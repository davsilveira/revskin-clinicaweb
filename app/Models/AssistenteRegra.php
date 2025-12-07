<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistenteRegra extends Model
{
    use HasFactory;

    protected $table = 'assistente_regras';

    protected $fillable = [
        'linha_id',
        'condicoes',
        'produtos',
        'observacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'condicoes' => 'array',
            'produtos' => 'array',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Find matching rules for given conditions.
     */
    public static function encontrarRegras(array $condicoes): \Illuminate\Database\Eloquent\Collection
    {
        return static::ativo()
            ->get()
            ->filter(function ($regra) use ($condicoes) {
                foreach ($regra->condicoes as $campo => $valor) {
                    if (!empty($valor) && ($condicoes[$campo] ?? null) !== $valor) {
                        return false;
                    }
                }
                return true;
            });
    }

    /**
     * Get produtos from rules.
     */
    public function getProdutosModels(): \Illuminate\Database\Eloquent\Collection
    {
        $produtoIds = collect($this->produtos)->pluck('produto_id')->filter();
        return Produto::whereIn('id', $produtoIds)->get();
    }
}

