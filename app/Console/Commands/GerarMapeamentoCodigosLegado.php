<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoProdutoDescricaoParser;
use App\Support\LegadoProdutoResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GerarMapeamentoCodigosLegado extends Command
{
    protected $signature = 'migration:gerar-mapeamento-codigos
                            {--source=docs/migration : Diretório com receitas.json}
                            {--min-score=85 : Score mínimo similar_text para match por nome}
                            {--apply : Adiciona novos mapeamentos ao markdown (sem duplicar)}';

    protected $description = 'Gera draft CSV de mapeamento legado→Tiny e opcionalmente aplica ao markdown';

    public function handle(): int
    {
        $sourceDir = base_path($this->option('source'));
        $jsonPath = rtrim($sourceDir, '/').'/receitas.json';
        $mapPath = LegadoCodigoProdutoMapeamento::defaultFilePath();
        $existing = LegadoCodigoProdutoMapeamento::fromMarkdownFile($mapPath);
        $minScore = (int) $this->option('min-score');

        if (! is_file($jsonPath)) {
            $this->error("Arquivo não encontrado: {$jsonPath}");

            return 1;
        }

        $receitas = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($receitas)) {
            $this->error('receitas.json inválido.');

            return 1;
        }

        $codigosLegado = [];
        foreach ($receitas as $rec) {
            foreach ($rec['itens'] ?? [] as $item) {
                $legado = trim((string) ($item['codigo_produto_legado'] ?? ''));
                if ($legado === '' || in_array($legado, ['...', 'W-AMOSTRA'], true)) {
                    continue;
                }
                if (! isset($codigosLegado[$legado])) {
                    $codigosLegado[$legado] = $item['descricao_produto_legado'] ?? '';
                }
            }
        }

        /** @var Collection<string, Produto> */
        $produtoCache = Produto::query()
            ->where('legado_somente_leitura', false)
            ->get()
            ->keyBy('codigo');

        $sugestoes = [];
        foreach ($codigosLegado as $legado => $descricao) {
            $base = LegadoCodigoProdutoMapeamento::paraBase($legado, $existing);
            if (LegadoProdutoResolver::findPorCodigo($base, $produtoCache)) {
                continue;
            }

            $item = [
                'codigo_produto_legado' => $legado,
                'codigo_produto_mapeado' => $base,
                'descricao_produto_legado' => $descricao,
            ];

            $produto = LegadoProdutoResolver::findPorItemLegado($item, $produtoCache, $existing);
            if (! $produto) {
                continue;
            }

            if ($legado === $produto->codigo || isset($existing[$legado])) {
                continue;
            }

            $parsed = is_string($descricao) && $descricao !== ''
                ? LegadoProdutoDescricaoParser::parse($descricao)
                : ['nome' => ''];
            $score = 0;
            if ($parsed['nome'] !== '') {
                similar_text(
                    LegadoProdutoResolver::normalizarNome($parsed['nome']),
                    LegadoProdutoResolver::normalizarNome($produto->nome),
                    $score
                );
            }

            $sugestoes[] = [
                'legado' => $legado,
                'tiny' => $produto->codigo,
                'match_type' => $score >= $minScore ? 'nome' : 'codigo_variante',
                'score' => (int) $score,
                'nome_legado' => $parsed['nome'] ?? '',
                'nome_tiny' => $produto->nome,
            ];
        }

        $draftPath = base_path('docs/sanitization/mapeamento-codigos-legado-base-DRAFT.csv');
        $fp = fopen($draftPath, 'w');
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['codigo_legado', 'codigo_tiny', 'match_type', 'score', 'nome_legado', 'nome_tiny'], ';');
        foreach ($sugestoes as $s) {
            fputcsv($fp, [$s['legado'], $s['tiny'], $s['match_type'], $s['score'], $s['nome_legado'], $s['nome_tiny']], ';');
        }
        fclose($fp);

        $this->info('Sugestões novas: '.\count($sugestoes));
        $this->line("CSV: {$draftPath}");

        if ($this->option('apply') && $sugestoes !== []) {
            $this->aplicarAoMarkdown($mapPath, $sugestoes, $existing);
        }

        return 0;
    }

    /**
     * @param  array<int, array{legado: string, tiny: string}>  $sugestoes
     * @param  array<string, string>  $existing
     */
    private function aplicarAoMarkdown(string $mapPath, array $sugestoes, array $existing): void
    {
        $content = (string) file_get_contents($mapPath);
        $insert = '';
        $novas = 0;
        foreach ($sugestoes as $s) {
            if (isset($existing[$s['legado']])) {
                continue;
            }
            $insert .= '| '.$s['legado'].' | '.$s['tiny'].' |'."\n";
            $existing[$s['legado']] = $s['tiny'];
            $novas++;
        }
        if ($insert !== '' && str_contains($content, '## Uso na Migração')) {
            $content = str_replace("## Uso na Migração", $insert."\n## Uso na Migração", $content);
        } else {
            $content .= "\n".$insert;
        }
        file_put_contents($mapPath, $content);
        $this->info("Adicionadas {$novas} linhas em {$mapPath}");
    }
}
