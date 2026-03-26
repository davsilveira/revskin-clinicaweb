<?php

namespace App\Http\Controllers;

use App\Jobs\CriarNegociacaoRdStationJob;
use App\Jobs\CriarPedidoTinyJob;
use App\Models\AtendimentoCallcenter;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ReceitaController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Receita::with(['paciente:id,nome', 'medico:id', 'medico.linkedUser:id,name,medico_id'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('paciente', fn ($pq) => $pq->where('nome', 'like', "%{$search}%"));
            })
            ->when($request->paciente_id, fn ($q, $id) => $q->where('paciente_id', $id))
            ->when($request->medico_id, fn ($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->data_inicio, fn ($q, $data) => $q->whereDate('data_receita', '>=', $data))
            ->when($request->data_fim, fn ($q, $data) => $q->whereDate('data_receita', '<=', $data));

        // Filter by user access
        if ($user->isMedico() && $user->medico_id) {
            $query->where('medico_id', $user->medico_id);
        }

        $receitas = $query->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $medicos = Medico::ativo()
            ->join('users', 'users.medico_id', '=', 'medicos.id')
            ->orderBy('users.name')
            ->select('medicos.id')
            ->get()
            ->load('linkedUser:id,name,medico_id');

        return Inertia::render('Receitas/Index', [
            'receitas' => $receitas,
            'medicos' => $medicos,
            'filters' => $request->only(['search', 'medico_id', 'paciente_id', 'status', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $paciente = null;

        if ($request->paciente_id) {
            $paciente = Paciente::find($request->paciente_id);

            // Check if user can access this paciente
            if ($paciente && ! $user->canAccessPaciente($paciente)) {
                abort(403, 'Acesso não autorizado.');
            }
        }

        $medicos = $user->isMedico() && $user->medico_id
            ? Medico::where('id', $user->medico_id)->get()->load('linkedUser:id,name,medico_id')
            : Medico::ativo()->join('users', 'users.medico_id', '=', 'medicos.id')->orderBy('users.name')->select('medicos.id')->get()->load('linkedUser:id,name,medico_id');

        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome', 'local_uso', 'preco', 'anotacoes']);

        // Map preco to preco_venda for frontend compatibility
        $produtos = $produtos->map(function ($produto) {
            $produto->preco_venda = $produto->preco ?? 0;

            return $produto;
        });

        return Inertia::render('Receitas/Form', [
            'paciente' => $paciente,
            'medicos' => $medicos,
            'produtos' => $produtos,
            'defaultMedicoId' => $user->medico_id,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'paciente_id' => 'required|exists:pacientes,id',
            'medico_id' => 'required|exists:medicos,id',
            'data_receita' => 'required|date',
            'anotacoes' => 'nullable|string',
            'anotacoes_paciente' => 'nullable|string',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string',
            'valor_caixa' => 'nullable|numeric|min:0',
            'valor_frete' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:aberta,finalizada',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.anotacoes' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
            'itens.*.grupo' => 'nullable|string|in:recomendado,opcional',
        ]);

        // If user is medico, ensure they can only create receitas for themselves
        if ($user->isMedico() && $user->medico_id && $validated['medico_id'] != $user->medico_id) {
            return back()->with('error', 'Você não pode criar receitas para outros médicos.');
        }

        $receita = Receita::create([
            'numero' => Receita::gerarNumero($validated['paciente_id']),
            'paciente_id' => $validated['paciente_id'],
            'medico_id' => $validated['medico_id'],
            'data_receita' => $validated['data_receita'],
            'anotacoes' => $validated['anotacoes'] ?? null,
            'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? null,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'status' => $validated['status'] ?? 'aberta',
        ]);

        foreach ($validated['itens'] as $index => $item) {
            $receita->itens()->create([
                'produto_id' => $item['produto_id'],
                'local_uso' => $item['local_uso'] ?? null,
                'anotacoes' => $item['anotacoes'] ?? null,
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'],
                'valor_total' => $item['quantidade'] * $item['valor_unitario'],
                'imprimir' => $item['imprimir'] ?? true,
                'grupo' => $item['grupo'] ?? 'recomendado',
                'ordem' => $index,
            ]);
        }

        $receita->calcularTotais();

        // Create callcenter atendimento if status is finalizada
        if ($receita->status === 'finalizada') {
            AtendimentoCallcenter::create([
                'receita_id' => $receita->id,
                'paciente_id' => $receita->paciente_id,
                'medico_id' => $receita->medico_id,
                'status' => AtendimentoCallcenter::STATUS_ENTRAR_EM_CONTATO,
                'data_abertura' => now(),
                'usuario_id' => $request->user()->id,
            ]);
        }

        return redirect()->route('receitas.show', $receita)
            ->with('success', 'Receita cadastrada com sucesso!');
    }

    public function show(Receita $receita): Response
    {
        return $this->renderReceitaForm($receita, request(), viewMode: true);
    }

    public function edit(Request $request, Receita $receita): Response|RedirectResponse
    {
        // Redirect finalized prescriptions to view – they are not editable
        if ($receita->status === 'finalizada') {
            return redirect()->route('receitas.show', $receita);
        }

        return $this->renderReceitaForm($receita, $request, viewMode: false);
    }

    private function renderReceitaForm(Receita $receita, Request $request, bool $viewMode = false): Response
    {
        $receita->load(['paciente', 'medico.linkedUser:id,name,medico_id', 'itens.produto', 'itens.aquisicoes', 'atendimentoCallcenter']);

        $this->loadAcquisitionDates($receita);

        $medicos = Medico::ativo()
            ->join('users', 'users.medico_id', '=', 'medicos.id')
            ->orderBy('users.name')
            ->select('medicos.id')
            ->get()
            ->load('linkedUser:id,name,medico_id');
        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome', 'local_uso', 'preco', 'anotacoes']);

        $produtos = $produtos->map(function ($produto) {
            $produto->preco_venda = $produto->preco ?? 0;

            return $produto;
        });

        $receitasAnteriores = Receita::where('paciente_id', $receita->paciente_id)
            ->where('id', '!=', $receita->id)
            ->where('ativo', true)
            ->with(['itens.produto:id,codigo,nome,local_uso', 'itens.aquisicoes', 'medico:id', 'medico.linkedUser:id,name,medico_id'])
            ->orderByDesc('id')
            ->take(10)
            ->get();

        $receitasAnteriores->each(function ($r) {
            $r->itens->each(function ($item) {
                $item->ultima_aquisicao = $item->ultima_aquisicao?->format('Y-m-d');
                $item->datas_aquisicao = collect($item->datas_aquisicao)->map(fn ($d) => $d->format('Y-m-d'))->toArray();
            });
        });

        $user = $request->user();

        $bloqueadaPorAtendimento = $receita->atendimentoCallcenter &&
            in_array($receita->atendimentoCallcenter->status, ['em_producao', 'finalizado']);
        $bloqueadaPorMedicoFinalizada = $user->isMedico() && $receita->status === 'finalizada';
        $bloqueadaParaEdicao = $bloqueadaPorAtendimento || $bloqueadaPorMedicoFinalizada;

        $props = [
            'receita' => $receita,
            'paciente' => $receita->paciente,
            'medicos' => $medicos,
            'produtos' => $produtos,
            'receitasAnteriores' => $receitasAnteriores,
            'bloqueadaParaEdicao' => $bloqueadaParaEdicao,
            'viewMode' => $viewMode,
        ];

        if (config('app.enable_debug_receitas')) {
            $props['casoClinico'] = session('caso_clinico_debug');
        }

        return Inertia::render('Receitas/Form', $props);
    }

    private function loadAcquisitionDates(Receita $receita): void
    {
        $receita->itens->each(function ($item) use ($receita) {
            if (! $item->produto_id) {
                $item->ultima_aquisicao = null;
                $item->datas_aquisicao = [];

                return;
            }

            $aquisicoes = \App\Models\ReceitaItemAquisicao::whereHas('receitaItem', function ($query) use ($receita, $item) {
                $query->where('produto_id', $item->produto_id)
                    ->whereHas('receita', function ($q) use ($receita) {
                        $q->where('paciente_id', $receita->paciente_id);
                    });
            })->orderByDesc('data_aquisicao')->get();

            $datasAquisicao = $aquisicoes->pluck('data_aquisicao')->filter()->unique()->sortDesc()->values();

            $item->ultima_aquisicao = $datasAquisicao->isNotEmpty() ? $datasAquisicao->first()->format('Y-m-d') : null;
            $item->datas_aquisicao = $datasAquisicao->map(fn ($d) => $d->format('Y-m-d'))->toArray();
        });
    }

    public function update(Request $request, Receita $receita)
    {
        $user = $request->user();

        // Check if receita is blocked for editing (atendimento in production or finalized)
        $receita->load('atendimentoCallcenter');
        if ($receita->atendimentoCallcenter &&
            in_array($receita->atendimentoCallcenter->status, ['em_producao', 'finalizado'])) {
            return back()->with('error', 'Esta receita não pode ser editada pois o atendimento já está em produção ou finalizado.');
        }

        // Bloquear edição de receitas finalizadas para todos os usuários
        if ($receita->status === 'finalizada') {
            return redirect()->route('receitas.show', $receita)
                ->with('error', 'Esta receita não pode ser editada pois já está finalizada.');
        }

        $validated = $request->validate([
            'data_receita' => 'required|date',
            'anotacoes' => 'nullable|string',
            'anotacoes_paciente' => 'nullable|string',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string',
            'valor_caixa' => 'nullable|numeric|min:0',
            'valor_frete' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:aberta,finalizada,cancelada',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.anotacoes' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
            'itens.*.grupo' => 'nullable|string|in:recomendado,opcional',
        ]);

        // If user is medico, ensure they cannot change medico_id
        // For admin/callcenter, allow medico_id to be updated if provided
        $updateData = [
            'data_receita' => $validated['data_receita'],
            'anotacoes' => $validated['anotacoes'] ?? null,
            'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? null,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'status' => $validated['status'] ?? $receita->status,
        ];

        // Only allow medico_id update for admin/callcenter
        if (! $user->isMedico() && $request->has('medico_id')) {
            $updateData['medico_id'] = $request->input('medico_id');
        }
        // For medico, ensure medico_id remains unchanged (already set to their own)

        $receita->update($updateData);

        // Sync items
        $receita->itens()->delete();
        foreach ($validated['itens'] as $index => $item) {
            $receita->itens()->create([
                'produto_id' => $item['produto_id'],
                'local_uso' => $item['local_uso'] ?? null,
                'anotacoes' => $item['anotacoes'] ?? null,
                'quantidade' => $item['quantidade'],
                'valor_unitario' => $item['valor_unitario'],
                'valor_total' => $item['quantidade'] * $item['valor_unitario'],
                'imprimir' => $item['imprimir'] ?? true,
                'grupo' => $item['grupo'] ?? 'recomendado',
                'ordem' => $index,
            ]);
        }

        $receita->calcularTotais();

        if ($receita->status === 'finalizada' && ! $receita->atendimentoCallcenter) {
            AtendimentoCallcenter::create([
                'receita_id' => $receita->id,
                'paciente_id' => $receita->paciente_id,
                'medico_id' => $receita->medico_id,
                'status' => AtendimentoCallcenter::STATUS_ENTRAR_EM_CONTATO,
                'data_abertura' => now(),
                'usuario_id' => $request->user()->id,
            ]);

            if (Setting::get('tiny_enabled', false)) {
                CriarPedidoTinyJob::dispatch($receita)->delay(now()->addMinute());
                Log::info('Tiny ERP: CriarPedidoTinyJob despachado', [
                    'receita_id' => $receita->id,
                    'receita_numero' => $receita->numero,
                ]);
            }

            if (Setting::get('rd_enabled', false)) {
                CriarNegociacaoRdStationJob::dispatch($receita)->delay(now()->addMinute());
            }
        }

        return redirect()->route('receitas.show', $receita)
            ->with('success', 'Receita atualizada com sucesso!');
    }

    public function destroy(Receita $receita)
    {
        $receita->update(['status' => 'cancelada', 'ativo' => false]);

        return redirect()->route('receitas.index')
            ->with('success', 'Receita cancelada com sucesso!');
    }

    /**
     * Autosave - Store or update without redirect (for AJAX autosave).
     */
    public function autosave(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:receitas,id',
            'paciente_id' => 'required|exists:pacientes,id',
            'medico_id' => 'required|exists:medicos,id',
            'data_receita' => 'required|date',
            'anotacoes' => 'nullable|string',
            'anotacoes_paciente' => 'nullable|string',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string',
            'valor_caixa' => 'nullable|numeric|min:0',
            'valor_frete' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:aberta,finalizada',
            'itens' => 'nullable|array',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.anotacoes' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
            'itens.*.grupo' => 'nullable|string|in:recomendado,opcional',
        ]);

        $id = $validated['id'] ?? null;
        unset($validated['id']);

        $user = $request->user();

        // If user is medico, ensure they can only save for themselves
        if ($user->isMedico() && $user->medico_id && $validated['medico_id'] != $user->medico_id) {
            return response()->json(['error' => 'Acesso não autorizado'], 403);
        }

        if ($id) {
            $receita = Receita::findOrFail($id);

            // Check access
            if ($user->isMedico() && $receita->medico_id != $user->medico_id) {
                return response()->json(['error' => 'Acesso não autorizado'], 403);
            }

            $receita->update([
                'medico_id' => $validated['medico_id'],
                'data_receita' => $validated['data_receita'],
                'anotacoes' => $validated['anotacoes'] ?? null,
                'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
                'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
                'desconto_motivo' => $validated['desconto_motivo'] ?? null,
                'valor_caixa' => $validated['valor_caixa'] ?? 0,
                'valor_frete' => $validated['valor_frete'] ?? 0,
            ]);
        } else {
            $receita = Receita::create([
                'numero' => Receita::gerarNumero($validated['paciente_id']),
                'paciente_id' => $validated['paciente_id'],
                'medico_id' => $validated['medico_id'],
                'data_receita' => $validated['data_receita'],
                'anotacoes' => $validated['anotacoes'] ?? null,
                'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
                'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
                'desconto_motivo' => $validated['desconto_motivo'] ?? null,
                'valor_caixa' => $validated['valor_caixa'] ?? 0,
                'valor_frete' => $validated['valor_frete'] ?? 0,
                'status' => 'aberta',
            ]);
        }

        // Sync items if provided
        if (! empty($validated['itens'])) {
            $receita->itens()->delete();
            foreach ($validated['itens'] as $index => $item) {
                $receita->itens()->create([
                    'produto_id' => $item['produto_id'],
                    'local_uso' => $item['local_uso'] ?? null,
                    'anotacoes' => $item['anotacoes'] ?? null,
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'valor_total' => $item['quantidade'] * $item['valor_unitario'],
                    'imprimir' => $item['imprimir'] ?? true,
                    'grupo' => $item['grupo'] ?? 'recomendado',
                    'ordem' => $index,
                ]);
            }
            $receita->calcularTotais();
        }

        return response()->json([
            'success' => true,
            'id' => $receita->id,
            'numero' => $receita->numero,
            'saved_at' => now()->toISOString(),
        ]);
    }

    /**
     * Copy receita from another.
     */
    public function copiar(Request $request, Receita $receita)
    {
        $novaReceita = Receita::create([
            'numero' => Receita::gerarNumero($receita->paciente_id),
            'paciente_id' => $receita->paciente_id,
            'medico_id' => $request->user()->medico_id ?? $receita->medico_id,
            'data_receita' => now(),
            'anotacoes' => $receita->anotacoes,
            'status' => 'aberta',
        ]);

        $novaReceita->copiarItensDeReceita($receita);

        $url = route('receitas.edit', $novaReceita).'?duplicada=1';

        return Inertia::location($url);
    }

    /**
     * Generate PDF.
     */
    public function pdf(Receita $receita)
    {
        $receita->load([
            'paciente',
            'medico.linkedUser:id,name,medico_id',
            'medico.clinica',
            'medico.clinicas' => fn ($q) => $q->orderBy('clinicas.nome'),
            'itens' => fn ($q) => $q->where('imprimir', true)->with('produto'),
        ]);

        $clinica = $receita->medico->clinicaParaReceita();
        $clinicaLogoFullPath = null;
        if ($clinica?->logo_path) {
            $fullPath = storage_path('app/public/'.$clinica->logo_path);
            if (file_exists($fullPath)) {
                $clinicaLogoFullPath = $fullPath;
            }
        }

        $pdf = Pdf::loadView('pdf.receita', [
            'receita' => $receita,
            'clinicaLogoFullPath' => $clinicaLogoFullPath,
        ]);

        return $pdf->download("receita-{$receita->numero}.pdf");
    }
}
