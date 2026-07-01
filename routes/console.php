<?php

use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Services\IntegrationJobFingerprint;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Schedule::command('inspire')->hourly();

// Sincronização de produtos com Tiny ERP (2x por dia)
Schedule::job(new SyncProdutosTinyJob)->dailyAt('12:00')->name('tiny-sync-produtos-12h');
Schedule::job(new SyncProdutosTinyJob)->dailyAt('00:00')->name('tiny-sync-produtos-00h');

// Tiny → ClincaWeb: contatos alterados no ERP (API V2), incremental a cada 10 min
Schedule::job(new PullPacientesTinyJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->name('tiny-pull-pacientes');

// Reenvio automático de jobs falhos (RD/Tiny): 5 min + 12 h (ver IntegrationJobFailureRetryService)
Schedule::command('integration:retry-failed')->everyMinute()->name('integration-retry-failed');

// Rede de segurança: garante que as filas de integração (RD/Tiny) sejam processadas
// mesmo que o cron manual configurado no painel de hospedagem fique desatualizado
// (ex.: uma fila nova adicionada ao código, mas não à linha de cron do queue:work).
// A lista de filas vem de IntegrationJobFingerprint::INTEGRATION_QUEUES, então basta
// atualizar aquela constante para que esta rede de segurança acompanhe.
Schedule::command(
    'queue:work database --queue='.implode(',', IntegrationJobFingerprint::INTEGRATION_QUEUES).' --stop-when-empty --max-time=50'
)->everyMinute()->withoutOverlapping()->name('integration-queues-worker');
