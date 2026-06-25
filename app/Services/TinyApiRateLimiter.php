<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class TinyApiRateLimiter
{
    private const CACHE_KEY = 'tiny_api_v2_request_timestamps';

    private const WINDOW_SECONDS = 60;

    public function acquire(): void
    {
        $maxRpm = max(1, (int) Setting::get('tiny_api_max_rpm', 25));

        while (true) {
            $timestamps = $this->pruneTimestamps($this->getTimestamps());
            $now = microtime(true);

            if (count($timestamps) < $maxRpm) {
                $timestamps[] = $now;
                $this->saveTimestamps($timestamps);

                return;
            }

            $oldest = min($timestamps);
            $sleepSeconds = max(0.05, ($oldest + self::WINDOW_SECONDS) - $now + 0.1);
            usleep((int) ($sleepSeconds * 1_000_000));
        }
    }

    public function recordFromResponseHeader(?string $xLimitApi): void
    {
        if ($xLimitApi === null || $xLimitApi === '' || ! is_numeric($xLimitApi)) {
            return;
        }

        $limit = (int) $xLimitApi;
        if ($limit <= 0) {
            return;
        }

        Setting::set('tiny_api_max_rpm', max(1, $limit - 5));
    }

    /**
     * @return list<float>
     */
    public function getTimestampsForTesting(): array
    {
        return $this->getTimestamps();
    }

    public function resetForTesting(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  list<float>  $timestamps
     * @return list<float>
     */
    private function pruneTimestamps(array $timestamps): array
    {
        $cutoff = microtime(true) - self::WINDOW_SECONDS;

        return array_values(array_filter($timestamps, fn ($t) => $t > $cutoff));
    }

    /**
     * @return list<float>
     */
    private function getTimestamps(): array
    {
        $stored = Cache::get(self::CACHE_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param  list<float>  $timestamps
     */
    private function saveTimestamps(array $timestamps): void
    {
        Cache::put(self::CACHE_KEY, $timestamps, now()->addMinutes(2));
    }
}
