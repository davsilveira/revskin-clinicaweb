<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationJobFailureState extends Model
{
    protected $table = 'integration_job_failure_states';

    protected $fillable = [
        'fingerprint',
        'last_failed_job_uuid',
        'next_retry_at',
        'fast_retries_left',
        'delayed_retry_left',
        'exhausted',
        'in_flight',
        'last_dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'next_retry_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'exhausted' => 'boolean',
            'in_flight' => 'boolean',
        ];
    }
}
