<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistenteTratamentoItem extends Model
{
    use HasFactory;

    protected $table = 'assistente_tratamento_itens';

    protected $fillable = [
        'tratamento_id',
        'produto_id',
        'local_uso',
        'anotacoes',
        'quantidade',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the tratamento.
     */
    public function tratamento(): BelongsTo
    {
        return $this->belongsTo(AssistenteTratamento::class, 'tratamento_id');
    }

    /**
     * Get the produto.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}




