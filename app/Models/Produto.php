<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the tabelas de preco.
     */
    public function tabelasPreco(): BelongsToMany
    {
        return $this->belongsToMany(TabelaPreco::class, 'tabela_preco_itens', 'produto_id', 'tabela_preco_id')
            ->withPivot('preco')
            ->withTimestamps();
    }

    /**
     * Get the receita itens.
     */
    public function receitaItens(): HasMany
    {
        return $this->hasMany(ReceitaItem::class);
    }

    /**
     * Get price from specific tabela.
     */
    public function getPrecoFromTabela(?int $tabelaPrecoId): float
    {
        if (!$tabelaPrecoId) {
            return 0;
        }

        $item = TabelaPrecoItem::where('tabela_preco_id', $tabelaPrecoId)
            ->where('produto_id', $this->id)
            ->first();

        return $item ? (float) $item->preco : 0;
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










