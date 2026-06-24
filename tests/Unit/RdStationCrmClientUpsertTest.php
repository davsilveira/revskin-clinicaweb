<?php

namespace Tests\Unit;

use App\Services\RdStationCrmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RdStationCrmClientUpsertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('rd_access_token', 'test-token', now()->addHour());
    }

    #[Test]
    public function upsert_organizacao_reuses_exact_match_not_only_first_partial_result(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => Http::sequence()
                ->push([
                    'data' => [
                        ['id' => 'partial-id', 'name' => 'Maria Silva Santos'],
                        ['id' => 'exact-id', 'name' => 'Maria Silva'],
                    ],
                ], 200)
                ->whenEmpty(Http::response(['detail' => 'unexpected'], 500)),
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Maria Silva', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found', $result['action']);
        $this->assertSame('exact-id', $result['data']['id'] ?? null);
    }

    #[Test]
    public function upsert_organizacao_recovers_when_create_returns_duplicate_name_error(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['detail' => 'Nome Empresa já cadastrada.'], 400);
                }

                static $getCalls = 0;
                $getCalls++;
                if ($getCalls === 1) {
                    return Http::response(['data' => []], 200);
                }

                return Http::response([
                    'data' => [
                        ['id' => 'org-existing', 'name' => 'João Souza'],
                    ],
                ], 200);
            },
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('João Souza', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found_after_duplicate', $result['action']);
        $this->assertSame('org-existing', $result['data']['id'] ?? null);
    }

    #[Test]
    public function upsert_organizacao_returns_error_when_create_fails_for_other_reason(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['detail' => 'Owner inválido.'], 400);
                }

                return Http::response(['data' => []], 200);
            },
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Cliente Teste', null, 'owner-1');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Owner inválido', (string) ($result['message'] ?? ''));
    }
}
