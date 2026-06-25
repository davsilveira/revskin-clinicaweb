<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\TinyApiRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TinyApiRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    private TinyApiRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new TinyApiRateLimiter;
        $this->limiter->resetForTesting();
        Setting::set('tiny_api_max_rpm', 25);
    }

    #[Test]
    public function acquire_records_request_timestamp(): void
    {
        $this->limiter->acquire();

        $this->assertCount(1, $this->limiter->getTimestampsForTesting());
    }

    #[Test]
    public function response_header_updates_max_rpm_with_margin(): void
    {
        $this->limiter->recordFromResponseHeader('60');

        $this->assertSame('55', Setting::get('tiny_api_max_rpm'));
    }

    #[Test]
    public function expired_timestamps_are_pruned_allowing_new_requests(): void
    {
        Setting::set('tiny_api_max_rpm', 1);
        Cache::put('tiny_api_v2_request_timestamps', [microtime(true) - 61], now()->addMinutes(2));

        $this->limiter->acquire();

        $this->assertCount(1, $this->limiter->getTimestampsForTesting());
    }
}
