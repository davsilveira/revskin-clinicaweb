<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Support\LegadoCodigoProdutoMapeamento;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RelatorioItensReceitaSemProduto extends Command
{
    protected $signature = 'migration:relatorio-itens-sem-produto-na-base
                            {--source=docs/migration : Diretório com receitas.json (extração)}
                            {--output= : Caminho CSV (padrão: storage/app/private/migration-backups/relatorio-itens-sem-produto-*.csv)}
                            {--mapeamento-codigos=docs/sanitization/mapeamento-codigos-legado-base.md : Tabela legado → código base}';

    protected $description = 'Lista itens em receitas.json sem produto correspondente na base (mesma lógica da importação). Gera CSV para cadastro no Tiny / mapeamento.';

    public function handle(): int
    {
        $sourceDir = base_path($this->option('source'));
        $jsonPath = rtrim($sourceDir, '/').'/receitas.json';
        if (! is_file($jsonPath)) {
            $this->error("Arquivo não encontrado: {$jsonPath}");
            $this->line('Execute: php artisan migration:extrair-legado');

            return 1;
        }

        $mapPath = base_path($this->option('mapeamento-codigos'));
        $mapeamento = LegadoCodigoProdutoMapeamento::fromMarkdownFile($mapPath);

        $receitas = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($receitas)) {
            $this->error('receitas.json inválido.');

            return 1;
        }

        /** @var Collection<string, Produto> */
        $produtoCache = Produto::query()->get()->keyBy('codigo');

        $rows = [];
        foreach ($receitas as $rec) {
            $legadoReceitaId = $rec['legado_id'] ?? '';
            $numeroLegado = $rec['numero_legado'] ?? '';
            foreach ($rec['itens'] ?? [] as $item) {
                if ($this->resolverProdutoId($item, $produtoCache, $mapeamento)) {
                    continue;
                }
                $legado = trim((string) ($item['codigo_produto_legado'] ?? ''));
                $mapeado = trim((string) ($item['codigo_produto_mapeado'] ?? ''));
                $codigoTentado = $legado !== ''
                    ? LegadoCodigoProdutoMapeamento::paraBase($legado, $mapeamento)
                    : $mapeado;
                $rows[] = [
                    'legado_receita_id' => $legadoReceitaId,
                    'numero_receita_legado' => $numeroLegado,
                    'legado_item_id' => $item['legado_id'] ?? '',
                    'codigo_produto_legado' => $legado,
                    'codigo_produto_mapeado_json' => $mapeado,
                    'codigo_tentado_na_base' => $codigoTentado,
                    'local_uso' => $item['local_uso'] ?? '',
                ];
            }
        }

        $outPath = $this->option('output');
        if (! $outPath) {
            $dir = storage_path('app/private/migration-backups');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $outPath = $dir.'/relatorio-itens-sem-produto-'.date('Y-m-d_His').'.csv';
        } else {
            $outPath = base_path($this->option('output'));
            $dir = dirname($outPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $fp = fopen($outPath, 'w');
        if ($fp === false) {
            $this->error("Não foi possível escrever: {$outPath}");

            return 1;
        }

        fwrite($fp, "\xEF\xBB\xBF");
        $headers = [
            'legado_receita_id',
            'numero_receita_legado',
            'legado_item_id',
            'codigo_produto_legado',
            'codigo_produto_mapeado_json',
            'codigo_tentado_na_base',
            'local_uso',
        ];
        fputcsv($fp, $headers);
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        fclose($fp);

        $this->info('Linhas sem produto na base: '.\count($rows));
        $this->line("CSV: {$outPath}");

        return 0;
    }

    /**
     * @param  array<string, string>  $mapeamento
     */
    private function resolverProdutoId(array $receitaItem, Collection $produtoCache, array $mapeamento): ?int
    {
        $legado = trim((string) ($receitaItem['codigo_produto_legado'] ?? ''));
        $codigoBusca = $legado !== ''
            ? LegadoCodigoProdutoMapeamento::paraBase($legado, $mapeamento)
            : trim((string) ($receitaItem['codigo_produto_mapeado'] ?? ''));

        if ($codigoBusca === '') {
            return null;
        }

        $produto = $produtoCache->get($codigoBusca);
        if (! $produto) {
            $produto = Produto::query()
                ->where('codigo', $codigoBusca)
                ->orWhere('codigo', 'like', $codigoBusca.' %')
                ->first();
            if ($produto) {
                $produtoCache->put($produto->codigo, $produto);
            }
        }

        return $produto?->id;
    }
}
