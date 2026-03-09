<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraCondicao extends Model
{
    protected $table = 'assistente_regra_condicoes';

    protected $fillable = [
        'regra_id',
        'campo',
        'operador',
        'valor',
    ];

    /**
     * Campos disponíveis para condições.
     */
    public const CAMPOS_DISPONIVEIS = [
        'gravidez' => 'Gravidez',
        'rosacea' => 'Rosácea',
        'fototipo' => 'Fototipo',
        'tipo_pele' => 'Tipo de Pele',
        'manchas' => 'Manchas',
        'rugas' => 'Rugas',
        'acne' => 'Acne',
        'flacidez' => 'Flacidez',
    ];

    /**
     * Operadores disponíveis.
     */
    public const OPERADORES = [
        'igual' => 'Igual a',
        'diferente' => 'Diferente de',
        'qualquer' => 'Qualquer valor',
    ];

    /**
     * Regra a qual pertence.
     */
    public function regra(): BelongsTo
    {
        return $this->belongsTo(RegraCondicional::class, 'regra_id');
    }

    /**
     * Verificar se a condição é atendida.
     *
     * @param array $avaliacaoClinica
     * @return bool
     */
    public function verificar(array $avaliacaoClinica): bool
    {
        $valorAvaliacao = $avaliacaoClinica[$this->campo] ?? null;

        switch ($this->operador) {
            case 'qualquer':
                return true;

            case 'diferente':
                return $valorAvaliacao !== $this->valor;

            case 'igual':
            default:
                return $valorAvaliacao === $this->valor;
        }
    }

    /**
     * Obter label do campo.
     */
    public function getCampoLabelAttribute(): string
    {
        return self::CAMPOS_DISPONIVEIS[$this->campo] ?? $this->campo;
    }

    /**
     * Obter label do operador.
     */
    public function getOperadorLabelAttribute(): string
    {
        return self::OPERADORES[$this->operador] ?? $this->operador;
    }
}
