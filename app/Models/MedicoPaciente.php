<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo médico↔paciente (Opção 2). Carrega os campos privados por médico.
 */
class MedicoPaciente extends Pivot
{
    protected $table = 'medico_paciente';

    public $incrementing = true;

    protected $fillable = [
        'medico_id',
        'paciente_id',
        'anotacoes',
        'codigo',
        'indicado_por',
        'ativo',
        'origem',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
