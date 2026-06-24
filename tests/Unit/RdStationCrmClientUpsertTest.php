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
    public function upsert_organizacao_finds_org_with_different_casing_before_post(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => Http::response([
                'data' => [
                    ['id' => 'case-id', 'name' => 'MARIA silva'],
                ],
            ], 200),
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Maria Silva', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found', $result['action']);
        $this->assertSame('case-id', $result['data']['id'] ?? null);
        Http::assertSentCount(1);
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
                if ($getCalls <= 4) {
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
    public function upsert_organizacao_recovers_when_create_returns_duplicate_name_as_json_message(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['detail' => 'Nome Empresa já cadastrada.'], 400);
                }

                static $getCalls = 0;
                $getCalls++;
                if ($getCalls <= 4) {
                    return Http::response(['data' => []], 200);
                }

                return Http::response([
                    'data' => [
                        ['id' => 'org-json-dup', 'name' => 'Ana Costa'],
                    ],
                ], 200);
            },
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Ana Costa', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found_after_duplicate', $result['action']);
        $this->assertSame('org-json-dup', $result['data']['id'] ?? null);
    }

    #[Test]
    public function upsert_organizacao_finds_org_on_second_page_via_links_next(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response(['detail' => 'POST inesperado'], 500);
                }

                static $getCalls = 0;
                $getCalls++;

                if ($getCalls === 1) {
                    return Http::response([
                        'data' => [],
                        'links' => [
                            'next' => 'https://api.rd.services/crm/v2/organizations?filter=name%3A~%22Pedro+Lima%22&page%5Bnumber%5D=2&page%5Bsize%5D=25',
                        ],
                    ], 200);
                }

                if ($getCalls === 2) {
                    return Http::response([
                        'data' => [
                            ['id' => 'page-two-id', 'name' => 'Pedro Lima'],
                        ],
                        'links' => [],
                    ], 200);
                }

                return Http::response(['data' => []], 200);
            },
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Pedro Lima', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found', $result['action']);
        $this->assertSame('page-two-id', $result['data']['id'] ?? null);
    }

    #[Test]
    public function upsert_organizacao_reuses_existing_id_even_when_patient_name_changed(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations/org-saved*' => Http::response([
                'data' => ['id' => 'org-saved', 'name' => 'Nome Antigo'],
            ], 200),
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('Nome Novo', 'org-saved', 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('reused', $result['action']);
        $this->assertSame('org-saved', $result['data']['id'] ?? null);
        Http::assertSentCount(1);
    }

    #[Test]
    public function upsert_organizacao_finds_name_with_spaces_and_accents_via_match_filter(): void
    {
        Http::fake([
            'api.rd.services/crm/v2/organizations*' => Http::response([
                'data' => [
                    ['id' => 'accent-id', 'name' => 'José  da   Silva'],
                ],
            ], 200),
        ]);

        $client = new RdStationCrmClient;
        $result = $client->upsertOrganizacao('José da Silva', null, 'owner-1');

        $this->assertSame('success', $result['status']);
        $this->assertSame('found', $result['action']);
        $this->assertSame('accent-id', $result['data']['id'] ?? null);
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
