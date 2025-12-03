<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfosimplesIntegrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Authentication routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Password Reset routes
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
    ->middleware('guest')
    ->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
    ->middleware('guest')
    ->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])
    ->middleware('guest')
    ->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('guest')
    ->name('password.update');

// Protected routes (require authentication)
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Tools - Infosimples (admin and finance)
    Route::get('/tools/infosimples', [InfosimplesIntegrationController::class, 'tools'])
        ->name('tools.infosimples');
    Route::get('/tools/infosimples/history', [InfosimplesIntegrationController::class, 'history'])
        ->name('tools.infosimples.history');
    Route::post('/tools/infosimples/{type}', [InfosimplesIntegrationController::class, 'lookup'])
        ->where('type', 'cpf|cnpj|cro')
        ->name('tools.infosimples.lookup');
    Route::delete('/tools/infosimples/history', [InfosimplesIntegrationController::class, 'clearHistory'])
        ->name('tools.infosimples.clearHistory');

    // User management (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Settings (admin only)
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

        // Settings - Infosimples integration
        Route::put('/settings/integrations/infosimples', [InfosimplesIntegrationController::class, 'update'])
            ->name('settings.infosimples.update');
        Route::post('/settings/integrations/infosimples/test', [InfosimplesIntegrationController::class, 'testConnection'])
            ->name('settings.infosimples.test');

        // Exports (admin only)
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
        Route::get('/exports/{exportRequest}/download', [ExportController::class, 'download'])->name('exports.download');
        Route::delete('/exports/history', [ExportController::class, 'clearHistory'])->name('exports.history.clear');
    });
});
