<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoCallcenter;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceitaController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Receita::with(['paciente:id,nome', 'medico:id,nome'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('paciente', fn($pq) => $pq->where('nome', 'like', "%{$search}%"));
            })
            ->when($request->medico_id, fn($q, $medicoId) => $q->where('medico_id', $medicoId))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->data_inicio, fn($q, $data) => $q->whereDate('data_receita', '>=', $data))
            ->when($request->data_fim, fn($q, $data) => $q->whereDate('data_receita', '<=', $data));

        // Filter by user access
        if ($user->isMedico() && $user->medico_id) {
            $query->where('medico_id', $user->medico_id);
        }

        $receitas = $query->orderByDesc('data_receita')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Receitas/Index', [
            'receitas' => $receitas,
            'medicos' => $medicos,
            'filters' => $request->only(['search', 'medico_id', 'status', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $paciente = null;

        if ($request->paciente_id) {
            $paciente = Paciente::find($request->paciente_id);
        }

        $medicos = $user->isMedico() && $user->medico_id
            ? Medico::where('id', $user->medico_id)->get(['id', 'nome'])
            : Medico::ativo()->orderBy('nome')->get(['id', 'nome']);

        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome', 'local_uso']);
        
        // Get prices from default price table
        $tabelaPadrao = \App\Models\TabelaPreco::where('ativo', true)->orderBy('id')->first();
        if ($tabelaPadrao) {
            $precos = \App\Models\TabelaPrecoItem::where('tabela_preco_id', $tabelaPadrao->id)
                ->pluck('preco', 'produto_id');
            $produtos = $produtos->map(function ($produto) use ($precos) {
                $produto->preco_venda = $precos[$produto->id] ?? 0;
                return $produto;
            });
        }

        return Inertia::render('Receitas/Form', [
            'paciente' => $paciente,
            'medicos' => $medicos,
            'produtos' => $produtos,
            'defaultMedicoId' => $user->medico_id,
        ]);
    }

    public function store(Request $request)
    {
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
            'status' => 'nullable|in:rascunho,finalizada',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.anotacoes' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
        ]);

        $receita = Receita::create([
            'numero' => Receita::gerarNumero(),
            'paciente_id' => $validated['paciente_id'],
            'medico_id' => $validated['medico_id'],
            'data_receita' => $validated['data_receita'],
            'anotacoes' => $validated['anotacoes'] ?? null,
            'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? null,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'status' => $validated['status'] ?? 'rascunho',
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
        $receita->load(['paciente', 'medico', 'itens.produto', 'atendimentoCallcenter']);

        return Inertia::render('Receitas/Show', [
            'receita' => $receita,
        ]);
    }

    public function edit(Receita $receita): Response
    {
        $receita->load(['paciente', 'medico', 'itens.produto']);

        $medicos = Medico::ativo()->orderBy('nome')->get(['id', 'nome']);
        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome', 'local_uso']);
        
        // Get prices from default price table
        $tabelaPadrao = \App\Models\TabelaPreco::where('ativo', true)->orderBy('id')->first();
        if ($tabelaPadrao) {
            $precos = \App\Models\TabelaPrecoItem::where('tabela_preco_id', $tabelaPadrao->id)
                ->pluck('preco', 'produto_id');
            $produtos = $produtos->map(function ($produto) use ($precos) {
                $produto->preco_venda = $precos[$produto->id] ?? 0;
                return $produto;
            });
        }

        return Inertia::render('Receitas/Form', [
            'receita' => $receita,
            'paciente' => $receita->paciente,
            'medicos' => $medicos,
            'produtos' => $produtos,
        ]);
    }

    public function update(Request $request, Receita $receita)
    {
        $validated = $request->validate([
            'data_receita' => 'required|date',
            'anotacoes' => 'nullable|string',
            'anotacoes_paciente' => 'nullable|string',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'desconto_motivo' => 'nullable|string',
            'valor_caixa' => 'nullable|numeric|min:0',
            'valor_frete' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:rascunho,finalizada,cancelada',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.local_uso' => 'nullable|string',
            'itens.*.anotacoes' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.imprimir' => 'boolean',
        ]);

        $receita->update([
            'data_receita' => $validated['data_receita'],
            'anotacoes' => $validated['anotacoes'] ?? null,
            'anotacoes_paciente' => $validated['anotacoes_paciente'] ?? null,
            'desconto_percentual' => $validated['desconto_percentual'] ?? 0,
            'desconto_motivo' => $validated['desconto_motivo'] ?? null,
            'valor_caixa' => $validated['valor_caixa'] ?? 0,
            'valor_frete' => $validated['valor_frete'] ?? 0,
            'status' => $validated['status'] ?? $receita->status,
        ]);

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
                'ordem' => $index,
            ]);
        }

        $receita->calcularTotais();

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
     * Copy receita from another.
     */
    public function copiar(Request $request, Receita $receita)
    {
        $novaReceita = Receita::create([
            'numero' => Receita::gerarNumero(),
            'paciente_id' => $receita->paciente_id,
            'medico_id' => $request->user()->medico_id ?? $receita->medico_id,
            'data_receita' => now(),
            'anotacoes' => $receita->anotacoes,
            'status' => 'rascunho',
        ]);

        $novaReceita->copiarItensDeReceita($receita);

        return redirect()->route('receitas.edit', $novaReceita)
            ->with('success', 'Receita copiada com sucesso! Faça os ajustes necessários.');
    }

    /**
     * Generate PDF.
     */
    public function pdf(Receita $receita)
    {
        $receita->load(['paciente', 'medico', 'itens' => fn($q) => $q->where('imprimir', true)->with('produto')]);

        $pdf = Pdf::loadView('pdf.receita', [
            'receita' => $receita,
        ]);

        return $pdf->download("receita-{$receita->numero}.pdf");
    }
}




