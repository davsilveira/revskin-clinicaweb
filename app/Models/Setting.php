<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'category',
        'description',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting?->value ?? $default;
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, ?string $category = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'category' => $category]
        );

        Cache::forget("setting.{$key}");
    }

    /**
     * Get all settings as key-value array
     */
    public static function allAsArray(): array
    {
        return static::pluck('value', 'key')->toArray();
    }
}

