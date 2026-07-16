<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Models\TabelaKarnaughProduto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia os códigos de produto gravados nas tabelas Karnaugh
 * (`tabela_karnaugh_produtos.produto_codigo`) com os códigos atuais do catálogo
 * (produtos importados do oList/Tiny).
 *
 * Após a recodificação de todos os produtos, o Karnaugh ficou com os códigos no
 * formato antigo (separador hífen, ex.: DEMERANE-30G) enquanto o catálogo passou
 * a usar espaço (ex.: DEMERANE 30G). O Assistente de Receitas casa por código
 * exato e, por isso, deixou de encontrar a maioria dos produtos.
 *
 * Este comando reescreve `produto_codigo` para o código canônico do catálogo
 * quando há correspondência única por normalização (hífen ↔ espaço) ou por um
 * mapa manual de exceções. É seguro por padrão: roda em dry-run e só grava com
 * --force. Não toca no template TONALITE (`__`), resolvido em runtime pelo
 * fototipo — esse caso é coberto pela normalização do motor de regras.
 */
class NormalizarCodigosKarnaugh extends Command
{
    protected $signature = 'karnaugh:normalizar-codigos-produto
                            {--tabela= : Limita a uma tabela Karnaugh (ID). Padrão: todas.}
                            {--force : Aplica de fato. Sem esta flag, apenas simula (dry-run).}';

    protected $description = 'Reconcilia tabela_karnaugh_produtos.produto_codigo com os códigos atuais do catálogo (corrige separador hífen x espaço e casos manuais).';

    /**
     * Mapa manual para códigos que não resolvem por normalização.
     * (codigo_karnaugh => codigo_catalogo)
     */
    private array $mapaManual = [
        'AQUELANE-8-30G' => 'AQUELANE 8',
        'REVELUMIE-G15' => 'REVELUMIE 15G',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $tabela = $this->option('tabela') !== null ? (int) $this->option('tabela') : null;

        if (! $force) {
            $this->warn('MODO SIMULAÇÃO (dry-run). Nada será alterado. Use --force para aplicar.');
            $this->newLine();
        }

        // Índice de produtos por código normalizado + set de códigos existentes.
        $indice = [];
        $existentes = [];
        Produto::whereNotNull('codigo')->where('codigo', '!=', '')
            ->get(['id', 'codigo'])
            ->each(function (Produto $p) use (&$indice, &$existentes) {
                $existentes[$p->codigo] = true;
                $indice[$this->normalizar($p->codigo)][$p->codigo] = true;
            });

        // Códigos distintos gravados no Karnaugh (respeitando o filtro de tabela).
        $base = TabelaKarnaughProduto::query()
            ->whereNotNull('produto_codigo')->where('produto_codigo', '!=', '');
        if ($tabela !== null) {
            $base->where('tabela_karnaugh_id', $tabela);
        }
        $distintos = (clone $base)->distinct()->pluck('produto_codigo');

        $plano = [];      // codigo_antigo => codigo_novo
        $ambiguos = [];   // codigo => [candidatos]
        $semMatch = [];   // codigo
        $stats = ['exato' => 0, 'normalizado' => 0, 'manual' => 0, 'template' => 0, 'ambiguo' => 0, 'sem_match' => 0];

        foreach ($distintos as $codigo) {
            $codigo = (string) $codigo;

            if (isset($existentes[$codigo])) {
                $stats['exato']++;
                continue;
            }

            // Template TONALITE (placeholder de tom): resolvido em runtime, não tocar.
            // O curinga vem como "__"/"___" (hífen) ou "***" (espaço), conforme a tabela.
            if (preg_match('/\*{2,}|_{2,}/', $codigo)) {
                $stats['template']++;
                continue;
            }

            if (isset($this->mapaManual[$codigo]) && isset($existentes[$this->mapaManual[$codigo]])) {
                $plano[$codigo] = $this->mapaManual[$codigo];
                $stats['manual']++;
                continue;
            }

            $cands = array_keys($indice[$this->normalizar($codigo)] ?? []);
            if (count($cands) === 1) {
                $plano[$codigo] = $cands[0];
                $stats['normalizado']++;
            } elseif (count($cands) > 1) {
                $stats['ambiguo']++;
                $ambiguos[$codigo] = $cands;
            } else {
                $stats['sem_match']++;
                $semMatch[] = $codigo;
            }
        }

        // Relatório
        $this->info('=== Reconciliação de códigos Karnaugh x Catálogo ===');
        $this->table(['Situação', 'Códigos distintos'], [
            ['Já exato (mantido)', $stats['exato']],
            ['Corrigido por normalização', $stats['normalizado']],
            ['Corrigido por mapa manual', $stats['manual']],
            ['Template TONALITE (ignorado)', $stats['template']],
            ['Ambíguo (revisar)', $stats['ambiguo']],
            ['Sem match (revisar)', $stats['sem_match']],
        ]);

        if (! empty($plano)) {
            $this->newLine();
            $this->line('Alterações propostas (codigo_antigo -> codigo_catalogo | linhas afetadas):');
            foreach ($plano as $old => $new) {
                $q = TabelaKarnaughProduto::where('produto_codigo', $old);
                if ($tabela !== null) {
                    $q->where('tabela_karnaugh_id', $tabela);
                }
                $this->line(sprintf('  %-30s -> %-24s (%d)', $old, $new, $q->count()));
            }
        }

        if (! empty($ambiguos)) {
            $this->newLine();
            $this->warn('AMBÍGUOS (não alterados — revisar manualmente):');
            foreach ($ambiguos as $c => $cands) {
                $this->line("  {$c} => [".implode(' | ', $cands).']');
            }
        }

        if (! empty($semMatch)) {
            $this->newLine();
            $this->warn('SEM MATCH (não alterados — revisar manualmente):');
            foreach ($semMatch as $c) {
                $this->line("  {$c}");
            }
        }

        if (empty($plano)) {
            $this->newLine();
            $this->info('Nada a atualizar.');
            return self::SUCCESS;
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Simulação concluída. Rode novamente com --force para aplicar.');
            return self::SUCCESS;
        }

        $totalLinhas = 0;
        DB::transaction(function () use ($plano, $tabela, &$totalLinhas) {
            foreach ($plano as $old => $new) {
                $q = TabelaKarnaughProduto::where('produto_codigo', $old);
                if ($tabela !== null) {
                    $q->where('tabela_karnaugh_id', $tabela);
                }
                $totalLinhas += $q->update(['produto_codigo' => $new]);
            }
        });

        $this->newLine();
        $this->info("Aplicado: {$totalLinhas} linha(s) atualizada(s) em ".count($plano).' código(s) distinto(s).');

        return self::SUCCESS;
    }

    /**
     * Normaliza um código: maiúsculas e sequências de espaço/hífen viram um único espaço.
     */
    private function normalizar(string $valor): string
    {
        return trim(preg_replace('/[\s\-]+/', ' ', mb_strtoupper(trim($valor))));
    }
}
