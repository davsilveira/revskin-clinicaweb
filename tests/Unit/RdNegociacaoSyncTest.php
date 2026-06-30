<?php

namespace Tests\Unit;

use App\Jobs\MarcarNegociacaoPerdidaRdJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Receita;
use App\Models\Setting;
use App\Services\RdNegociacaoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RdNegociacaoSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispatches_job_when_rd_enabled_and_configured(): void
    {
        Bus::fake();

        Setting::set('rd_enabled', true);
        Setting::set('rd_cancelamento_field_id', 'field-123');
        Setting::set('rd_cancelamento_field_value', 'Cancelado');

        $receita = $this->makeReceita('deal-abc');

        RdNegociacaoSync::agendarMarcarPerdida($receita);

        Bus::assertDispatched(MarcarNegociacaoPerdidaRdJob::class, function (MarcarNegociacaoPerdidaRdJob $job) use ($receita) {
            return $job->receita->id === $receita->id;
        });
    }

    #[Test]
    public function does_not_dispatch_without_rd_deal_id(): void
    {
        Bus::fake();

        Setting::set('rd_enabled', true);
        Setting::set('rd_cancelamento_field_id', 'field-123');
        Setting::set('rd_cancelamento_field_value', 'Cancelado');

        $receita = $this->makeReceita(null);

        RdNegociacaoSync::agendarMarcarPerdida($receita);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function does_not_dispatch_when_cancelamento_field_not_configured(): void
    {
        Bus::fake();

        Setting::set('rd_enabled', true);

        $receita = $this->makeReceita('deal-abc');

        RdNegociacaoSync::agendarMarcarPerdida($receita);

        Bus::assertNothingDispatched();
    }

    private function makeReceita(?string $rdDealId): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. RD']);
        $paciente = Paciente::create(['nome' => 'Paciente RD', 'medico_id' => $medico->id]);

        return Receita::create([
            'numero' => '1-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'rd_deal_id' => $rdDealId,
        ]);
    }
}
