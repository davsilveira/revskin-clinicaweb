<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Setting;
use App\Exports\CatalogoProdutosExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

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
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Produtos/Index', [
            'produtos' => $produtos,
            'filters' => $request->only(['search', 'ativo']),
            'lastSync' => Setting::get('tiny_produtos_last_sync'),
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
            'categoria' => 'nullable|string|max:255',
            'modo_uso' => 'nullable|string|max:255',
            'unidade' => 'nullable|string|max:20',
            'preco_venda' => 'nullable|numeric|min:0',
            'preco_custo' => 'nullable|numeric|min:0',
            'estoque_minimo' => 'nullable|integer|min:0',
            'tiny_id' => 'nullable|string|max:255',
            'ativo' => 'boolean',
        ]);

        // Map preco_venda to preco
        if (isset($validated['preco_venda'])) {
            $validated['preco'] = $validated['preco_venda'];
            unset($validated['preco_venda']);
        }

        Produto::create($validated);

        return redirect()->route('produtos.index')
            ->with('success', 'Produto cadastrado com sucesso!');
    }

    public function show(Produto $produto): Response
    {
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
            'nome' => 'nullable|string|max:255',
            'descricao' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'modo_uso' => 'nullable|string',
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
     * Catalogo de produtos (read-only para medicos).
     */
    public function catalogo(Request $request): Response
    {
        $query = Produto::ativo()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            });

        $produtos = $query->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'descricao', 'modo_uso', 'anotacoes']);

        return Inertia::render('Produtos/Catalogo', [
            'produtos' => $produtos,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Export catalogo de produtos (PDF ou Excel).
     */
    public function catalogoExport(Request $request)
    {
        $format = $request->get('format', 'pdf');

        $produtos = Produto::ativo()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            })
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'descricao', 'modo_uso', 'anotacoes']);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.catalogo-produtos', [
                'produtos' => $produtos,
                'total' => $produtos->count(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download('catalogo-produtos.pdf');
        }

        if ($format === 'xlsx') {
            return Excel::download(
                new CatalogoProdutosExport($produtos),
                'catalogo-produtos.xlsx'
            );
        }

        abort(400, 'Formato não suportado');
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










