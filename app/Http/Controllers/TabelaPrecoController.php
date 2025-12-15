<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\TabelaPreco;
use App\Models\TabelaPrecoItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TabelaPrecoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TabelaPreco::withCount('itens')
            ->when($request->search, fn($q, $search) => $q->where('nome', 'like', "%{$search}%"))
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $tabelas = $query->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('TabelasPreco/Index', [
            'tabelas' => $tabelas,
            'filters' => $request->only(['search', 'ativo']),
        ]);
    }

    public function create(): Response
    {
        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome']);

        return Inertia::render('TabelasPreco/Form', [
            'produtos' => $produtos,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'itens' => 'nullable|array',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.preco' => 'required|numeric|min:0',
        ]);

        $tabela = TabelaPreco::create([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        if (!empty($validated['itens'])) {
            foreach ($validated['itens'] as $item) {
                $tabela->itens()->create([
                    'produto_id' => $item['produto_id'],
                    'preco' => $item['preco'],
                ]);
            }
        }

        return redirect()->route('tabelas-preco.index')
            ->with('success', 'Tabela de preço cadastrada com sucesso!');
    }

    public function show(TabelaPreco $tabelas_preco): Response
    {
        $tabelas_preco->load(['itens.produto']);

        return Inertia::render('TabelasPreco/Show', [
            'tabela' => $tabelas_preco,
        ]);
    }

    public function edit(TabelaPreco $tabelas_preco): Response
    {
        $tabelas_preco->load(['itens.produto']);
        $produtos = Produto::ativo()->orderBy('codigo')->get(['id', 'codigo', 'nome']);

        return Inertia::render('TabelasPreco/Form', [
            'tabela' => $tabelas_preco,
            'produtos' => $produtos,
        ]);
    }

    public function update(Request $request, TabelaPreco $tabelas_preco)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'itens' => 'nullable|array',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.preco' => 'required|numeric|min:0',
        ]);

        $tabelas_preco->update([
            'nome' => $validated['nome'],
            'descricao' => $validated['descricao'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        // Sync items
        $tabelas_preco->itens()->delete();
        if (!empty($validated['itens'])) {
            foreach ($validated['itens'] as $item) {
                $tabelas_preco->itens()->create([
                    'produto_id' => $item['produto_id'],
                    'preco' => $item['preco'],
                ]);
            }
        }

        return redirect()->route('tabelas-preco.index')
            ->with('success', 'Tabela de preço atualizada com sucesso!');
    }

    public function destroy(TabelaPreco $tabelas_preco)
    {
        $tabelas_preco->update(['ativo' => false]);

        return redirect()->route('tabelas-preco.index')
            ->with('success', 'Tabela de preço desativada com sucesso!');
    }
}




