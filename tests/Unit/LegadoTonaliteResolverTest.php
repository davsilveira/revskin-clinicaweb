<?php

namespace Tests\Unit;

use App\Support\LegadoProdutoConvencoesCodigo;
use App\Support\LegadoTonaliteResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegadoTonaliteResolverTest extends TestCase
{
    #[Test]
    public function detecta_placeholders_tonalite(): void
    {
        $this->assertTrue(LegadoTonaliteResolver::isPlaceholder('TONALITE-___-G30'));
        $this->assertTrue(LegadoTonaliteResolver::isPlaceholder('TONALITE-__-G50'));
        $this->assertTrue(LegadoTonaliteResolver::isPlaceholder('TON___-G30'));
        $this->assertFalse(LegadoTonaliteResolver::isPlaceholder('TONALITE-3-G30'));
    }

    #[Test]
    public function expande_placeholder_com_fototipo(): void
    {
        $resolvido = LegadoTonaliteResolver::resolverCodigo('TONALITE-___-G30', '3');

        $this->assertSame('TONALITE-3-G30', $resolvido);
        $this->assertContains('TONALITE 3 30G', LegadoProdutoConvencoesCodigo::variantes($resolvido));
    }

    #[Test]
    public function placeholder_sem_fototipo_nao_expande(): void
    {
        $this->assertNull(LegadoTonaliteResolver::resolverCodigo('TONALITE-___-G30', null));
        $this->assertNull(LegadoTonaliteResolver::resolverCodigo('TONALITE-___-G30', ''));
    }

    #[Test]
    public function fototipo_cinco_e_seis_mapeiam_para_quatro_virgula_cinco(): void
    {
        $this->assertSame('TONALITE-4,5-G30', LegadoTonaliteResolver::resolverCodigo('TONALITE-___-G30', '5'));
        $this->assertSame('TONALITE-4,5-G30', LegadoTonaliteResolver::resolverCodigo('TONALITE-___-G30', '6'));
    }

    #[Test]
    public function expande_codigo_cq_ton_tres_underlines(): void
    {
        $this->assertSame('TONALITE-2,5-G30', LegadoTonaliteResolver::resolverCodigo('TON___-G30', '2.5'));
    }
}
