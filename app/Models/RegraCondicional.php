<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegraCondicional extends Model
{
    protected $table = 'assistente_regras_condicionais';

    /**
     * Tipos de regra disponíveis.
     */
    public const TIPOS = [
        'selecao_tabela' => 'Seleção de Tabela',
        'modificacao_tabela' => 'Modificação de Tabela',
    ];

    protected $fillable = [
        'nome',
        'descricao',
        'tipo',
        'tabela_karnaugh_id',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    /**
     * Condições desta regra.
     */
    public function condicoes(): HasMany
    {
        return $this->hasMany(RegraCondicao::class, 'regra_id');
    }

    /**
     * Ações desta regra.
     */
    public function acoes(): HasMany
    {
        return $this->hasMany(RegraAcao::class, 'regra_id')->orderBy('ordem');
    }

    /**
     * Tabela Karnaugh alvo (para regras de modificação).
     */
    public function tabelaAlvo(): BelongsTo
    {
        return $this->belongsTo(TabelaKarnaugh::class, 'tabela_karnaugh_id');
    }

    /**
     * Scope para regras ativas.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope para ordenar por ordem.
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('ordem');
    }

    /**
     * Scope para regras de seleção de tabela.
     */
    public function scopeSelecaoTabela($query)
    {
        return $query->where('tipo', 'selecao_tabela');
    }

    /**
     * Scope para regras de modificação de tabela.
     */
    public function scopeModificacaoTabela($query)
    {
        return $query->where('tipo', 'modificacao_tabela');
    }

    /**
     * Scope para regras de uma tabela específica.
     */
    public function scopeParaTabela($query, int $tabelaId)
    {
        return $query->where('tabela_karnaugh_id', $tabelaId);
    }

    /**
     * Verifica se é uma regra de seleção de tabela.
     */
    public function isSelecaoTabela(): bool
    {
        return $this->tipo === 'selecao_tabela';
    }

    /**
     * Verifica se é uma regra de modificação de tabela.
     */
    public function isModificacaoTabela(): bool
    {
        return $this->tipo === 'modificacao_tabela';
    }

    /**
     * Verificar se as condições da regra são atendidas.
     *
     * @param array $avaliacaoClinica Dados da avaliação clínica
     * @return bool
     */
    public function verificarCondicoes(array $avaliacaoClinica): bool
    {
        foreach ($this->condicoes as $condicao) {
            if (!$condicao->verificar($avaliacaoClinica)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obter todas as regras ativas ordenadas com seus relacionamentos.
     */
    public static function getRegrasAtivas()
    {
        return static::ativo()
            ->ordenado()
            ->with(['condicoes', 'acoes.tabelaKarnaugh', 'acoes.produto', 'tabelaAlvo'])
            ->get();
    }

    /**
     * Obter regras ativas de seleção de tabela.
     */
    public static function getRegrasSelecaoTabela()
    {
        return static::ativo()
            ->selecaoTabela()
            ->ordenado()
            ->with(['condicoes', 'acoes.tabelaKarnaugh'])
            ->get();
    }

    /**
     * Obter regras ativas de modificação para uma tabela específica.
     */
    public static function getRegrasModificacaoParaTabela(int $tabelaId)
    {
        return static::ativo()
            ->modificacaoTabela()
            ->paraTabela($tabelaId)
            ->ordenado()
            ->with(['condicoes', 'acoes.produto'])
            ->get();
    }
}
