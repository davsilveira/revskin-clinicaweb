<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfosimplesCache extends Model
{
    protected $table = 'infosimples_cache';

    protected $fillable = [
        'service',
        'key_value',
        'response',
        'code',
    ];

    protected $casts = [
        'response' => 'array',
        'code' => 'integer',
    ];

    public function isFresh(int $cacheMonths): bool
    {
        if (!$this->created_at) {
            return false;
        }

        $months = max(1, $cacheMonths);

        return $this->created_at->gt(now()->subMonths($months));
    }
}

