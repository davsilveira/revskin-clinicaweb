<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtendimentoCallcenter extends Model
{
    use HasFactory;

    protected $table = 'atendimentos_callcenter';

    protected $fillable = [
        'receita_id',
        'paciente_id',
        'medico_id',
        'status',
        'data_abertura',
        'data_alteracao',
        'usuario_id',
        'usuario_alteracao_id',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_abertura' => 'datetime',
            'data_alteracao' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    const STATUS_ENTRAR_EM_CONTATO = 'entrar_em_contato';
    const STATUS_AGUARDANDO_RETORNO = 'aguardando_retorno';
    const STATUS_EM_PRODUCAO = 'em_producao';
    const STATUS_FINALIZADO = 'finalizado';
    const STATUS_CANCELADO = 'cancelado';

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ENTRAR_EM_CONTATO => 'Entrar em Contato',
            self::STATUS_AGUARDANDO_RETORNO => 'Aguardando Retorno',
            self::STATUS_EM_PRODUCAO => 'Em Produção',
            self::STATUS_FINALIZADO => 'Finalizado',
            self::STATUS_CANCELADO => 'Cancelado',
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
     * Get the paciente.
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * Get the medico.
     */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    /**
     * Get the usuario who created.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Get the usuario who last modified.
     */
    public function usuarioAlteracao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_alteracao_id');
    }

    /**
     * Get the acompanhamentos.
     */
    public function acompanhamentos(): HasMany
    {
        return $this->hasMany(AcompanhamentoCallcenter::class, 'atendimento_id')
            ->orderByDesc('data_registro');
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Update status with tracking.
     */
    public function atualizarStatus(string $novoStatus, User $usuario, ?string $descricao = null): void
    {
        $this->status = $novoStatus;
        $this->data_alteracao = now();
        $this->usuario_alteracao_id = $usuario->id;
        $this->save();

        if ($descricao) {
            $this->acompanhamentos()->create([
                'usuario_id' => $usuario->id,
                'descricao' => $descricao,
                'data_registro' => now(),
            ]);
        }
    }
}

