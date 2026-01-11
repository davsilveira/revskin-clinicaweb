<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabelaPrecoItem extends Model
{
    use HasFactory;

    protected $table = 'tabela_preco_itens';

    protected $fillable = [
        'tabela_preco_id',
        'produto_id',
        'preco',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
        ];
    }

    /**
     * Get the tabela de preco.
     */
    public function tabelaPreco(): BelongsTo
    {
        return $this->belongsTo(TabelaPreco::class, 'tabela_preco_id');
    }

    /**
     * Get the produto.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}










