<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinica extends Model
{
    use HasFactory;

    protected $table = 'clinicas';

    protected $fillable = [
        'nome',
        'cnpj',
        'telefone1',
        'telefone2',
        'telefone3',
        'email',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'cep',
        'anotacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the medicos for this clinica.
     */
    public function medicos(): HasMany
    {
        return $this->hasMany(Medico::class);
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}










