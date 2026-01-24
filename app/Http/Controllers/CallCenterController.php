<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoCallcenter;
use App\Models\Medico;
use App\Models\Produto;
use App\Models\ReceitaItem;
use App\Models\ReceitaItemAquisicao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AtendimentoCallcenter::with([
                'paciente:id,nome',
                'medico:id,nome',
                'receita:id,numero',
                'usuario:id,name',
            ])
            ->ativo()
            ->when($request->search, function ($q, $search) {
                $q->whereHas('paciente', fn($pq) => $pq->where('nome', 'like', "%{$search}%"));
            })
            ->when($request->medico_id, fn($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_abertura', '>=', $data))
            ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_abertura', '<=', $data));

        $ordenarPor = $request->get('ordenar_por', 'data_abertura');
        $ordem = $request->get('ordem', 'desc');

        $atendimentos = $query->orderBy($ordenarPor, $ordem)
            ->paginate(20)
            ->withQueryString();

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);
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
            'paciente.medico',
            'medico',
            'receita.itens.produto',
            'usuario',
            'usuarioAlteracao',
            'acompanhamentos.usuario',
        ]);

        $statusOptions = AtendimentoCallcenter::getStatusOptions();
        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome', 'preco', 'preco_venda', 'local_uso']);

        return Inertia::render('CallCenter/Show', [
            'atendimento' => $atendimento,
            'statusOptions' => $statusOptions,
            'produtos' => $produtos,
        ]);
    }

    /**
     * Update the receita items for an atendimento.
     */
    public function updateReceita(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'itens' => 'array',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string|max:255',
            'itens.*.anotacoes' => 'nullable|string|max:500',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
            'itens.*.grupo' => 'nullable|string|in:recomendado,opcional',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string|max:255',
            'valor_frete' => 'nullable|numeric|min:0',
            'valor_caixa' => 'nullable|numeric|min:0',
            'anotacoes' => 'nullable|string',
        ]);

        $receita = $atendimento->receita;
        if (!$receita) {
            return back()->with('error', 'Receita não encontrada');
        }

        // Update receita fields
        $receita->update([
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? '',
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
            'anotacoes' => $validated['anotacoes'] ?? $receita->anotacoes,
        ]);

        // Delete existing items and recreate
        $receita->itens()->delete();

        foreach ($validated['itens'] ?? [] as $ordem => $itemData) {
            if (empty($itemData['produto_id'])) {
                continue;
            }

            $receita->itens()->create([
                'produto_id' => $itemData['produto_id'],
                'local_uso' => $itemData['local_uso'] ?? '',
                'anotacoes' => $itemData['anotacoes'] ?? '',
                'quantidade' => $itemData['quantidade'],
                'valor_unitario' => $itemData['valor_unitario'],
                'valor_total' => $itemData['quantidade'] * $itemData['valor_unitario'],
                'imprimir' => $itemData['imprimir'] ?? true,
                'grupo' => $itemData['grupo'] ?? 'recomendado',
                'ordem' => $ordem,
            ]);
        }

        // Recalculate totals
        $receita->refresh();
        $receita->calcularTotais();

        // Update atendimento timestamp
        $atendimento->update([
            'data_alteracao' => now(),
            'usuario_alteracao_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Produtos atualizados com sucesso!');
    }

    /**
     * Autosave receita items (API endpoint).
     */
    public function autosaveReceita(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'itens' => 'array',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string|max:255',
            'itens.*.anotacoes' => 'nullable|string|max:500',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
            'itens.*.grupo' => 'nullable|string|in:recomendado,opcional',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string|max:255',
            'valor_frete' => 'nullable|numeric|min:0',
            'valor_caixa' => 'nullable|numeric|min:0',
        ]);

        $receita = $atendimento->receita;
        if (!$receita) {
            return response()->json(['error' => 'Receita não encontrada'], 404);
        }

        // Update receita fields
        $receita->update([
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? '',
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
        ]);

        // Delete existing items and recreate
        $receita->itens()->delete();

        foreach ($validated['itens'] ?? [] as $ordem => $itemData) {
            if (empty($itemData['produto_id'])) {
                continue;
            }

            $receita->itens()->create([
                'produto_id' => $itemData['produto_id'],
                'local_uso' => $itemData['local_uso'] ?? '',
                'anotacoes' => $itemData['anotacoes'] ?? '',
                'quantidade' => $itemData['quantidade'],
                'valor_unitario' => $itemData['valor_unitario'],
                'valor_total' => $itemData['quantidade'] * $itemData['valor_unitario'],
                'imprimir' => $itemData['imprimir'] ?? true,
                'grupo' => $itemData['grupo'] ?? 'recomendado',
                'ordem' => $ordem,
            ]);
        }

        // Recalculate totals
        $receita->refresh();
        $receita->calcularTotais();

        return response()->json([
            'success' => true,
            'receita_id' => $receita->id,
            'valor_total' => $receita->valor_total,
        ]);
    }

    public function atualizarStatus(Request $request, AtendimentoCallcenter $atendimento)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(AtendimentoCallcenter::getStatusOptions())),
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
        }

        return back()->with('success', 'Status atualizado com sucesso!');
    }

    /**
     * Register acquisition dates for all items in the receita when sale is closed.
     */
    protected function registrarDatasAquisicao(AtendimentoCallcenter $atendimento): void
    {
        $receita = $atendimento->receita;
        if (!$receita) {
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

        AtendimentoCallcenter::whereIn('id', $validated['ids'])
            ->update([
                'status' => AtendimentoCallcenter::STATUS_CANCELADO,
                'data_alteracao' => now(),
                'usuario_alteracao_id' => $request->user()->id,
            ]);

        return back()->with('success', 'Atendimentos cancelados com sucesso!');
    }
}










