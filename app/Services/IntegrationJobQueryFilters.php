<?php

namespace App\Services;

use Illuminate\Http\Request;

readonly class IntegrationJobQueryFilters
{
    /** @var list<int> */
    public const ALLOWED_DAYS = [7, 15, 30];

    public const PER_PAGE = 50;

    public function __construct(
        public ?string $queue = null,
        public int $days = 7,
        public ?string $jobClass = null,
        public ?string $paciente = null,
        public int $pendingPage = 1,
        public int $failedPage = 1,
        public int $retryPage = 1,
        public string $tab = 'failed',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $days = (int) $request->input('days', 7);
        if (! in_array($days, self::ALLOWED_DAYS, true)) {
            $days = 7;
        }

        $queue = $request->string('queue')->toString();
        $queueFilter = in_array($queue, IntegrationJobFingerprint::INTEGRATION_QUEUES, true) ? $queue : null;

        $job = $request->string('job')->toString();
        $jobClass = self::resolveJobClass($job);

        $paciente = trim($request->string('paciente')->toString());

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['pending', 'failed', 'retry'], true)) {
            $tab = 'failed';
        }

        return new self(
            queue: $queueFilter,
            days: $days,
            jobClass: $jobClass,
            paciente: $paciente !== '' ? $paciente : null,
            pendingPage: max(1, (int) $request->input('pending_page', 1)),
            failedPage: max(1, (int) $request->input('failed_page', 1)),
            retryPage: max(1, (int) $request->input('retry_page', 1)),
            tab: $tab,
        );
    }

    public static function resolveJobClass(string $job): ?string
    {
        if ($job === '') {
            return null;
        }

        if (array_key_exists($job, IntegrationJobFingerprint::FILTER_JOB_LABELS)) {
            return $job;
        }

        foreach (IntegrationJobFingerprint::FILTER_JOB_LABELS as $class => $label) {
            if (class_basename($class) === $job) {
                return $class;
            }
        }

        return null;
    }

    public function jobKey(): ?string
    {
        if ($this->jobClass === null) {
            return null;
        }

        return class_basename($this->jobClass);
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertia(): array
    {
        return [
            'queue' => $this->queue,
            'days' => $this->days,
            'job' => $this->jobKey(),
            'paciente' => $this->paciente,
            'pending_page' => $this->pendingPage,
            'failed_page' => $this->failedPage,
            'retry_page' => $this->retryPage,
            'tab' => $this->tab,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(array $overrides = []): array
    {
        $params = [
            'days' => $this->days,
            'tab' => $this->tab,
            'pending_page' => $this->pendingPage,
            'failed_page' => $this->failedPage,
            'retry_page' => $this->retryPage,
        ];

        if ($this->queue) {
            $params['queue'] = $this->queue;
        }

        if ($this->jobKey()) {
            $params['job'] = $this->jobKey();
        }

        if ($this->paciente) {
            $params['paciente'] = $this->paciente;
        }

        return array_merge($params, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParamsResettingPages(array $overrides = []): array
    {
        return $this->queryParams(array_merge([
            'pending_page' => 1,
            'failed_page' => 1,
            'retry_page' => 1,
        ], $overrides));
    }

    public function needsPostFilter(): bool
    {
        return $this->paciente !== null;
    }
}
