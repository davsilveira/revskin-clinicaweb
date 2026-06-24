<?php

namespace Tests\Unit;

use App\Support\LegadoProdutoResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegadoProdutoResolverTest extends TestCase
{
    #[Test]
    public function variantes_codigo_espaco_tonalite_inclui_catalogo(): void
    {
        $vars = LegadoProdutoResolver::variantesCodigo('TONALITE 3 G30');

        $this->assertContains('TONALITE 3 30G', $vars);
    }

    #[Test]
    public function variantes_codigo_espaco_tonalite_meio_tom_inclui_catalogo(): void
    {
        $vars = LegadoProdutoResolver::variantesCodigo('TONALITE 2,5 G30');

        $this->assertContains('TONALITE 2,5 30G', $vars);
    }

    #[Test]
    public function variantes_codigo_hifen_tonalite_continua_funcionando(): void
    {
        $vars = LegadoProdutoResolver::variantesCodigo('TONALITE-3-G30');

        $this->assertContains('TONALITE 3 30G', $vars);
    }
}
