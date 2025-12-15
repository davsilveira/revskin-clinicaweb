<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TabelaPreco extends Model
{
    use HasFactory;

    protected $table = 'tabelas_preco';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the medicos using this price table.
     */
    public function medicos(): HasMany
    {
        return $this->hasMany(Medico::class, 'tabela_preco_id');
    }

    /**
     * Get the produtos with prices.
     */
    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'tabela_preco_itens', 'tabela_preco_id', 'produto_id')
            ->withPivot('preco')
            ->withTimestamps();
    }

    /**
     * Get the itens (pivot records).
     */
    public function itens(): HasMany
    {
        return $this->hasMany(TabelaPrecoItem::class, 'tabela_preco_id');
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}




