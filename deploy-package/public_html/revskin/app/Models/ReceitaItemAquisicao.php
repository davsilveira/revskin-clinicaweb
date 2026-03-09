<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceitaItemAquisicao extends Model
{
    use HasFactory;

    protected $table = 'receita_item_aquisicoes';

    protected $fillable = [
        'receita_item_id',
        'data_aquisicao',
        'atendimento_id',
    ];

    protected function casts(): array
    {
        return [
            'data_aquisicao' => 'date',
        ];
    }

    /**
     * Get the receita item.
     */
    public function receitaItem(): BelongsTo
    {
        return $this->belongsTo(ReceitaItem::class);
    }

    /**
     * Get the atendimento.
     */
    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(AtendimentoCallcenter::class, 'atendimento_id');
    }
}
