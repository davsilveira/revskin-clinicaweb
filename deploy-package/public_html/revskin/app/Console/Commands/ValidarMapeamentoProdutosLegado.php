<?php

namespace App\Console\Commands;

use App\Models\Produto;
use App\Support\LegadoCodigoProdutoMapeamento;
use App\Support\LegadoProdutoResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ValidarMapeamentoProdutosLegado extends Command
{
    protected $signature = 'produtos:validar-mapeamento-legado';

    protected $description = 'Valida se cada produto legado_somente_leitura resolve para um SKU ativo (mapa + convenções)';

    public function handle(): int
    {
        $mapeamento = LegadoCodigoProdutoMapeamento::carregar();
        $this->line('Mapa: '.LegadoCodigoProdutoMapeamento::defaultFilePath().' ('.count($mapeamento).' entradas)');

        /** @var Collection<string, Produto> */
        $produtoCache = Produto::query()
            ->where('legado_somente_leitura', false)
            ->get()
            ->keyBy('codigo');

        $legados = Produto::query()
            ->where('legado_somente_leitura', true)
            ->orderBy('codigo')
            ->get();

        $ok = 0;
        $sem = [];

        foreach ($legados as $legado) {
            $item = [
                'codigo_produto_legado' => $legado->codigo,
                'codigo_produto_mapeado' => '',
                'descricao_produto_legado' => $legado->nome,
            ];

            $ativo = LegadoProdutoResolver::findPorItemLegado($item, $produtoCache, $mapeamento);
            if ($ativo) {
                $ok++;
                $this->line("  OK  {$legado->codigo} → {$ativo->codigo}");
            } else {
                $sem[] = $legado->codigo;
                $this->warn("  —   {$legado->codigo}");
            }
        }

        $this->newLine();
        $this->info("Resolvíveis: {$ok}/{$legados->count()}");
        if ($sem !== []) {
            $this->warn('Sem match automático ('.count($sem).'): '.implode(', ', $sem));
        }

        return $sem === [] ? self::SUCCESS : self::FAILURE;
    }
}
