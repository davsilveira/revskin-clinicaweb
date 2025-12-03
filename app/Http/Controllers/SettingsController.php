<?php

namespace App\Http\Controllers;

use App\Models\InfosimplesSetting;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $this->ensureAdmin();

        $infosimples = InfosimplesSetting::current();

        return Inertia::render('Settings/Index', [
            'infosimples' => [
                'settings' => [
                    'enabled' => (bool) $infosimples->enabled,
                    'has_token' => !empty($infosimples->token),
                    'cache_months' => $infosimples->cache_months,
                    'timeout' => $infosimples->timeout,
                    'updated_at' => optional($infosimples->updated_at)->format('d/m/Y H:i'),
                ],
                'cache_ttl_options' => $this->cacheOptions(),
                'default_timeout_options' => $this->timeoutOptions(),
            ],
            // Add more integrations here as needed
            // 'other_integration' => [...],
        ]);
    }

    protected function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    protected function cacheOptions(): array
    {
        return [
            ['value' => 1, 'label' => '1 mês (30 dias)'],
            ['value' => 2, 'label' => '2 meses (60 dias)'],
            ['value' => 3, 'label' => '3 meses (90 dias)'],
            ['value' => 4, 'label' => '4 meses (120 dias)'],
            ['value' => 5, 'label' => '5 meses (150 dias)'],
            ['value' => 6, 'label' => '6 meses (180 dias)'],
        ];
    }

    protected function timeoutOptions(): array
    {
        return [
            ['value' => 30, 'label' => '30 segundos'],
            ['value' => 60, 'label' => '60 segundos'],
            ['value' => 120, 'label' => '120 segundos'],
            ['value' => 180, 'label' => '180 segundos'],
            ['value' => 300, 'label' => '300 segundos'],
            ['value' => 600, 'label' => '600 segundos'],
        ];
    }
}
