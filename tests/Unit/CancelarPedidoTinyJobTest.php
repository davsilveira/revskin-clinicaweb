<?php

namespace Tests\Unit;

use App\Jobs\CancelarPedidoTinyJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use App\Services\TinyErpClient;
use App\Services\TinyPedidoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CancelarPedidoTinyJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Paciente sem CPF agora é sincronizável (o oList aceita contato sem cpf_cnpj);
        // sem isso o observer dispararia o SyncClienteTinyJob inline e bateria na API real.
        Bus::fake();
        Setting::set('tiny_enabled', true);
        Setting::set('tiny_api_version', 'v3');
        Setting::set('tiny_url_base', 'https://api.tiny.com.br/public-api/v3');
        Cache::put('tiny_access_token', 'test-token', now()->addHour());
    }

    #[Test]
    public function tiny_pedido_sync_dispatches_job_when_receita_has_tiny_pedido_id(): void
    {
        Bus::fake();

        $receita = $this->makeReceitaComPedidoTiny('12345');

        TinyPedidoSync::agendarCancelamento($receita);

        Bus::assertDispatched(CancelarPedidoTinyJob::class, function (CancelarPedidoTinyJob $job) use ($receita) {
            return $job->receita->id === $receita->id;
        });
    }

    #[Test]
    public function cancelar_pedido_chama_api_quando_pedido_nao_esta_cancelado(): void
    {
        Http::fake([
            'api.tiny.com.br/public-api/v3/pedidos/12345' => Http::response([
                'id' => 12345,
                'situacao' => 0,
            ], 200),
            'api.tiny.com.br/public-api/v3/pedidos/12345/situacao' => Http::response(null, 204),
        ]);

        $receita = $this->makeReceitaComPedidoTiny('12345');

        (new CancelarPedidoTinyJob($receita))->handle();

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/pedidos/12345/situacao')
                && ($request->data()['situacao'] ?? null) === TinyErpClient::SITUACAO_CANCELADA_V3;
        });
    }

    #[Test]
    public function cancelar_pedido_nao_chama_api_quando_pedido_ja_cancelado(): void
    {
        Http::fake([
            'api.tiny.com.br/public-api/v3/pedidos/99999' => Http::response([
                'id' => 99999,
                'situacao' => TinyErpClient::SITUACAO_CANCELADA_V3,
            ], 200),
        ]);

        $receita = $this->makeReceitaComPedidoTiny('99999');

        (new CancelarPedidoTinyJob($receita))->handle();

        Http::assertSentCount(1);
    }

    #[Test]
    public function is_situacao_pedido_cancelada_reconhece_codigos_e_textos(): void
    {
        $this->assertTrue(TinyErpClient::isSituacaoPedidoCancelada(2));
        $this->assertTrue(TinyErpClient::isSituacaoPedidoCancelada('cancelado'));
        $this->assertTrue(TinyErpClient::isSituacaoPedidoCancelada('Cancelada'));
        $this->assertFalse(TinyErpClient::isSituacaoPedidoCancelada(0));
        $this->assertFalse(TinyErpClient::isSituacaoPedidoCancelada('aprovado'));
    }

    private function makeReceitaComPedidoTiny(string $tinyPedidoId): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. Tiny']);
        $paciente = Paciente::create(['nome' => 'Paciente Tiny', 'medico_id' => $medico->id]);

        return Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'tiny_pedido_id' => $tinyPedidoId,
        ]);
    }
}
