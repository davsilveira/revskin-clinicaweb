<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfosimplesSetting extends Model
{
    protected $fillable = [
        'enabled',
        'token',
        'cache_months',
        'timeout',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'cache_months' => 'integer',
        'timeout' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => false,
            'token' => null,
            'cache_months' => 1,
            'timeout' => 30,
        ]);
    }

    public function cacheTtlMonths(): int
    {
        return max(1, (int) ($this->cache_months ?: 1));
    }
}

