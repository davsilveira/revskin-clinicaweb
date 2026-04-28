<?php

namespace App\Services;

use App\Models\IntegrationJobFailureState;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class IntegrationJobFailureRetryService
{
    private const FAST_INTERVAL_MINUTES = 5;

    private const DELAYED_INTERVAL_HOURS = 12;

    private const IN_FLIGHT_SUCCESS_TTL_HOURS = 1;

    public function __construct(
        private bool $logDebug = false
    ) {}

    /**
     * Uma iteração: sincronizar failed_jobs, processar reenvios, limpar sucesso inferido.
     */
    public function run(): void
    {
        $this->ingestFromFailedJobs();
        $this->dispatchDueRetries();
        $this->cleanupInFlightSuccesses();
    }

    /**
     * @return list<stdClass&object{ uuid: string, failed_at: string, payload: string, queue: string }>
     */
    public function loadIntegrationFailedRows(): array
    {
        $rows = DB::table('failed_jobs')
            ->whereIn('queue', IntegrationJobFingerprint::INTEGRATION_QUEUES)
            ->orderBy('id')
            ->get(['uuid', 'failed_at', 'payload', 'queue']);

        $out = [];
        foreach ($rows as $row) {
            if (IntegrationJobFingerprint::fromFailedJobPayload($row->payload) !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function ingestFromFailedJobs(): void
    {
        $perFingerprintLatest = $this->latestFailedByFingerprint();
        if ($this->logDebug) {
            Log::debug('integration_retry: latest by fingerprint', ['count' => count($perFingerprintLatest)]);
        }

        foreach ($perFingerprintLatest as $fp => $row) {
            $this->ingestOneFingerprint($fp, $row);
        }
    }

    /**
     * @param  stdClass&object{ uuid: string, failed_at: string, payload: string, queue: string }  $row
     */
    private function ingestOneFingerprint(string $fingerprint, stdClass $row): void
    {
        $failedAt = $this->parseFailedAt($row->failed_at);
        $state = IntegrationJobFailureState::query()->where('fingerprint', $fingerprint)->first();

        if ($state === null) {
            IntegrationJobFailureState::query()->create([
                'fingerprint' => $fingerprint,
                'last_failed_job_uuid' => $row->uuid,
                'next_retry_at' => $failedAt->copy()->addMinutes(self::FAST_INTERVAL_MINUTES),
                'fast_retries_left' => 3,
                'delayed_retry_left' => 1,
                'exhausted' => false,
                'in_flight' => false,
                'last_dispatched_at' => null,
            ]);
            if ($this->logDebug) {
                Log::debug('integration_retry: new state', ['fingerprint' => $fingerprint, 'next' => $failedAt->copy()->addMinutes(5)]);
            }

            return;
        }

        if ($state->exhausted) {
            if ($state->last_failed_job_uuid !== $row->uuid) {
                $state->update(['last_failed_job_uuid' => $row->uuid]);
            }

            return;
        }

        if ($state->last_failed_job_uuid === $row->uuid) {
            return;
        }

        $state->last_failed_job_uuid = $row->uuid;
        $state->in_flight = false;
        $state->last_dispatched_at = null;

        if ($state->fast_retries_left > 0) {
            $state->next_retry_at = $failedAt->copy()->addMinutes(self::FAST_INTERVAL_MINUTES);
        } elseif ($state->delayed_retry_left > 0) {
            $state->next_retry_at = $failedAt->copy()->addHours(self::DELAYED_INTERVAL_HOURS);
        } else {
            $state->exhausted = true;
            $state->next_retry_at = null;
        }

        $state->save();
        if ($this->logDebug) {
            Log::debug('integration_retry: new failure for fingerprint', [
                'fingerprint' => $fingerprint,
                'next' => $state->next_retry_at?->toIso8601String(),
                'exhausted' => $state->exhausted,
            ]);
        }
    }

    /**
     * @return array<string, stdClass&object{ uuid: string, failed_at: string, payload: string, queue: string }>
     */
    private function latestFailedByFingerprint(): array
    {
        $perFingerprint = [];
        foreach ($this->loadIntegrationFailedRows() as $row) {
            $parsed = IntegrationJobFingerprint::fromFailedJobPayload($row->payload);
            if ($parsed === null) {
                continue;
            }
            $fp = $parsed['fingerprint'];
            if (! isset($perFingerprint[$fp])) {
                $perFingerprint[$fp] = $row;

                continue;
            }
            $a = $this->parseFailedAt($perFingerprint[$fp]->failed_at);
            $b = $this->parseFailedAt($row->failed_at);
            if ($b->gt($a)) {
                $perFingerprint[$fp] = $row;
            }
        }

        return $perFingerprint;
    }

    public function dispatchDueRetries(): void
    {
        $now = now();
        $existsUuids = DB::table('failed_jobs')->pluck('uuid')->all();
        $existsSet = array_flip($existsUuids);

        $states = IntegrationJobFailureState::query()
            ->where('exhausted', false)
            ->where('next_retry_at', '<=', $now)
            ->get();

        foreach ($states as $state) {
            if (! isset($existsSet[$state->last_failed_job_uuid])) {
                continue;
            }

            $isFast = $state->fast_retries_left > 0;
            $can12h = $state->fast_retries_left === 0 && $state->delayed_retry_left > 0;
            if (! $isFast && ! $can12h) {
                continue;
            }

            $exitCode = Artisan::call('queue:retry', [
                'id' => [$state->last_failed_job_uuid],
            ]);
            if ($exitCode !== 0) {
                Log::error('integration_retry: queue:retry retornou código não zero', [
                    'uuid' => $state->last_failed_job_uuid,
                    'exit' => $exitCode,
                ]);

                continue;
            }

            if ($isFast) {
                $state->fast_retries_left = max(0, $state->fast_retries_left - 1);
            } else {
                $state->delayed_retry_left = 0;
            }
            $state->in_flight = true;
            $state->last_dispatched_at = $now;
            $state->save();

            if ($this->logDebug) {
                Log::debug('integration_retry: reenviado', [
                    'fingerprint' => $state->fingerprint,
                    'uuid' => $state->last_failed_job_uuid,
                    'fast_left' => $state->fast_retries_left,
                    'delay_left' => $state->delayed_retry_left,
                ]);
            }
        }
    }

    public function cleanupInFlightSuccesses(): void
    {
        $activeFingerprints = array_keys($this->latestFailedByFingerprint());
        $activeSet = array_flip($activeFingerprints);
        $cutoff = now()->subHours(self::IN_FLIGHT_SUCCESS_TTL_HOURS);

        $states = IntegrationJobFailureState::query()
            ->where('in_flight', true)
            ->where('last_dispatched_at', '<', $cutoff)
            ->get();

        foreach ($states as $state) {
            if (isset($activeSet[$state->fingerprint])) {
                continue;
            }
            if ($state->exhausted) {
                $state->update(['in_flight' => false]);

                continue;
            }
            if ($this->logDebug) {
                Log::debug('integration_retry: removendo estado (assumido sucesso pós-reenfileiramento)', [
                    'fingerprint' => $state->fingerprint,
                ]);
            }
            $state->delete();
        }
    }

    private function parseFailedAt(mixed $value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }
}
