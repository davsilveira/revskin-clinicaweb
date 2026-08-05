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
        'legado_id',
        'numero',
        'numero_origem',
        'origem',
        'data_receita',
        'paciente_id',
        'medico_id',
        'receita_origem_id',
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
        'cortesia',
        'ativo',
        'tiny_pedido_id',
        'rd_deal_id',
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
            'cortesia' => 'boolean',
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
     * Receita a partir da qual esta foi criada (duplicação / copiar).
     */
    public function receitaOrigem(): BelongsTo
    {
        return $this->belongsTo(Receita::class, 'receita_origem_id');
    }

    /**
     * Receitas criadas a partir desta.
     */
    public function receitasDuplicadas(): HasMany
    {
        return $this->hasMany(Receita::class, 'receita_origem_id');
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
     * Load items for the printable PDF.
     *
     * When any item was commercialized via oList (vendido), only those items
     * are included. Otherwise, every item marked for print (imprimir) is used.
     */
    public function carregarItensParaPdf(): self
    {
        $temComercializados = $this->itens()->where('vendido', true)->exists();

        $this->load([
            'itens' => function ($q) use ($temComercializados) {
                if ($temComercializados) {
                    $q->where('vendido', true);
                } else {
                    $q->where('imprimir', true);
                }
                $q->with('produto');
            },
        ]);

        return $this;
    }

    /**
     * Calculate totals from items.
     */
    public function calcularTotais(): void
    {
        $this->load('itens');

        $subtotal = $this->itens->where('imprimir', true)->sum('valor_total');
        $this->subtotal = $subtotal;

        if ($this->desconto_percentual > 0) {
            $this->desconto_valor = $subtotal * ($this->desconto_percentual / 100);
        } else {
            $this->desconto_valor = 0;
        }

        $desconto = (float) $this->desconto_valor;
        $frete = (float) ($this->valor_frete ?? 0);
        $caixa = (float) ($this->valor_caixa ?? 0);

        $this->valor_total = $subtotal - $desconto + $frete + $caixa;
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
                'grupo' => $item->grupo,
            ]);
        }
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'aberta' => 'Aberta',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => ucfirst($this->status),
        };
    }

    /**
     * Rótulo da venda no ERP (oList/Tiny) para relatórios.
     *
     * Uma receita finalizada é "Vendido" quando ao menos um item foi
     * comercializado (item->vendido = true, marcado pela sincronia do pedido
     * faturado). Se ainda não houver pedido faturado, é "Aberto".
     * Para receitas ainda "aberta", a venda não se aplica (retorna null).
     *
     * Requer itens_vendidos_count carregado via withCount; sem ele, consulta
     * os itens diretamente.
     */
    public function getVendaLabelAttribute(): ?string
    {
        if ($this->status !== 'finalizada') {
            return null;
        }

        $vendidos = $this->itens_vendidos_count
            ?? $this->itens()->where('vendido', true)->count();

        return $vendidos > 0 ? 'Vendido' : 'Aberto';
    }

    /**
     * Generate next number.
     */
    public static function gerarNumero(int $pacienteId): string
    {
        $max = 0;
        foreach (static::where('paciente_id', $pacienteId)->pluck('numero') as $numero) {
            if (preg_match('/-(\d+)\s*$/', (string) $numero, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $pacienteId.'-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Renumerar sequência contínua do paciente por data_receita (+ id).
     * Preserva numero_origem quando ainda vazio e o número antigo era legado.
     */
    public static function renumerarPorData(int $pacienteId): int
    {
        $receitas = static::where('paciente_id', $pacienteId)
            ->orderBy('data_receita')
            ->orderBy('id')
            ->get();

        $n = 0;
        foreach ($receitas as $receita) {
            $n++;
            $novo = $pacienteId.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            if ($receita->numero === $novo) {
                continue;
            }
            $receita->numero = $novo;
            $receita->saveQuietly();
        }

        return $n;
    }
}
