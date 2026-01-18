<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabelaKarnaugh extends Model
{
    protected $table = 'tabelas_karnaugh';

    protected $fillable = [
        'nome',
        'descricao',
        'arquivo_original',
        'ativo',
        'padrao',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'padrao' => 'boolean',
    ];

    /**
     * Produtos desta tabela Karnaugh.
     */
    public function produtos(): HasMany
    {
        return $this->hasMany(TabelaKarnaughProduto::class);
    }

    /**
     * Scope para tabelas ativas.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Obter a tabela padrão.
     */
    public static function getPadrao(): ?self
    {
        return static::where('padrao', true)->where('ativo', true)->first();
    }

    /**
     * Definir esta tabela como padrão (remove padrão das outras).
     */
    public function definirComoPadrao(): void
    {
        static::where('id', '!=', $this->id)->update(['padrao' => false]);
        $this->update(['padrao' => true]);
    }

    /**
     * Buscar produtos para um caso clínico específico.
     */
    public function buscarProdutosPorCaso(string $casoClinico): array
    {
        $produtos = $this->produtos()
            ->where('caso_clinico', $casoClinico)
            ->orderBy('ordem')
            ->get();

        $resultado = [];
        foreach ($produtos as $produto) {
            $resultado[] = [
                'categoria' => $produto->categoria,
                'produto_codigo' => $produto->produto_codigo,
                'grupo' => $produto->grupo,
                'marcar' => $produto->marcar,
            ];
        }

        return $resultado;
    }
}
