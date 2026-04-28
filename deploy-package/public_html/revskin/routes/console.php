<?php

use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncProdutosTinyJob;
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

// Tiny → ClincaWeb: contatos alterados no ERP (API V2)
Schedule::job(new PullPacientesTinyJob)->dailyAt('04:00')->name('tiny-pull-pacientes-04h');

// Reenvio automático de jobs falhos (RD/Tiny): 5 min + 12 h (ver IntegrationJobFailureRetryService)
Schedule::command('integration:retry-failed')->everyMinute()->name('integration-retry-failed');
