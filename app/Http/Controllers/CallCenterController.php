<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoCallcenter;
use App\Models\Medico;
use App\Models\Produto;
use App\Models\ReceitaItemAquisicao;
use App\Models\Setting;
use App\Services\TinyPedidoSync;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AtendimentoCallcenter::with([
            'paciente:id,nome',
            'medico:id', 'medico.linkedUser:id,name,medico_id',
            'receita:id,numero',
            'usuario:id,name',
        ])
            ->ativo()
            ->when($request->search, function ($q, $search) {
                $q->whereHas('paciente', fn ($pq) => $pq->where('nome', 'like', "%{$search}%"));
            })
            ->when($request->medico_id, fn ($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->data_inicio, fn ($q, $data) => $q->whereDate('data_abertura', '>=', $data))
            ->when($request->data_fim, fn ($q, $data) => $q->whereDate('data_abertura', '<=', $data));

        $ordenarPor = $request->get('ordenar_por', 'data_abertura');
        $ordem = $request->get('ordem', 'desc');

        $atendimentos = $query->orderBy($ordenarPor, $ordem)
            ->paginate(20)
            ->withQueryString();

        $medicos = Medico::ativo()
            ->join('users', 'users.medico_id', '=', 'medicos.id')
            ->orderBy('users.name')
            ->select('medicos.id')
            ->get()
            ->load('linkedUser:id,name,medico_id');
        $statusOptions = AtendimentoCallcenter::getStatusOptions();

        return Inertia::render('CallCenter/Index', [
            'atendimentos' => $atendimentos,
            'medicos' => $medicos,
            'statusOptions' => $statusOptions,
            'filters' => $request->only(['search', 'medico_id', 'status', 'data_inicio', 'data_fim', 'ordenar_por', 'ordem']),
        ]);
    }

    public function show(AtendimentoCallcenter $atendimento): Response
    {
        $atendimento->load([
            'paciente.telefones',
            'paciente.medico.linkedUser:id,name,medico_id',
            'paciente.medicos:id,apelido,crm,uf_crm,nome_legado',
            'paciente.medicos.linkedUser:id,name,medico_id',
            'medico.linkedUser:id,name,medico_id',
            'receita.itens.produto',
            'receita.itens.aquisicoes',
            'usuario',
            'usuarioAlteracao',
            'acompanhamentos.usuario',
        ]);

        if ($atendimento->paciente) {
            $atendimento->paciente->attachPrivadosPorMedico();
        }
        // Datas de aquisição por linha (só deste receita_item + receita_itens.data_aquisicao)
        if ($atendimento->receita && $atendimento->receita->itens) {
            $atendimento->receita->itens->each(function ($item) {
                if (! $item->produto_id) {
                    $item->ultima_aquisicao = null;
                    $item->datas_aquisicao = [];

                    return;
                }

                $aquisicoes = $item->relationLoaded('aquisicoes')
                    ? $item->aquisicoes->sortByDesc('data_aquisicao')->values()
                    : $item->aquisicoes()->orderByDesc('data_aquisicao')->get();

                $datasStrings = $aquisicoes->pluck('data_aquisicao')->filter()->map(fn ($d) => $d->format('Y-m-d'));
                if ($item->data_aquisicao) {
                    $datasStrings->push($item->data_aquisicao->format('Y-m-d'));
                }
                $datasUnicas = $datasStrings->unique()->sortDesc()->values();

                $item->ultima_aquisicao = $datasUnicas->isNotEmpty() ? $datasUnicas->first() : null;
                $item->datas_aquisicao = $datasUnicas->all();
            });
        }

        $produtoColumns = ['id', 'codigo', 'nome', 'local_uso', 'preco', 'anotacoes', 'legado_somente_leitura', 'unidade'];
        $produtos = Produto::ativo()
            ->semLegadoSomenteLeitura()
            ->orderBy('codigo')
            ->get($produtoColumns);
        if ($atendimento->receita?->itens) {
            $legadoIds = $atendimento->receita->itens->pluck('produto_id')->filter()->diff($produtos->pluck('id'));
            if ($legadoIds->isNotEmpty()) {
                $produtos = $produtos->concat(Produto::whereIn('id', $legadoIds)->get($produtoColumns));
            }
        }
        $produtos = $produtos->map(function ($produto) {
            $produto->preco_venda = $produto->preco ?? 0;

            return $produto;
        });

        return Inertia::render('CallCenter/Show', [
            'atendimento' => $atendimento,
            'produtos' => $produtos,
        ]);
    }

    public function atualizarStatus(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(AtendimentoCallcenter::getStatusOptions())),
            'acompanhamento' => 'nullable|string',
        ]);

        $novoStatus = $validated['status'];
        $statusAnterior = $atendimento->status;

        $atendimento->atualizarStatus(
            $novoStatus,
            $request->user(),
            $validated['acompanhamento'] ?? null
        );

        // Register acquisition date when status changes to em_producao (sale closed)
        if ($novoStatus === AtendimentoCallcenter::STATUS_EM_PRODUCAO && $statusAnterior !== $novoStatus) {
            $this->registrarDatasAquisicao($atendimento);

            // Sincronizar com Tiny ERP (delay de 1 minuto) - só se integração estiver habilitada
            if (Setting::get('tiny_enabled', false)) {
                \App\Jobs\SyncVendaTinyJob::dispatch($atendimento)
                    ->delay(now()->addMinute());
            }
        }

        // Register acquisition date when status changes to finalizado (sale completed)
        if ($novoStatus === AtendimentoCallcenter::STATUS_FINALIZADO && $statusAnterior !== $novoStatus) {
            $this->registrarDatasAquisicao($atendimento);
        }

        if ($novoStatus === AtendimentoCallcenter::STATUS_CANCELADO && $statusAnterior !== $novoStatus) {
            $atendimento->load('receita');
            TinyPedidoSync::agendarCancelamento($atendimento->receita);
        }

        return back()->with('success', 'Status atualizado com sucesso!');
    }

    /**
     * Register acquisition dates for all items in the receita when sale is closed.
     */
    protected function registrarDatasAquisicao(AtendimentoCallcenter $atendimento): void
    {
        $receita = $atendimento->receita;
        if (! $receita) {
            return;
        }

        $dataAquisicao = now()->toDateString();

        foreach ($receita->itens as $item) {
            // Only register for items that are included (imprimir = true)
            if ($item->imprimir) {
                ReceitaItemAquisicao::create([
                    'receita_item_id' => $item->id,
                    'data_aquisicao' => $dataAquisicao,
                    'atendimento_id' => $atendimento->id,
                ]);

                // Also update the legacy field for backwards compatibility
                $item->update(['data_aquisicao' => $dataAquisicao]);
            }
        }
    }

    public function addAcompanhamento(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'descricao' => 'required|string',
            'tipo' => 'nullable|string|in:ligacao,whatsapp,email,observacao',
        ]);

        $atendimento->acompanhamentos()->create([
            'usuario_id' => $request->user()->id,
            'tipo' => $validated['tipo'] ?? 'observacao',
            'descricao' => $validated['descricao'],
            'data_registro' => now(),
        ]);

        $atendimento->update([
            'data_alteracao' => now(),
            'usuario_alteracao_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Acompanhamento registrado com sucesso!');
    }

    public function cancelarMultiplos(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:atendimentos_callcenter,id',
        ]);

        $atendimentos = AtendimentoCallcenter::with('receita')
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($atendimentos as $atendimento) {
            TinyPedidoSync::agendarCancelamento($atendimento->receita);
        }

        AtendimentoCallcenter::whereIn('id', $validated['ids'])
            ->update([
                'status' => AtendimentoCallcenter::STATUS_CANCELADO,
                'data_alteracao' => now(),
                'usuario_alteracao_id' => $request->user()->id,
            ]);

        return back()->with('success', 'Atendimentos cancelados com sucesso!');
    }
}
