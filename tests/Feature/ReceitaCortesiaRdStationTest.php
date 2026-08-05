<?php

namespace Tests\Feature;

use App\Jobs\CriarNegociacaoRdStationJob;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReceitaCortesiaRdStationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Cache::put('rd_access_token', 'test-token', now()->addHour());
        Setting::set('rd_enabled', true);
        Setting::set('rd_owner_id', 'owner-1');
        Setting::set('rd_stage_id', 'stage-1');
        Setting::set('rd_medico_field_id', 'field-medico');
        Setting::set('rd_receita_field_id', 'field-receita');
        Setting::set('rd_cortesia_field_id', '6a721f71257c0d0020d8178e');
        Setting::set('rd_produto_padrao_id', null);
    }

    #[Test]
    public function admin_can_persist_cortesia_on_receita(): void
    {
        $admin = User::create([
            'name' => 'Admin Cortesia',
            'email' => 'admin-cortesia@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $medico = Medico::create(['nome' => 'Dr. Cortesia']);
        $paciente = Paciente::create(['nome' => 'Paciente Cortesia', 'medico_id' => $medico->id]);
        $produto = Produto::create([
            'nome' => 'Produto Cortesia',
            'codigo' => 'PCORT-1',
            'ativo' => true,
            'preco' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('receitas.store'), [
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
                'data_receita' => now()->toDateString(),
                'cortesia' => true,
                'itens' => [
                    [
                        'produto_id' => $produto->id,
                        'quantidade' => 1,
                        'valor_unitario' => 10,
                        'imprimir' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $receita = Receita::query()->latest('id')->first();
        $this->assertNotNull($receita);
        $this->assertTrue($receita->cortesia);
    }

    #[Test]
    public function medico_cannot_set_cortesia_on_create(): void
    {
        $medico = Medico::create(['nome' => 'Dr. Sem Cortesia']);
        $medicoUser = User::create([
            'name' => 'Medico Cortesia',
            'email' => 'medico-cortesia@example.com',
            'password' => Hash::make('password'),
            'role' => 'medico',
            'medico_id' => $medico->id,
            'is_active' => true,
        ]);
        $paciente = Paciente::create(['nome' => 'Paciente Medico', 'medico_id' => $medico->id]);
        $produto = Produto::create([
            'nome' => 'Produto Medico',
            'codigo' => 'PMED-1',
            'ativo' => true,
            'preco' => 10,
        ]);

        $this->actingAs($medicoUser)
            ->post(route('receitas.store'), [
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
                'data_receita' => now()->toDateString(),
                'cortesia' => true,
                'itens' => [
                    [
                        'produto_id' => $produto->id,
                        'quantidade' => 1,
                        'valor_unitario' => 10,
                        'imprimir' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $receita = Receita::query()->latest('id')->first();
        $this->assertNotNull($receita);
        $this->assertFalse($receita->cortesia);
    }

    #[Test]
    public function rd_job_sends_sim_when_cortesia_is_true(): void
    {
        $this->fakeRdApi();

        $receita = $this->makeReceita(cortesia: true);

        (new CriarNegociacaoRdStationJob($receita))->handle();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/deals')) {
                return false;
            }

            $fields = $request->data()['data']['custom_fields'] ?? [];

            return ($fields['cortesia_crm'] ?? null) === 'Sim';
        });
    }

    #[Test]
    public function rd_job_sends_empty_when_cortesia_is_false(): void
    {
        $this->fakeRdApi();

        $receita = $this->makeReceita(cortesia: false);

        (new CriarNegociacaoRdStationJob($receita))->handle();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/deals')) {
                return false;
            }

            $fields = $request->data()['data']['custom_fields'] ?? [];

            return array_key_exists('cortesia_crm', $fields) && $fields['cortesia_crm'] === '';
        });
    }

    private function makeReceita(bool $cortesia): Receita
    {
        $medico = Medico::create(['nome' => 'Dr. RD Cortesia']);
        $paciente = Paciente::create([
            'nome' => 'Paciente RD Cortesia',
            'medico_id' => $medico->id,
            'rd_organization_id' => 'org-1',
        ]);

        return Receita::create([
            'numero' => '9-0001',
            'data_receita' => now()->toDateString(),
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'status' => 'finalizada',
            'valor_total' => 100,
            'cortesia' => $cortesia,
        ]);
    }

    private function fakeRdApi(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'GET' && str_contains($url, '/organizations/org-1')) {
                return Http::response(['data' => ['id' => 'org-1', 'name' => 'Paciente RD Cortesia']], 200);
            }

            if ($method === 'GET' && str_contains($url, '/contacts')) {
                return Http::response(['data' => []], 200);
            }

            if ($method === 'POST' && str_contains($url, '/contacts')) {
                return Http::response(['data' => ['id' => 'contact-1', 'name' => 'Paciente RD Cortesia']], 200);
            }

            if ($method === 'GET' && str_contains($url, '/custom_fields/field-medico')) {
                return Http::response(['data' => ['api_identifier' => 'medico_crm']], 200);
            }

            if ($method === 'GET' && str_contains($url, '/custom_fields/field-receita')) {
                return Http::response(['data' => ['api_identifier' => 'receita_crm']], 200);
            }

            if ($method === 'GET' && str_contains($url, '/custom_fields/6a721f71257c0d0020d8178e')) {
                return Http::response(['data' => ['api_identifier' => 'cortesia_crm']], 200);
            }

            if ($method === 'POST' && preg_match('#/deals$#', parse_url($url, PHP_URL_PATH) ?: '')) {
                return Http::response(['data' => ['id' => 'deal-cortesia-1']], 200);
            }

            return Http::response(['data' => []], 200);
        });
    }
}
