<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcompanhamentoCallcenter extends Model
{
    use HasFactory;

    protected $table = 'acompanhamentos_callcenter';

    protected $fillable = [
        'atendimento_id',
        'usuario_id',
        'descricao',
        'data_registro',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_registro' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the atendimento.
     */
    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(AtendimentoCallcenter::class, 'atendimento_id');
    }

    /**
     * Get the usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}

