<?php

namespace Tests\Unit;

use App\Services\RdWebhookAuditLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RdWebhookAuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function records_and_returns_entries_newest_first(): void
    {
        RdWebhookAuditLog::record(['outcome' => 'dispatched', 'deal_id' => 'deal-1']);
        RdWebhookAuditLog::record(['outcome' => 'rejected_auth', 'deal_id' => 'deal-2']);

        $items = RdWebhookAuditLog::all();

        $this->assertCount(2, $items);
        $this->assertSame('deal-2', $items[0]['deal_id']);
        $this->assertSame('deal-1', $items[1]['deal_id']);
        $this->assertNotNull(RdWebhookAuditLog::lastReceivedAt());
    }

    #[Test]
    public function keeps_at_most_thirty_entries(): void
    {
        for ($i = 0; $i < 35; $i++) {
            RdWebhookAuditLog::record(['outcome' => 'dispatched', 'index' => $i]);
        }

        $this->assertCount(30, RdWebhookAuditLog::all());
        $this->assertSame(34, RdWebhookAuditLog::all()[0]['index']);
    }
}
