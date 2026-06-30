<?php

namespace Tests\Feature;

use App\Jobs\MarcarNegociacaoPerdidaRdJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarcarNegociacaoPerdidaRdJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('rd_access_token', 'test-token', now()->addHour());
        Setting::set('rd_enabled', true);
        Setting::set('rd_cancelamento_field_id', 'field-cancel-id');
        Setting::set('rd_cancelamento_field_value', 'Sim');
    }

    #[Test]
    public function updates_deal_custom_field_on_cancel(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/custom_fields/field-cancel-id' => Http::response([
                'data' => ['api_identifier' => 'cancelamento_clinicaweb'],
            ], 200),
            'api.rd.services/crm/v2/deals/deal-999' => Http::response(['data' => ['id' => 'deal-999']], 200),
        ]);

        $receita = $this->makeReceita('deal-999');

        (new MarcarNegociacaoPerdidaRdJob($receita))->handle();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT' || ! str_contains($request->url(), '/deals/deal-999')) {
                return false;
            }

            $body = $request->data();

            return ($body['data']['custom_fields']['cancelamento_clinicaweb'] ?? null) === 'Sim';
        });
    }

    #[Test]
    public function skips_when_receita_has_no_rd_deal_id(): void
    {
        Http::fake();

        $receita = $this->makeReceita(null);

        (new MarcarNegociacaoPerdidaRdJob($receita))->handle();

        Http::assertNothingSent();
    }

    private function makeReceita(?string $rdDealId): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. RD']);
        $paciente = Paciente::create(['nome' => 'Paciente RD', 'medico_id' => $medico->id]);

        return Receita::create([
            'numero' => '1-0002',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'cancelada',
            'rd_deal_id' => $rdDealId,
        ]);
    }
}
