<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Models\TabelaKarnaughProduto;
use Illuminate\Console\Command;

class ExportarProdutosKarnaughCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karnaugh:exportar-produtos-csv
                            {--output= : Caminho do arquivo CSV de saída (padrão: storage/app/produtos_karnaugh_unicos.csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exporta a lista de produtos únicos contidos nas tabelas Karnaugh para um arquivo CSV';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputPath = $this->option('output') ?? storage_path('app/produtos_karnaugh_unicos.csv');

        $this->info('Buscando produtos únicos nas tabelas Karnaugh...');

        // Produtos únicos por produto_codigo (códigos podem ter padrões como TONALITE-__-__)
        $produtosUnicos = TabelaKarnaughProduto::query()
            ->select('produto_codigo', 'categoria')
            ->distinct()
            ->get()
            ->groupBy('produto_codigo')
            ->map(function ($grupo) {
                $primeiro = $grupo->first();
                return [
                    'produto_codigo' => $primeiro->produto_codigo,
                    'categorias' => $grupo->pluck('categoria')->unique()->sort()->values()->implode(', '),
                ];
            })
            ->values();

        // Tentar obter nome do produto quando o código corresponde exatamente
        $codigosExatos = $produtosUnicos->pluck('produto_codigo')->filter(function ($codigo) {
            return !str_contains($codigo, '__');
        })->values();

        $produtosPorCodigo = Produto::whereIn('codigo', $codigosExatos)
            ->get()
            ->keyBy('codigo');

        $linhas = [];
        $linhas[] = ['produto_codigo', 'produto_nome', 'categorias_utilizadas'];

        foreach ($produtosUnicos->sortBy('produto_codigo') as $item) {
            $produto = $produtosPorCodigo->get($item['produto_codigo']);
            $linhas[] = [
                $item['produto_codigo'],
                $produto ? $produto->nome : '',
                $item['categorias'],
            ];
        }

        $diretorio = dirname($outputPath);
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }

        $fp = fopen($outputPath, 'w');
        if ($fp === false) {
            $this->error("Não foi possível criar o arquivo: {$outputPath}");
            return 1;
        }

        // BOM UTF-8 para Excel abrir corretamente
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($linhas as $linha) {
            fputcsv($fp, $linha, ';');
        }

        fclose($fp);

        $total = count($linhas) - 1;
        $this->info("Exportação concluída! {$total} produtos únicos exportados para: {$outputPath}");

        return 0;
    }
}
