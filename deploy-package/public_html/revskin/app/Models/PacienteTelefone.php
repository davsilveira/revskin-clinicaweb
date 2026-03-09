<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacienteTelefone extends Model
{
    use HasFactory;

    protected $table = 'paciente_telefones';

    protected $fillable = [
        'paciente_id',
        'numero',
        'tipo',
        'principal',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
        ];
    }

    /**
     * Phone type options.
     */
    public static function getTipos(): array
    {
        return [
            'Celular' => 'Celular',
            'Residencial' => 'Residencial',
            'Comercial' => 'Comercial',
            'WhatsApp' => 'WhatsApp',
            'Recado' => 'Recado',
            'Outro' => 'Outro',
        ];
    }

    /**
     * Get the paciente.
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }
}
