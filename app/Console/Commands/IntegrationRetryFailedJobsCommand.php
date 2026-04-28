<?php

namespace App\Console\Commands;

use App\Services\IntegrationJobFailureRetryService;
use Illuminate\Console\Command;

class IntegrationRetryFailedJobsCommand extends Command
{
    protected $signature = 'integration:retry-failed {--debug : Log detalhado}';

    protected $description = 'Reenvia jobs falhos de integrações RD/Tiny conforme política (5 min / 12 h)';

    public function handle(): int
    {
        $service = new IntegrationJobFailureRetryService((bool) $this->option('debug'));
        $service->run();

        return self::SUCCESS;
    }
}
