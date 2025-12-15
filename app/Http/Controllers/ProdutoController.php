<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProdutoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Produto::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%")
                        ->orWhere('codigo_cq', 'like', "%{$search}%");
                });
            })
            ->when($request->has('ativo'), fn($q) => $q->where('ativo', $request->boolean('ativo')));

        $produtos = $query->orderBy('codigo')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Produtos/Index', [
            'produtos' => $produtos,
            'filters' => $request->only(['search', 'ativo']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Produtos/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:255|unique:produtos,codigo',
            'codigo_cq' => 'nullable|string|max:255',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'local_uso' => 'nullable|string|max:255',
            'ativo' => 'boolean',
        ]);

        Produto::create($validated);

        return redirect()->route('produtos.index')
            ->with('success', 'Produto cadastrado com sucesso!');
    }

    public function show(Produto $produto): Response
    {
        $produto->load('tabelasPreco');

        return Inertia::render('Produtos/Show', [
            'produto' => $produto,
        ]);
    }

    public function edit(Produto $produto): Response
    {
        return Inertia::render('Produtos/Form', [
            'produto' => $produto,
        ]);
    }

    public function update(Request $request, Produto $produto)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:255|unique:produtos,codigo,' . $produto->id,
            'codigo_cq' => 'nullable|string|max:255',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'local_uso' => 'nullable|string|max:255',
            'ativo' => 'boolean',
        ]);

        $produto->update($validated);

        return redirect()->route('produtos.index')
            ->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Produto $produto)
    {
        $produto->update(['ativo' => false]);

        return redirect()->route('produtos.index')
            ->with('success', 'Produto desativado com sucesso!');
    }

    /**
     * Search produtos for autocomplete.
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');

        $produtos = Produto::ativo()
            ->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->orderBy('codigo')
            ->limit(20)
            ->get(['id', 'codigo', 'nome', 'local_uso']);

        return response()->json($produtos);
    }
}




