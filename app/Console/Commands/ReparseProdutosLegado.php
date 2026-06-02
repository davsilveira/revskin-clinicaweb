<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Support\LegadoProdutoDescricaoParser;
use Illuminate\Console\Command;

class ReparseProdutosLegado extends Command
{
    protected $signature = 'migration:reparse-produtos-legado
                            {--source=docs/migration : Diretório com produtos.json}
                            {--fix : Aplica correções no banco}';

    protected $description = 'Re-parseia descricao legado em produtos legado_somente_leitura com nome blob';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $jsonPath = base_path(rtrim($this->option('source'), '/').'/produtos.json');

        if (! is_file($jsonPath)) {
            $this->error("Arquivo não encontrado: {$jsonPath}");

            return 1;
        }

        $produtosJson = json_decode((string) file_get_contents($jsonPath), true);
        if (! is_array($produtosJson)) {
            $this->error('produtos.json inválido.');

            return 1;
        }

        $descByCodigo = [];
        foreach ($produtosJson as $p) {
            $codigo = trim((string) ($p['codigo'] ?? ''));
            if ($codigo !== '') {
                $descByCodigo[$codigo] = (string) ($p['descricao_legado'] ?? '');
            }
        }

        $candidatos = Produto::query()
            ->where('legado_somente_leitura', true)
            ->get();

        $corrigidos = 0;
        foreach ($candidatos as $produto) {
            $desc = $descByCodigo[$produto->codigo] ?? null;
            if ($desc === null || $desc === '') {
                if (! str_contains($produto->nome, "\n") && ! preg_match('/\buso\s*:/iu', $produto->nome)) {
                    continue;
                }
                $desc = $produto->nome;
            }

            $parsed = LegadoProdutoDescricaoParser::parse($desc);
            if ($parsed['nome'] === '' && $parsed['formula'] === '') {
                continue;
            }

            $mudou = $produto->nome !== $parsed['nome']
                || (string) $produto->descricao !== (string) $parsed['formula']
                || (string) $produto->modo_uso !== (string) $parsed['modo_uso'];

            if (! $mudou) {
                continue;
            }

            $this->line("  {$produto->codigo}: nome → ".mb_substr($parsed['nome'], 0, 60).'...');

            if ($fix) {
                $produto->nome = $parsed['nome'] !== '' ? $parsed['nome'] : $produto->nome;
                $produto->descricao = $parsed['formula'] !== '' ? $parsed['formula'] : $produto->descricao;
                $produto->modo_uso = $parsed['modo_uso'] !== '' ? $parsed['modo_uso'] : $produto->modo_uso;
                $produto->saveQuietly();
            }
            $corrigidos++;
        }

        $this->info(($fix ? 'Corrigidos' : 'Seriam corrigidos').": {$corrigidos}");

        return 0;
    }
}
