<?php

namespace App\Services;

use App\Models\IntegrationJobFailureState;
use App\Models\Paciente;
use App\Models\Receita;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntegrationJobInspector
{
    /**
     * @return array{
     *     pending: list<array<string, mixed>>,
     *     pending_pagination: array<string, mixed>,
     *     failed: list<array<string, mixed>>,
     *     failed_pagination: array<string, mixed>,
     *     retry_states: list<array<string, mixed>>,
     *     retry_pagination: array<string, mixed>
     * }
     */
    public function snapshot(IntegrationJobQueryFilters $filters): array
    {
        $statesByFingerprint = IntegrationJobFailureState::query()
            ->get()
            ->keyBy('fingerprint');

        $receitaCache = [];
        $pacienteCache = [];

        $failedRows = $this->loadFailedRowsList($filters, $statesByFingerprint, $receitaCache, $pacienteCache);
        $failedByFingerprint = $this->latestFailedByFingerprint($failedRows);

        $pendingPaginated = $this->paginatePending($filters, $statesByFingerprint, $receitaCache, $pacienteCache);
        $failedPaginated = $this->paginateFailed($filters, $failedRows);
        $retryPaginated = $this->paginateRetryStates($filters, $statesByFingerprint, $failedByFingerprint);

        return [
            'pending' => $pendingPaginated['data'],
            'pending_pagination' => $pendingPaginated['pagination'],
            'failed' => $failedPaginated['data'],
            'failed_pagination' => $failedPaginated['pagination'],
            'retry_states' => $retryPaginated['data'],
            'retry_pagination' => $retryPaginated['pagination'],
        ];
    }

    /**
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<int, Receita|null>  $receitaCache
     * @param  array<int, Paciente|null>  $pacienteCache
     * @return list<array<string, mixed>>
     */
    private function loadFailedRowsList(
        IntegrationJobQueryFilters $filters,
        Collection $statesByFingerprint,
        array &$receitaCache,
        array &$pacienteCache
    ): array {
        $rows = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->tap(fn ($query) => $this->applySqlFilters($query, $filters, 'failed'))
            ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->all();

        $list = [];

        foreach ($this->mapFailedRows($rows, $statesByFingerprint, $receitaCache, $pacienteCache) as $row) {
            if ($filters->paciente && ! $this->matchesPacienteFilter($row, $filters->paciente)) {
                continue;
            }

            $list[] = $row;
        }

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $failedRows
     * @return array<string, array<string, mixed>>
     */
    private function latestFailedByFingerprint(array $failedRows): array
    {
        $map = [];

        foreach ($failedRows as $row) {
            $fingerprint = $row['fingerprint'];
            if (! isset($map[$fingerprint])) {
                $map[$fingerprint] = $row;

                continue;
            }

            $current = strtotime((string) ($map[$fingerprint]['failed_at'] ?? '')) ?: 0;
            $candidate = strtotime((string) ($row['failed_at'] ?? '')) ?: 0;
            if ($candidate >= $current) {
                $map[$fingerprint] = $row;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<int, Receita|null>  $receitaCache
     * @param  array<int, Paciente|null>  $pacienteCache
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function paginatePending(
        IntegrationJobQueryFilters $filters,
        Collection $statesByFingerprint,
        array &$receitaCache,
        array &$pacienteCache
    ): array {
        if ($filters->needsPostFilter()) {
            $rows = DB::table('jobs')
                ->orderByDesc('id')
                ->tap(fn ($query) => $this->applySqlFilters($query, $filters, 'pending'))
                ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
                ->all();

            $items = array_values(array_filter(
                $this->mapPendingRows($rows, $statesByFingerprint, $receitaCache, $pacienteCache),
                fn (array $row) => $this->matchesPacienteFilter($row, (string) $filters->paciente)
            ));
        } else {
            $total = (int) DB::table('jobs')
                ->tap(fn ($query) => $this->applySqlFilters($query, $filters, 'pending'))
                ->count();

            $offset = ($filters->pendingPage - 1) * IntegrationJobQueryFilters::PER_PAGE;

            $rows = DB::table('jobs')
                ->orderByDesc('id')
                ->tap(fn ($query) => $this->applySqlFilters($query, $filters, 'pending'))
                ->offset($offset)
                ->limit(IntegrationJobQueryFilters::PER_PAGE)
                ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
                ->all();

            $items = $this->mapPendingRows($rows, $statesByFingerprint, $receitaCache, $pacienteCache);

            return $this->wrapPaginated($items, $total, $filters->pendingPage, 'pending_page', $filters, true);
        }

        return $this->wrapPaginated(
            $items,
            count($items),
            $filters->pendingPage,
            'pending_page',
            $filters
        );
    }

    /**
     * @param  list<array<string, mixed>>  $failedRows
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function paginateFailed(IntegrationJobQueryFilters $filters, array $failedRows): array
    {
        return $this->wrapPaginated(
            $failedRows,
            count($failedRows),
            $filters->failedPage,
            'failed_page',
            $filters
        );
    }

    /**
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<string, array<string, mixed>>  $failedByFingerprint
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function paginateRetryStates(
        IntegrationJobQueryFilters $filters,
        Collection $statesByFingerprint,
        array $failedByFingerprint
    ): array {
        $items = $this->mapRetryStates($statesByFingerprint, $failedByFingerprint, $filters);

        return $this->wrapPaginated(
            $items,
            count($items),
            $filters->retryPage,
            'retry_page',
            $filters
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    private function wrapPaginated(
        array $items,
        int $total,
        int $page,
        string $pageParam,
        IntegrationJobQueryFilters $filters,
        bool $alreadyPaginated = false,
    ): array {
        $perPage = IntegrationJobQueryFilters::PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $pageItems = $alreadyPaginated
            ? $items
            : array_slice($items, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => route('tools.integracoes.index'),
                'pageName' => $pageParam,
                'query' => $filters->queryParams(),
            ]
        );

        return [
            'data' => $pageItems,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'links' => collect($paginator->linkCollection()->toArray())
                    ->map(fn (array $link) => [
                        'url' => $link['url'],
                        'label' => $link['label'],
                        'active' => $link['active'],
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applySqlFilters($query, IntegrationJobQueryFilters $filters, string $type): void
    {
        $query->whereIn('queue', IntegrationJobFingerprint::INTEGRATION_QUEUES);

        if ($filters->queue) {
            $query->where('queue', $filters->queue);
        }

        if ($filters->jobClass) {
            $query->where('payload', 'like', '%'.class_basename($filters->jobClass).'%');
        }

        $since = now()->subDays($filters->days);

        if ($type === 'pending') {
            $query->where('created_at', '>=', $since->timestamp);
        } else {
            $query->where('failed_at', '>=', $since);
        }
    }

    /**
     * @param  list<object>  $rows
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<int, Receita|null>  $receitaCache
     * @param  array<int, Paciente|null>  $pacienteCache
     * @return list<array<string, mixed>>
     */
    private function mapPendingRows(array $rows, Collection $statesByFingerprint, array &$receitaCache, array &$pacienteCache): array
    {
        $out = [];

        foreach ($rows as $row) {
            $parsed = IntegrationJobFingerprint::parsePayload((string) $row->payload);
            if ($parsed === null) {
                continue;
            }

            $meta = $this->enrichDescribe(
                IntegrationJobFingerprint::describe($parsed['class'], $parsed['instance']),
                $receitaCache,
                $pacienteCache
            );

            $state = $statesByFingerprint->get($parsed['fingerprint']);

            $out[] = [
                'id' => (int) $row->id,
                'uuid' => $this->extractUuidFromPayload((string) $row->payload),
                'queue' => (string) $row->queue,
                'attempts' => (int) $row->attempts,
                'status' => $row->reserved_at ? 'running' : 'pending',
                'available_at' => $this->formatTimestamp($row->available_at),
                'created_at' => $this->formatTimestamp($row->created_at),
                'fingerprint' => $parsed['fingerprint'],
                'retry_state' => $this->formatRetryState($state),
                ...$meta,
            ];
        }

        return $out;
    }

    /**
     * @param  list<object>  $rows
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<int, Receita|null>  $receitaCache
     * @param  array<int, Paciente|null>  $pacienteCache
     * @return list<array<string, mixed>>
     */
    private function mapFailedRows(array $rows, Collection $statesByFingerprint, array &$receitaCache, array &$pacienteCache): array
    {
        $out = [];

        foreach ($rows as $row) {
            $parsed = IntegrationJobFingerprint::parsePayload((string) $row->payload);
            if ($parsed === null) {
                continue;
            }

            $meta = $this->enrichDescribe(
                IntegrationJobFingerprint::describe($parsed['class'], $parsed['instance']),
                $receitaCache,
                $pacienteCache
            );

            $state = $statesByFingerprint->get($parsed['fingerprint']);

            $out[] = [
                'uuid' => (string) $row->uuid,
                'queue' => (string) $row->queue,
                'failed_at' => $this->formatTimestamp($row->failed_at),
                'error_summary' => $this->summarizeException((string) $row->exception),
                'error_full' => (string) $row->exception,
                'fingerprint' => $parsed['fingerprint'],
                'retry_state' => $this->formatRetryState($state),
                ...$meta,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<string, IntegrationJobFailureState>  $statesByFingerprint
     * @param  array<string, array<string, mixed>>  $failedByFingerprint
     * @return list<array<string, mixed>>
     */
    private function mapRetryStates(Collection $statesByFingerprint, array $failedByFingerprint, IntegrationJobQueryFilters $filters): array
    {
        $since = now()->subDays($filters->days);

        return $statesByFingerprint
            ->filter(function (IntegrationJobFailureState $state) use ($since, $failedByFingerprint, $filters) {
                if ($state->updated_at === null || $state->updated_at->lt($since)) {
                    return false;
                }

                $failedRow = $failedByFingerprint[$state->fingerprint] ?? null;

                if ($filters->queue && ($failedRow['queue'] ?? null) !== $filters->queue) {
                    return false;
                }

                if ($filters->jobClass && ($failedRow['job_class'] ?? null) !== $filters->jobClass) {
                    return false;
                }

                if ($filters->paciente) {
                    if ($failedRow === null) {
                        return false;
                    }

                    return $this->matchesPacienteFilter($failedRow, $filters->paciente);
                }

                return true;
            })
            ->sortByDesc('updated_at')
            ->values()
            ->map(function (IntegrationJobFailureState $state) use ($failedByFingerprint) {
                $failedRow = $failedByFingerprint[$state->fingerprint] ?? null;

                return [
                    'fingerprint' => $state->fingerprint,
                    'last_failed_job_uuid' => $state->last_failed_job_uuid,
                    'next_retry_at' => $state->next_retry_at?->toIso8601String(),
                    'fast_retries_left' => (int) $state->fast_retries_left,
                    'delayed_retry_left' => (int) $state->delayed_retry_left,
                    'exhausted' => (bool) $state->exhausted,
                    'in_flight' => (bool) $state->in_flight,
                    'last_dispatched_at' => $state->last_dispatched_at?->toIso8601String(),
                    'job_label' => $failedRow['job_label'] ?? null,
                    'queue' => $failedRow['queue'] ?? null,
                    'receita_id' => $failedRow['receita_id'] ?? null,
                    'receita_numero' => $failedRow['receita_numero'] ?? null,
                    'paciente_nome' => $failedRow['paciente_nome'] ?? null,
                    'error_summary' => $failedRow['error_summary'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesPacienteFilter(array $row, string $search): bool
    {
        $nome = (string) ($row['paciente_nome'] ?? '');

        return $nome !== '' && mb_stripos($nome, $search) !== false;
    }

    /**
     * @param  array<string, mixed>  $describe
     * @param  array<int, Receita|null>  $receitaCache
     * @param  array<int, Paciente|null>  $pacienteCache
     * @return array<string, mixed>
     */
    private function enrichDescribe(array $describe, array &$receitaCache, array &$pacienteCache): array
    {
        if ($describe['receita_id']) {
            $receitaId = (int) $describe['receita_id'];
            if (! array_key_exists($receitaId, $receitaCache)) {
                $receitaCache[$receitaId] = Receita::query()
                    ->with('paciente:id,nome')
                    ->find($receitaId);
            }
            $receita = $receitaCache[$receitaId];
            if ($receita) {
                $describe['receita_numero'] = $receita->numero ?? ('REC-'.$receita->id);
                $describe['paciente_id'] = $receita->paciente_id;
                $describe['paciente_nome'] = $receita->paciente?->nome;
                $describe['context_label'] = 'Receita #'.($describe['receita_numero'] ?? $receita->id);
            }
        }

        if ($describe['paciente_id'] && ! $describe['paciente_nome']) {
            $pacienteId = (int) $describe['paciente_id'];
            if (! array_key_exists($pacienteId, $pacienteCache)) {
                $pacienteCache[$pacienteId] = Paciente::query()->find($pacienteId, ['id', 'nome']);
            }
            $paciente = $pacienteCache[$pacienteId];
            if ($paciente) {
                $describe['paciente_nome'] = $paciente->nome;
                $describe['context_label'] = $paciente->nome;
            }
        }

        return $describe;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatRetryState(?IntegrationJobFailureState $state): ?array
    {
        if ($state === null) {
            return null;
        }

        return [
            'fast_retries_left' => (int) $state->fast_retries_left,
            'delayed_retry_left' => (int) $state->delayed_retry_left,
            'exhausted' => (bool) $state->exhausted,
            'in_flight' => (bool) $state->in_flight,
            'next_retry_at' => $state->next_retry_at?->toIso8601String(),
        ];
    }

    private function summarizeException(string $exception): string
    {
        $lines = preg_split('/\R/', trim($exception)) ?: [];
        $first = trim($lines[0] ?? '');
        if ($first === '') {
            return 'Erro desconhecido';
        }

        if (preg_match('/\s+in\s+\//', $first)) {
            $first = trim(preg_split('/\s+in\s+\//', $first, 2)[0] ?? $first);
        }

        if (strlen($first) > 240) {
            return substr($first, 0, 237).'...';
        }

        return $first;
    }

    private function extractUuidFromPayload(string $payload): ?string
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return null;
        }

        $uuid = $data['uuid'] ?? null;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return date('c', (int) $value);
        }

        return (string) $value;
    }
}
