<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Services\IntegrationJobFailureRetryService;
use App\Services\IntegrationJobFingerprint;
use App\Services\IntegrationJobInspector;
use App\Services\IntegrationJobQueryFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationJobsController extends Controller
{
    public function index(Request $request, IntegrationJobInspector $inspector): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acesso restrito a administradores.');

        $filters = IntegrationJobQueryFilters::fromRequest($request);

        $snapshot = $inspector->snapshot($filters);

        return Inertia::render('Tools/Integracoes', [
            'queues' => IntegrationJobFingerprint::INTEGRATION_QUEUES,
            'jobOptions' => IntegrationJobFingerprint::jobOptions(),
            'filters' => $filters->toInertia(),
            'pending' => $snapshot['pending'],
            'pendingPagination' => $snapshot['pending_pagination'],
            'failed' => $snapshot['failed'],
            'failedPagination' => $snapshot['failed_pagination'],
            'retryStates' => $snapshot['retry_states'],
            'retryPagination' => $snapshot['retry_pagination'],
        ]);
    }

    public function retry(Request $request, string $uuid, IntegrationJobFailureRetryService $retryService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acesso restrito a administradores.');

        if (! $this->isManagedFailedJob($uuid)) {
            return back()->with('error', 'Job não encontrado ou não pertence às filas de integração.');
        }

        $retryService->resetStateForManualRetry($uuid);

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exitCode !== 0) {
            return back()->with('error', 'Não foi possível reprocessar o job.');
        }

        $retryService->markInFlightForManualRetry($uuid);

        return back()->with('success', 'Job reenfileirado para reprocessamento.');
    }

    public function retryBatch(Request $request, IntegrationJobFailureRetryService $retryService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acesso restrito a administradores.');

        $validated = $request->validate([
            'uuids' => 'nullable|array',
            'uuids.*' => 'uuid',
            'queue' => 'nullable|string|in:'.implode(',', IntegrationJobFingerprint::INTEGRATION_QUEUES),
        ]);

        $uuids = collect($validated['uuids'] ?? []);
        if ($uuids->isEmpty() && ! empty($validated['queue'])) {
            $uuids = DB::table('failed_jobs')
                ->where('queue', $validated['queue'])
                ->pluck('uuid');
        }

        $retried = 0;
        foreach ($uuids as $uuid) {
            if (! $this->isManagedFailedJob($uuid)) {
                continue;
            }
            $retryService->resetStateForManualRetry($uuid);
            if (Artisan::call('queue:retry', ['id' => [$uuid]]) === 0) {
                $retryService->markInFlightForManualRetry($uuid);
                $retried++;
            }
        }

        if ($retried === 0) {
            return back()->with('error', 'Nenhum job foi reprocessado.');
        }

        return back()->with('success', "{$retried} job(s) reenfileirado(s).");
    }

    public function forget(Request $request, string $uuid, IntegrationJobFailureRetryService $retryService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Acesso restrito a administradores.');

        if (! $this->isManagedFailedJob($uuid)) {
            return back()->with('error', 'Job não encontrado ou não pertence às filas de integração.');
        }

        $fingerprint = $this->fingerprintForFailedJob($uuid);

        $exitCode = Artisan::call('queue:forget', ['id' => $uuid]);
        if ($exitCode !== 0) {
            return back()->with('error', 'Não foi possível descartar o job.');
        }

        if ($fingerprint !== null) {
            $retryService->syncStateAfterForget($fingerprint);
        }

        return back()->with('success', 'Job removido da lista de falhos.');
    }

    private function fingerprintForFailedJob(string $uuid): ?string
    {
        $row = DB::table('failed_jobs')
            ->where('uuid', $uuid)
            ->whereIn('queue', IntegrationJobFingerprint::INTEGRATION_QUEUES)
            ->first(['payload']);

        if ($row === null) {
            return null;
        }

        $parsed = IntegrationJobFingerprint::fromFailedJobPayload((string) $row->payload);

        return $parsed['fingerprint'] ?? null;
    }

    private function isManagedFailedJob(string $uuid): bool
    {
        $row = DB::table('failed_jobs')
            ->where('uuid', $uuid)
            ->whereIn('queue', IntegrationJobFingerprint::INTEGRATION_QUEUES)
            ->first(['payload']);

        if ($row === null) {
            return false;
        }

        return IntegrationJobFingerprint::fromFailedJobPayload((string) $row->payload) !== null;
    }
}
