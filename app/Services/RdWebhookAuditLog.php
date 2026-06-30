<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class RdWebhookAuditLog
{
    private const SETTING_KEY = 'rd_webhook_audit_log';

    private const MAX_ENTRIES = 30;

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function record(array $entry): void
    {
        $items = self::all();
        array_unshift($items, array_merge([
            'id' => (string) Str::uuid(),
            'received_at' => now()->toIso8601String(),
        ], $entry));

        Setting::set(self::SETTING_KEY, json_encode(
            array_slice($items, 0, self::MAX_ENTRIES),
            JSON_THROW_ON_ERROR
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $raw = Setting::get(self::SETTING_KEY);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function lastReceivedAt(): ?string
    {
        $items = self::all();

        return isset($items[0]['received_at']) ? (string) $items[0]['received_at'] : null;
    }
}
