<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Models\Receita;
use App\Models\ReceitaItem;
use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoProdutoResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RelinkReceitaProdutosLegado extends Command
{
    protected $signature = 'migration:relink-receita-produtos
                            {--source=docs/migration : Diretório com receitas.json}
                            {--fix : Aplica UPDATE em receita_itens.produto_id}';

    protected $description = 'Relinka itens de receita importadas para produtos Tiny usando de-para atualizado';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $sourceDir = base_path($this->option('source'));
        $jsonPath = rtrim($sourceDir, '/').'/receitas.json';
        $mapPath = base_path('docs/sanitization/mapeamento-codigos-legado-base.md');
        $mapeamento = LegadoCodigoProdutoMapeamento::fromMarkdownFile($mapPath);

        if (! is_file($jsonPath)) {
            $this->error("Arquivo não encontrado: {$jsonPath}");

            return 1;
        }

        $receitasJson = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($receitasJson)) {
            $this->error('receitas.json inválido.');

            return 1;
        }

        $jsonByLegadoId = collect($receitasJson)->keyBy('legado_id');

        /** @var Collection<string, Produto> */
        $produtoCache = Produto::query()->get()->keyBy('codigo');

        $relink = 0;
        $semMatch = 0;
        $jaOk = 0;
        $semJson = 0;

        Receita::query()
            ->where('anotacoes', 'like', '%[legado:%')
            ->orderBy('id')
            ->chunkById(50, function ($receitas) use (
                $jsonByLegadoId,
                $mapeamento,
                $produtoCache,
                $fix,
                &$relink,
                &$semMatch,
                &$jaOk,
                &$semJson
            ) {
        foreach ($receitas as $receita) {
            $receita->load(['itens.produto']);
            if (! preg_match('/\[legado:(\d+)\|/', (string) $receita->anotacoes, $m)) {
                continue;
            }
            $legadoId = (int) $m[1];
            $json = $jsonByLegadoId->get($legadoId);
            if (! $json) {
                $semJson++;

                continue;
            }

            $itensPorCodigo = [];
            foreach ($json['itens'] ?? [] as $ji) {
                $cod = trim((string) ($ji['codigo_produto_legado'] ?? ''));
                if ($cod === '' || in_array($cod, ['...', 'W-AMOSTRA'], true)) {
                    continue;
                }
                if (! isset($itensPorCodigo[$cod])) {
                    $itensPorCodigo[$cod] = [];
                }
                $itensPorCodigo[$cod][] = $ji;
            }

            foreach ($receita->itens as $dbItem) {
                $produtoAtual = $dbItem->produto;
                if (! $produtoAtual) {
                    continue;
                }

                if (! $produtoAtual->legado_somente_leitura) {
                    $jaOk++;

                    continue;
                }

                $codigoAtual = $produtoAtual->codigo;
                $candidatosJson = $itensPorCodigo[$codigoAtual] ?? [];

                if ($candidatosJson === []) {
                    foreach ($itensPorCodigo as $cod => $lista) {
                        $base = LegadoCodigoProdutoMapeamento::paraBase($cod, $mapeamento);
                        if ($base === $codigoAtual || $cod === $codigoAtual) {
                            $candidatosJson = $lista;
                            break;
                        }
                    }
                }

                $ji = $candidatosJson[0] ?? null;
                if (! $ji) {
                    $semMatch++;

                    continue;
                }

                $produto = LegadoProdutoResolver::findPorItemLegado($ji, $produtoCache, $mapeamento);
                if (! $produto || $produto->legado_somente_leitura) {
                    $semMatch++;

                    continue;
                }

                if ((int) $dbItem->produto_id === (int) $produto->id) {
                    $jaOk++;

                    continue;
                }

                if ($fix) {
                    ReceitaItem::where('id', $dbItem->id)->update(['produto_id' => $produto->id]);
                }
                $relink++;
            }
        }
            });

        $this->newLine();
        $this->info($fix ? 'Relink aplicado.' : 'Dry-run (use --fix para aplicar).');
        $this->line("Relinkados: {$relink} | Já corretos: {$jaOk} | Sem match: {$semMatch} | Sem JSON: {$semJson}");

        return 0;
    }
}
