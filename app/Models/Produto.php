<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';

    protected $fillable = [
        'codigo',
        'codigo_cq',
        'nome',
        'descricao',
        'anotacoes',
        'local_uso',
        'preco',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'preco' => 'decimal:2',
        ];
    }

    /**
     * Get the receita itens.
     */
    public function receitaItens(): HasMany
    {
        return $this->hasMany(ReceitaItem::class);
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Get display name with code.
     */
    public function getNomeCompletoAttribute(): string
    {
        return "{$this->codigo} - {$this->nome}";
    }
}










