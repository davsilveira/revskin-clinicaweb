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
        'anotacoes_internas',
        'local_uso',
        'categoria',
        'modo_uso',
        'preco',
        'preco_custo',
        'estoque_minimo',
        'unidade',
        'tiny_id',
        'tiny_sync_at',
        'ativo',
        'legado_somente_leitura',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'legado_somente_leitura' => 'boolean',
            'preco' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'estoque_minimo' => 'integer',
            'tiny_sync_at' => 'datetime',
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
     * Exclui produtos criados só para migração legada (listagem catálogo / receitas novas).
     */
    public function scopeSemLegadoSomenteLeitura($query)
    {
        return $query->where('legado_somente_leitura', false);
    }

    /**
     * Normalize literal \n and /n to real newlines in text fields.
     */
    private function normalizeNewlines(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace(['\\n', '/n'], "\n", $value);
    }

    public function getDescricaoAttribute(?string $value): ?string
    {
        return $this->normalizeNewlines($value);
    }

    public function getModoUsoAttribute(?string $value): ?string
    {
        return $this->normalizeNewlines($value);
    }

    public function getAnotacoesAttribute(?string $value): ?string
    {
        return $this->normalizeNewlines($value);
    }

    public function getAnotacoesInternasAttribute(?string $value): ?string
    {
        return $this->normalizeNewlines($value);
    }

    /**
     * Get display name with code.
     */
    public function getNomeCompletoAttribute(): string
    {
        return "{$this->codigo} - {$this->nome}";
    }
}
