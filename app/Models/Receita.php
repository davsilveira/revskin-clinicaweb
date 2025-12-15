<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receita extends Model
{
    use HasFactory;

    protected $table = 'receitas';

    protected $fillable = [
        'numero',
        'data_receita',
        'paciente_id',
        'medico_id',
        'anotacoes',
        'anotacoes_paciente',
        'subtotal',
        'desconto_percentual',
        'desconto_valor',
        'desconto_motivo',
        'valor_caixa',
        'valor_frete',
        'valor_total',
        'status',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_receita' => 'date',
            'subtotal' => 'decimal:2',
            'desconto_percentual' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'valor_caixa' => 'decimal:2',
            'valor_frete' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'ativo' => 'boolean',
        ];
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
     * Get the itens.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(ReceitaItem::class)->orderBy('ordem');
    }

    /**
     * Get the atendimento callcenter.
     */
    public function atendimentoCallcenter(): HasOne
    {
        return $this->hasOne(AtendimentoCallcenter::class);
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
     * Calculate totals from items.
     */
    public function calcularTotais(): void
    {
        $subtotal = $this->itens->sum('valor_total');
        $this->subtotal = $subtotal;

        if ($this->desconto_percentual > 0) {
            $this->desconto_valor = $subtotal * ($this->desconto_percentual / 100);
        }

        $this->valor_total = $subtotal - $this->desconto_valor + $this->valor_frete;
        $this->save();
    }

    /**
     * Copy items from another receita.
     */
    public function copiarItensDeReceita(Receita $outraReceita): void
    {
        foreach ($outraReceita->itens as $item) {
            $this->itens()->create([
                'produto_id' => $item->produto_id,
                'local_uso' => $item->local_uso,
                'anotacoes' => $item->anotacoes,
                'quantidade' => $item->quantidade,
                'valor_unitario' => $item->valor_unitario,
                'valor_total' => $item->valor_total,
                'imprimir' => $item->imprimir,
                'ordem' => $item->ordem,
            ]);
        }
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'rascunho' => 'Rascunho',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => ucfirst($this->status),
        };
    }

    /**
     * Generate next number.
     */
    public static function gerarNumero(): string
    {
        $ultimo = static::whereYear('created_at', now()->year)
            ->orderByDesc('id')
            ->first();

        $sequencia = $ultimo ? ((int) substr($ultimo->numero, -6)) + 1 : 1;

        return now()->format('Y') . str_pad($sequencia, 6, '0', STR_PAD_LEFT);
    }
}




