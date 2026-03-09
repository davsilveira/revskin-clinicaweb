<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Get the medicos for this clinica (legacy - direct FK).
     */
    public function medicosDiretos(): HasMany
    {
        return $this->hasMany(Medico::class);
    }

    /**
     * Get the medicos for this clinica (many-to-many).
     */
    public function medicos(): BelongsToMany
    {
        return $this->belongsToMany(Medico::class, 'clinica_medico')
            ->withTimestamps();
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}










