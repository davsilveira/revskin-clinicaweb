<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $this->ensureAdmin();

        $settings = Setting::getSettings();

        return Inertia::render('Settings/Index', [
            'tiny' => [
                'settings' => [
                    'enabled' => (bool) ($settings['tiny_enabled'] ?? false),
                    'has_token' => !empty($settings['tiny_token'] ?? null),
                    'url_base' => $settings['tiny_url_base'] ?? 'https://api.tiny.com.br/api2',
                    'updated_at' => $settings['tiny_updated_at'] ?? null,
                ],
            ],
        ]);
    }

    public function updateTiny(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'enabled' => 'boolean',
            'token' => 'nullable|string',
            'remove_token' => 'nullable|boolean',
            'url_base' => 'nullable|string|url',
        ]);

        Setting::set('tiny_enabled', $validated['enabled'] ?? false);
        
        if (!empty($validated['remove_token'])) {
            Setting::set('tiny_token', null);
        } elseif (!empty($validated['token'])) {
            Setting::set('tiny_token', encrypt($validated['token']));
        }

        if (!empty($validated['url_base'])) {
            Setting::set('tiny_url_base', $validated['url_base']);
        }

        Setting::set('tiny_updated_at', now()->format('d/m/Y H:i'));

        return back()->with('success', 'Configuracoes salvas com sucesso!');
    }

    public function testTiny()
    {
        $this->ensureAdmin();

        $settings = Setting::getSettings();
        $token = $settings['tiny_token'] ?? null;
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token nao configurado.',
            ]);
        }

        try {
            $decryptedToken = decrypt($token);
            $urlBase = $settings['tiny_url_base'] ?? 'https://api.tiny.com.br/api2';

            // Testar conexao listando produtos
            $response = Http::timeout(30)->asForm()->post("{$urlBase}/produtos.pesquisa.php", [
                'token' => $decryptedToken,
                'formato' => 'json',
                'pesquisa' => '',
            ]);

            $data = $response->json();

            if (isset($data['retorno']['status']) && $data['retorno']['status'] === 'OK') {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexao estabelecida com sucesso!',
                    'data' => [
                        'status' => $data['retorno']['status'],
                        'registros' => $data['retorno']['numero_paginas'] ?? 0,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $data['retorno']['erros'][0]['erro'] ?? 'Erro desconhecido na API.',
                'data' => $data['retorno'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ]);
        }
    }

    protected function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
