<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceitaItem extends Model
{
    use HasFactory;

    protected $table = 'receita_itens';

    protected $fillable = [
        'receita_id',
        'produto_id',
        'local_uso',
        'anotacoes',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'data_aquisicao',
        'imprimir',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'data_aquisicao' => 'date',
            'imprimir' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * Get the receita.
     */
    public function receita(): BelongsTo
    {
        return $this->belongsTo(Receita::class);
    }

    /**
     * Get the produto.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Get the aquisicoes (purchase history).
     */
    public function aquisicoes(): HasMany
    {
        return $this->hasMany(ReceitaItemAquisicao::class)->orderByDesc('data_aquisicao');
    }

    /**
     * Get the last acquisition date.
     */
    public function getUltimaAquisicaoAttribute(): ?\Carbon\Carbon
    {
        return $this->aquisicoes()->first()?->data_aquisicao ?? $this->data_aquisicao;
    }

    /**
     * Get all acquisition dates sorted by most recent.
     */
    public function getDatasAquisicaoAttribute(): array
    {
        $datas = $this->aquisicoes->pluck('data_aquisicao')->toArray();
        
        // Include legacy data_aquisicao if exists and not already in list
        if ($this->data_aquisicao && !in_array($this->data_aquisicao, $datas)) {
            $datas[] = $this->data_aquisicao;
        }
        
        return $datas;
    }

    /**
     * Calculate total on save.
     */
    protected static function booted(): void
    {
        static::saving(function (ReceitaItem $item) {
            $item->valor_total = $item->quantidade * $item->valor_unitario;
        });

        static::saved(function (ReceitaItem $item) {
            $item->receita->calcularTotais();
        });

        static::deleted(function (ReceitaItem $item) {
            $item->receita->calcularTotais();
        });
    }
}










