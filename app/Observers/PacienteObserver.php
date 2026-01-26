<?php

namespace App\Observers;

use App\Jobs\SyncClienteTinyJob;
use App\Models\Paciente;
use App\Models\Setting;

class PacienteObserver
{
    /**
     * Handle the Paciente "created" event.
     */
    public function created(Paciente $paciente): void
    {
        $this->dispararSincronizacao($paciente);
    }

    /**
     * Handle the Paciente "updated" event.
     */
    public function updated(Paciente $paciente): void
    {
        // Só sincronizar se integração estiver habilitada e campos relevantes foram alterados
        if (!$this->deveSincronizar($paciente)) {
            return;
        }

        $this->dispararSincronizacao($paciente);
    }

    /**
     * Verificar se deve sincronizar baseado nos campos alterados
     */
    protected function deveSincronizar(Paciente $paciente): bool
    {
        // Campos que quando alterados devem disparar sincronização
        $camposRelevantes = [
            'nome',
            'cpf',
            'email1',
            'telefone1',
            'endereco',
            'numero',
            'complemento',
            'bairro',
            'cidade',
            'uf',
            'cep',
        ];

        $alterados = $paciente->getDirty();

        foreach ($camposRelevantes as $campo) {
            if (isset($alterados[$campo])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Disparar job de sincronização com delay de 1 minuto
     */
    protected function dispararSincronizacao(Paciente $paciente): void
    {
        // Verificar se integração está habilitada
        if (!Setting::get('tiny_enabled', false)) {
            return;
        }

        // Disparar job com delay de 1 minuto
        SyncClienteTinyJob::dispatch($paciente)
            ->delay(now()->addMinute());
    }
}
