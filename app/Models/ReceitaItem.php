<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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










