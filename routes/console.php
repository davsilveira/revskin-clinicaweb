<?php

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
