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
        'categoria',
        'modo_uso',
        'preco',
        'preco_custo',
        'estoque_minimo',
        'unidade',
        'tiny_id',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'preco' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'estoque_minimo' => 'integer',
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










