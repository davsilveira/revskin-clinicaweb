<?php

namespace App\Services;

use App\Models\MedicoPaciente;
use App\Models\Paciente;

/**
 * Ponto único para criar/atualizar o vínculo médico↔paciente (Opção 2).
 *
 * Reusado por TODOS os caminhos que antes escreviam `pacientes.medico_id`:
 * cadastro de paciente (form/autosave/quickCreate), emissão de receita e assistente.
 */
class PacienteVinculoService
{
    /**
     * Garante que existe um vínculo (medico↔paciente). Cria se não houver.
     *
     * @param  array<string,mixed>  $privados  anotacoes|codigo|indicado_por|ativo (opcionais)
     */
    public function garantir(Paciente $paciente, int $medicoId, array $privados = [], ?int $actorUserId = null, string $origem = 'form'): MedicoPaciente
    {
        $pivot = MedicoPaciente::firstOrNew([
            'medico_id' => $medicoId,
            'paciente_id' => $paciente->id,
        ]);

        $novo = ! $pivot->exists;

        // Campos privados: só sobrescreve as chaves enviadas (não zera o que não veio).
        foreach (['anotacoes', 'codigo', 'indicado_por'] as $campo) {
            if (array_key_exists($campo, $privados)) {
                $pivot->{$campo} = $privados[$campo] !== '' ? $privados[$campo] : null;
            }
        }
        if (array_key_exists('ativo', $privados)) {
            $pivot->ativo = (bool) $privados['ativo'];
        }

        if ($novo) {
            $pivot->origem = $origem;
            $pivot->created_by_user_id = $actorUserId;
        }
        $pivot->updated_by_user_id = $actorUserId;

        $pivot->save();

        return $pivot;
    }
}
