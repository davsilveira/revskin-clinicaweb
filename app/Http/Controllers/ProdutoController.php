<?php

namespace App\Http\Controllers;

use App\Exports\ProdutosAdminExport;
use App\Exports\ProdutosTemplateExport;
use App\Models\Produto;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProdutoController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->search;
        if (in_array($search, ['undefined', 'null', ''], true)) {
            $search = null;
        }

        $query = Produto::query()
            ->when($request->boolean('legado_somente_leitura'), function ($q) {
                // Só legados ainda «pendentes» (ativo): mapeados/arquivados (ativo=0) não aparecem aqui.
                $q->where('legado_somente_leitura', true)->where('ativo', true);
            }, fn ($q) => $q->where('legado_somente_leitura', false))
            ->when($search, function ($q, $s) {
                $q->where(function ($query) use ($s) {
                    $query->where('nome', 'like', "%{$s}%")
                        ->orWhere('codigo', 'like', "%{$s}%")
                        ->orWhere('codigo_cq', 'like', "%{$s}%");
                });
            })
            ->when($request->has('ativo'), fn ($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->boolean('pendentes'), function ($q) {
                $q->where(function ($query) {
                    $query->whereNull('descricao')->orWhere('descricao', '');
                })->where(function ($query) {
                    $query->whereNull('modo_uso')->orWhere('modo_uso', '');
                });
            });

        $produtos = $query->orderBy('codigo')
            ->paginate(50)
            ->withQueryString();

        if ($request->user()?->role !== 'admin') {
            $produtos->through(function (Produto $produto) {
                $produto->makeHidden('anotacoes_internas');

                return $produto;
            });
        }

        $filters = $request->only(['search', 'ativo', 'pendentes', 'legado_somente_leitura']);
        if (isset($filters['search']) && in_array($filters['search'], ['undefined', 'null', ''], true)) {
            unset($filters['search']);
        }

        $totalGeral = Produto::where('legado_somente_leitura', false)->count();
        $totalLegadoPendentes = Produto::query()
            ->where('legado_somente_leitura', true)
            ->where('ativo', true)
            ->count();

        return Inertia::render('Produtos/Index', [
            'produtos' => $produtos,
            'totalGeral' => $totalGeral,
            'totalLegadoPendentes' => $totalLegadoPendentes,
            'filters' => $filters,
            'lastSync' => Setting::get('tiny_produtos_last_sync'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Produtos/Form');
    }

    public function store(Request $request)
    {
        $rules = [
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
        ];
        if ($request->user()?->role === 'admin') {
            $rules['anotacoes_internas'] = 'nullable|string';
        }
        $validated = $request->validate($rules);
        if ($request->user()?->role !== 'admin') {
            unset($validated['anotacoes_internas']);
        }

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
        $rules = [
            'descricao' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'modo_uso' => 'nullable|string',
            'ativo' => 'boolean',
        ];
        if ($request->user()?->role === 'admin') {
            $rules['anotacoes_internas'] = 'nullable|string';
        }
        $validated = $request->validate($rules);
        if ($request->user()?->role !== 'admin') {
            unset($validated['anotacoes_internas']);
        }
        // Nome vem do Tiny/oList e é atualizado só pela sincronização.
        unset($validated['nome']);

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
            ->semLegadoSomenteLeitura()
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
     * Export produtos (admin) - todos os dados.
     */
    public function export(Request $request)
    {
        $search = $request->search;
        if ($search === 'undefined' || $search === 'null' || $search === '') {
            $search = null;
        }

        $query = Produto::query()
            ->when($search, function ($q, $s) {
                $q->where(function ($query) use ($s) {
                    $query->where('nome', 'like', "%{$s}%")
                        ->orWhere('codigo', 'like', "%{$s}%")
                        ->orWhere('codigo_cq', 'like', "%{$s}%");
                });
            })
            ->when($request->has('ativo'), fn ($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->boolean('pendentes'), function ($q) {
                $q->where(function ($query) {
                    $query->whereNull('descricao')->orWhere('descricao', '');
                })->where(function ($query) {
                    $query->whereNull('modo_uso')->orWhere('modo_uso', '');
                });
            })
            ->orderBy('codigo');

        $produtos = $query->get();
        $format = $request->get('format', 'xlsx');
        $date = now()->format('Y-m-d');
        $includeAnotacoesInternas = $request->user()?->role === 'admin';

        if ($format === 'csv') {
            return Excel::download(
                new ProdutosAdminExport($produtos, $includeAnotacoesInternas),
                "produtos-export-{$date}.csv",
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new ProdutosAdminExport($produtos, $includeAnotacoesInternas),
            "produtos-export-{$date}.xlsx"
        );
    }

    /**
     * Download do modelo minimal para importação (apenas colunas editáveis).
     */
    public function template(Request $request)
    {
        $format = $request->get('format', 'xlsx');
        $date = now()->format('Y-m-d');
        $includeAnotacoesInternas = $request->user()?->role === 'admin';

        if ($format === 'csv') {
            return Excel::download(
                new ProdutosTemplateExport($includeAnotacoesInternas),
                "produtos-modelo-{$date}.csv",
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new ProdutosTemplateExport($includeAnotacoesInternas),
            "produtos-modelo-{$date}.xlsx"
        );
    }

    private function getVal(array $row, string $key): mixed
    {
        $keyLower = mb_strtolower($key);
        foreach ($row as $k => $v) {
            if (mb_strtolower(trim((string) $k)) === $keyLower) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function getValAliases(array $row, array $aliases): mixed
    {
        foreach ($aliases as $key) {
            $v = $this->getVal($row, $key);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    private function extractDadosFromRow(array $row, bool $importAnotacoesInternas = false): array
    {
        $dados = [];
        // Nome é sincronizado do Tiny/oList — ignorado na importação em massa.
        $desc = $this->getValAliases($row, ['descricao_formula', 'descricao']);
        if ($desc !== null) {
            $dados['descricao'] = $desc;
        }
        $modo = $this->getValAliases($row, ['modo_uso', 'modo_de_uso']);
        if ($modo !== null) {
            $dados['modo_uso'] = $modo;
        }
        $anot = $this->getValAliases($row, ['anotacoes_especialista', 'anotacoes_dos_especialistas', 'anotacoes']);
        if ($anot !== null) {
            $dados['anotacoes'] = $anot;
        }
        if ($importAnotacoesInternas) {
            $internas = $this->getVal($row, 'anotacoes_internas');
            if ($internas !== null) {
                $dados['anotacoes_internas'] = $internas;
            }
        }
        $val = $this->getValAliases($row, ['ativo', 'status']);
        if ($val !== null) {
            $ativo = in_array(strtolower((string) $val), ['1', 'sim', 's', 'ativo', 'true']);
            $dados['ativo'] = $ativo;
        }

        return $dados;
    }

    /**
     * Preview da importação: valida colunas, conta alterações, não executa.
     */
    public function importarEdicoesPreview(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $file = $request->file('arquivo');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?? '');
        $linhas = $this->lerArquivoImportacao($file->getRealPath(), $ext);

        $headersNorm = [];
        if (! empty($linhas)) {
            $headersNorm = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_keys($linhas[0]));
        }

        $temCodigo = in_array('codigo', $headersNorm) || in_array('codigo_legado', $headersNorm);
        $missingCols = [];
        if (! $temCodigo) {
            $missingCols[] = 'codigo';
        }
        $columnsOk = empty($missingCols);

        if (! $columnsOk) {
            return back()->with([
                'import_preview' => [
                    'columns_ok' => false,
                    'missing_columns' => $missingCols,
                    'total_rows' => count($linhas),
                    'alteracoes_count' => 0,
                    'nao_encontrados_count' => 0,
                ],
            ]);
        }

        $alteracoesCount = 0;
        $naoEncontradosCount = 0;
        $naoEncontradosList = [];
        $importAnotacoesInternas = $request->user()?->role === 'admin';

        foreach ($linhas as $row) {
            $codigo = trim((string) ($this->getVal($row, 'codigo') ?? $this->getVal($row, 'codigo_legado') ?? ''));
            if ($codigo === '') {
                continue;
            }
            $produto = Produto::where('codigo', $codigo)->first();
            if (! $produto) {
                $naoEncontradosCount++;
                $naoEncontradosList[] = $codigo;

                continue;
            }
            $dados = $this->extractDadosFromRow($row, $importAnotacoesInternas);
            if (! empty($dados)) {
                $alteracoesCount++;
            }
        }

        $tempName = 'preview_'.uniqid().'.'.($ext ?: 'tmp');
        $stored = Storage::putFileAs('imports', $file, $tempName);
        $fullPath = Storage::path('imports/'.$tempName);

        session()->put('import_preview_data', [
            'path' => $fullPath,
            'ext' => $ext,
            'temp_key' => 'imports/'.$tempName,
        ]);

        return back()->with([
            'import_preview' => [
                'columns_ok' => true,
                'missing_columns' => [],
                'total_rows' => count($linhas),
                'alteracoes_count' => $alteracoesCount,
                'nao_encontrados_count' => $naoEncontradosCount,
                'nao_encontrados' => array_slice($naoEncontradosList, 0, 10),
                'can_confirm' => true,
            ],
        ]);
    }

    /**
     * Executar importação após preview confirmado.
     */
    public function importarEdicoesExecutar(Request $request)
    {
        $preview = session()->get('import_preview_data');
        if (! $preview || ! is_file($preview['path'] ?? '')) {
            return back()->withErrors(['arquivo' => 'Sessão de preview expirada. Faça o upload do arquivo novamente.']);
        }

        $path = $preview['path'];
        $ext = $preview['ext'] ?? '';
        $linhas = $this->lerArquivoImportacao($path, $ext);
        $importAnotacoesInternas = $request->user()?->role === 'admin';

        $atualizados = 0;
        $naoEncontrados = [];
        $log = [];

        foreach ($linhas as $i => $row) {
            $linhaNum = $i + 2;
            $codigo = trim((string) ($this->getVal($row, 'codigo') ?? $this->getVal($row, 'codigo_legado') ?? ''));

            if ($codigo === '') {
                continue;
            }

            $produto = Produto::where('codigo', $codigo)->first();
            if (! $produto) {
                $naoEncontrados[] = $codigo;
                $log[] = [
                    'tipo' => 'nao_encontrado',
                    'codigo' => $codigo,
                    'linha' => $linhaNum,
                    'mensagem' => "Código {$codigo} não encontrado na base — verifique se o produto existe.",
                ];

                continue;
            }

            $dados = $this->extractDadosFromRow($row, $importAnotacoesInternas);

            if (! empty($dados)) {
                try {
                    $produto->update($dados);
                    $atualizados++;
                    $log[] = [
                        'tipo' => 'sucesso',
                        'codigo' => $codigo,
                        'linha' => $linhaNum,
                        'mensagem' => "Produto {$codigo} atualizado com sucesso.",
                    ];
                } catch (\Exception $e) {
                    $log[] = [
                        'tipo' => 'erro',
                        'codigo' => $codigo,
                        'linha' => $linhaNum,
                        'mensagem' => "Não foi possível atualizar o produto {$codigo}.",
                    ];
                }
            }
        }

        if (isset($preview['temp_key'])) {
            Storage::delete($preview['temp_key']);
        }
        session()->forget('import_preview_data');

        return back()->with([
            'success' => "Edição em massa concluída: {$atualizados} produto(s) atualizado(s).",
            'import_result' => [
                'atualizados' => $atualizados,
                'nao_encontrados' => $naoEncontrados,
                'log' => $log,
            ],
            'import_preview' => null,
        ]);
    }

    protected function lerArquivoImportacao(string $path, string $ext): array
    {
        $linhas = [];

        if ($ext === 'csv' || $ext === 'txt') {
            $fp = fopen($path, 'r');
            if (! $fp) {
                return [];
            }
            $firstLine = fgets($fp);
            rewind($fp);
            $separador = str_contains($firstLine, ';') ? ';' : ',';
            $headers = null;
            while (($row = fgetcsv($fp, 0, $separador, '"', '')) !== false) {
                if ($headers === null) {
                    $headers = array_map('trim', $row);

                    continue;
                }
                $linha = [];
                foreach ($headers as $j => $h) {
                    $linha[$h] = isset($row[$j]) ? trim((string) $row[$j]) : '';
                }
                $linhas[] = $linha;
            }
            fclose($fp);

            return $linhas;
        }

        // XLSX / XLS
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return [];
            }

            $normalizeCell = fn ($c) => trim((string) ($c ?? ''));
            // Encontrar a linha do cabeçalho (Excel pode ter linha de título antes)
            $headerRowIndex = 0;
            foreach ($rows as $idx => $row) {
                $cellsNorm = array_map($normalizeCell, $row);
                if (in_array('codigo', $cellsNorm) || in_array('codigo_legado', $cellsNorm)) {
                    $headerRowIndex = $idx;
                    break;
                }
            }
            $headers = array_map($normalizeCell, $rows[$headerRowIndex]);

            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $linha = [];
                foreach ($headers as $j => $h) {
                    $val = $rows[$i][$j] ?? null;
                    $linha[$h] = $val === null ? '' : $normalizeCell($val);
                }
                $linhas[] = $linha;
            }

            return $linhas;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erro ao ler arquivo Excel: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Search produtos for autocomplete.
     */
    public function search(Request $request)
    {
        $search = $request->get('q', '');

        $produtos = Produto::ativo()
            ->semLegadoSomenteLeitura()
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
