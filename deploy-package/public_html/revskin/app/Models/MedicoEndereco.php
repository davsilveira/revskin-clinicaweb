<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicoEndereco extends Model
{
    use HasFactory;

    protected $table = 'medico_enderecos';

    protected $fillable = [
        'medico_id',
        'nome',
        'cep',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'principal',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
        ];
    }

    /**
     * Get the medico.
     */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    /**
     * Get endereco completo.
     */
    public function getEnderecoCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->endereco,
            $this->numero ? "nº {$this->numero}" : null,
            $this->complemento,
            $this->bairro,
            $this->cidade,
            $this->uf,
            $this->cep ? "CEP: {$this->cep}" : null,
        ]);
        return implode(', ', $partes);
    }
}
