<?php

namespace Tests\Unit;

use App\Support\LegadoProdutoConvencoesCodigo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegadoProdutoConvencoesCodigoTest extends TestCase
{
    #[Test]
    public function tonalite_legado_vira_codigo_catalogo(): void
    {
        $vars = LegadoProdutoConvencoesCodigo::variantes('TONALITE-2,5-G30');

        $this->assertContains('TONALITE 2,5 30G', $vars);
    }

    #[Test]
    public function dynamisee_e_demerane_convertem(): void
    {
        $this->assertContains('DYNAMISEE 1', LegadoProdutoConvencoesCodigo::variantes('HYDRAMINCE-DYNAMISEE-1'));
        $this->assertContains('DEMERANE 50G', LegadoProdutoConvencoesCodigo::variantes('DEMERANE-ULTRA-G50'));
    }

    #[Test]
    public function placeholder_tonalite_nao_gera_variante(): void
    {
        $this->assertSame([], LegadoProdutoConvencoesCodigo::variantes('TONALITE-___-G30'));
    }
}
