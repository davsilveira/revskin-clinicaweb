<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistenteTratamento extends Model
{
    use HasFactory;

    protected $table = 'assistente_tratamentos';

    protected $fillable = [
        'caso_clinico_id',
        'codigo',
        'nome',
        'descricao',
        'ordem',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the caso clinico.
     */
    public function casoClinico(): BelongsTo
    {
        return $this->belongsTo(AssistenteCasoClinico::class, 'caso_clinico_id');
    }

    /**
     * Get the itens.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(AssistenteTratamentoItem::class, 'tratamento_id')
            ->orderBy('ordem');
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}

