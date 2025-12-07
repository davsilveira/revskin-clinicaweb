<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InfosimplesIntegrationController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\ClinicaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\TabelaPrecoController;
use App\Http\Controllers\ReceitaController;
use App\Http\Controllers\CallCenterController;
use App\Http\Controllers\AssistenteReceitaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\TinyIntegrationController;
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

    // API - CEP Lookup
    Route::get('/api/cep/{cep}', [PacienteController::class, 'buscarCep'])->name('api.cep');

    // Pacientes (all authenticated users, filtered by role in controller)
    Route::resource('pacientes', PacienteController::class);
    Route::get('/api/pacientes/search', [PacienteController::class, 'search'])->name('pacientes.search');

    // Receitas (medico and admin)
    Route::middleware('medico')->group(function () {
        Route::resource('receitas', ReceitaController::class);
        Route::post('/receitas/{receita}/copiar', [ReceitaController::class, 'copiar'])->name('receitas.copiar');
        Route::get('/receitas/{receita}/pdf', [ReceitaController::class, 'pdf'])->name('receitas.pdf');
        
        // Assistente de Receita
        Route::get('/assistente-receita', [AssistenteReceitaController::class, 'index'])->name('assistente.index');
        Route::post('/assistente-receita/iniciar', [AssistenteReceitaController::class, 'iniciar'])->name('assistente.iniciar');
        Route::post('/assistente-receita/processar', [AssistenteReceitaController::class, 'processar'])->name('assistente.processar');
        Route::post('/assistente-receita/gerar-receita', [AssistenteReceitaController::class, 'gerarReceita'])->name('assistente.gerar');
    });

    // Call Center (callcenter and admin)
    Route::middleware('callcenter')->group(function () {
        Route::get('/callcenter', [CallCenterController::class, 'index'])->name('callcenter.index');
        Route::get('/callcenter/{atendimento}', [CallCenterController::class, 'show'])->name('callcenter.show');
        Route::put('/callcenter/{atendimento}/status', [CallCenterController::class, 'atualizarStatus'])->name('callcenter.status');
        Route::post('/callcenter/{atendimento}/acompanhamento', [CallCenterController::class, 'addAcompanhamento'])->name('callcenter.acompanhamento');
        Route::post('/callcenter/cancelar', [CallCenterController::class, 'cancelarMultiplos'])->name('callcenter.cancelar');
    });

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

    // Admin only routes
    Route::middleware('admin')->group(function () {
        // Users management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        
        // Settings - Tiny ERP integration
        Route::put('/settings/integrations/tiny', [SettingsController::class, 'updateTiny'])
            ->name('settings.tiny.update');
        Route::post('/settings/integrations/tiny/test', [SettingsController::class, 'testTiny'])
            ->name('settings.tiny.test');

        // Exports
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
        Route::get('/exports/{exportRequest}/download', [ExportController::class, 'download'])->name('exports.download');
        Route::delete('/exports/history', [ExportController::class, 'clearHistory'])->name('exports.history.clear');

        // Cadastros (admin only)
        Route::resource('medicos', MedicoController::class);
        Route::post('/medicos/{medico}/assinatura', [MedicoController::class, 'uploadAssinatura'])->name('medicos.assinatura');
        
        Route::resource('clinicas', ClinicaController::class);
        Route::resource('produtos', ProdutoController::class);
        Route::resource('tabelas-preco', TabelaPrecoController::class);
        
        // Assistente - Tabela de Karnaugh (admin only)
        Route::get('/assistente/regras', [AssistenteReceitaController::class, 'regras'])->name('assistente.regras');
        Route::post('/assistente/regras', [AssistenteReceitaController::class, 'salvarRegras'])->name('assistente.regras.salvar');

        // Relatórios
        Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/receitas-medico', [RelatorioController::class, 'receitasPorMedico'])->name('relatorios.receitas-medico');
        Route::get('/relatorios/receitas-medico/export/{format}', [RelatorioController::class, 'exportReceitasMedico'])->name('relatorios.receitas-medico.export');

        // Integração Tiny ERP
        Route::prefix('integracoes/tiny')->name('tiny.')->group(function () {
            Route::get('/', [TinyIntegrationController::class, 'settings'])->name('settings');
            Route::put('/', [TinyIntegrationController::class, 'updateSettings'])->name('settings.update');
            Route::post('/test', [TinyIntegrationController::class, 'testConnection'])->name('test');
            Route::post('/sync-produtos', [TinyIntegrationController::class, 'syncProdutos'])->name('sync-produtos');
            Route::post('/sync-cliente/{paciente}', [TinyIntegrationController::class, 'syncCliente'])->name('sync-cliente');
            Route::post('/criar-proposta/{receita}', [TinyIntegrationController::class, 'criarProposta'])->name('criar-proposta');
            Route::get('/pedidos', [TinyIntegrationController::class, 'listarPedidos'])->name('pedidos');
        });
    });
});
