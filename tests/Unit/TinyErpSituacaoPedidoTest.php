<?php

namespace Tests\Unit;

use App\Services\TinyErpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TinyErpSituacaoPedidoTest extends TestCase
{
    #[DataProvider('faturadasProvider')]
    public function test_is_situacao_pedido_faturada(mixed $situacao, bool $expected): void
    {
        $this->assertSame($expected, TinyErpClient::isSituacaoPedidoFaturada($situacao));
    }

    public static function faturadasProvider(): array
    {
        return [
            'faturado str' => ['faturado', true],
            'Faturado mixed' => ['Faturado', true],
            'entregue' => ['entregue', true],
            'enviado' => ['enviado', true],
            'pronto_envio' => ['pronto_envio', true],
            'pronto-envio' => ['pronto-envio', true],
            'atendido' => ['atendido', true],
            'v3 code 1' => [1, true],
            'v3 code 5' => [5, true],
            'v3 code 6' => [6, true],
            'v3 code 7' => [7, true],
            'aberto' => ['aberto', false],
            'aprovado' => ['aprovado', false],
            'preparando_envio' => ['preparando_envio', false],
            'cancelado' => ['cancelado', false],
            'v3 cancelado' => [2, false],
            'null' => [null, false],
            'empty' => ['', false],
        ];
    }

    public function test_label_situacao_pedido(): void
    {
        $this->assertSame('Faturado', TinyErpClient::labelSituacaoPedido('faturado'));
        $this->assertSame('Entregue', TinyErpClient::labelSituacaoPedido('entregue'));
        $this->assertSame('Faturado', TinyErpClient::labelSituacaoPedido(1));
        $this->assertSame('Entregue', TinyErpClient::labelSituacaoPedido(6));
    }
}
