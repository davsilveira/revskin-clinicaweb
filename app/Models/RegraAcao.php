<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraAcao extends Model
{
    protected $table = 'assistente_regra_acoes';

    protected $fillable = [
        'regra_id',
        'tipo_acao',
        'tabela_karnaugh_id',
        'produto_id',
        'marcar',
        'quantidade',
        'categoria',
        'ordem',
    ];

    protected $casts = [
        'marcar' => 'boolean',
        'quantidade' => 'integer',
        'ordem' => 'integer',
    ];

    /**
     * Tipos de ação disponíveis.
     */
    public const TIPOS_ACAO = [
        'usar_tabela' => 'Usar Tabela Karnaugh',
        'adicionar_item' => 'Adicionar Item',
        'remover_item' => 'Remover Item',
        'modificar_quantidade' => 'Modificar Quantidade',
        'alterar_marcacao' => 'Alterar Marcação',
    ];

    /**
     * Regra a qual pertence.
     */
    public function regra(): BelongsTo
    {
        return $this->belongsTo(RegraCondicional::class, 'regra_id');
    }

    /**
     * Tabela Karnaugh associada (para ação usar_tabela).
     */
    public function tabelaKarnaugh(): BelongsTo
    {
        return $this->belongsTo(TabelaKarnaugh::class, 'tabela_karnaugh_id');
    }

    /**
     * Produto associado (para ações adicionar/remover_item).
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    /**
     * Verifica se é uma ação de usar tabela.
     */
    public function isUsarTabela(): bool
    {
        return $this->tipo_acao === 'usar_tabela';
    }

    /**
     * Verifica se é uma ação de adicionar item.
     */
    public function isAdicionarItem(): bool
    {
        return $this->tipo_acao === 'adicionar_item';
    }

    /**
     * Verifica se é uma ação de remover item.
     */
    public function isRemoverItem(): bool
    {
        return $this->tipo_acao === 'remover_item';
    }

    /**
     * Verifica se é uma ação de modificar quantidade.
     */
    public function isModificarQuantidade(): bool
    {
        return $this->tipo_acao === 'modificar_quantidade';
    }

    /**
     * Verifica se é uma ação de alterar marcação.
     */
    public function isAlterarMarcacao(): bool
    {
        return $this->tipo_acao === 'alterar_marcacao';
    }

    /**
     * Obter label do tipo de ação.
     */
    public function getTipoAcaoLabelAttribute(): string
    {
        return self::TIPOS_ACAO[$this->tipo_acao] ?? $this->tipo_acao;
    }
}
