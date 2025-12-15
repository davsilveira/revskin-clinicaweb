<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistenteCasoClinico extends Model
{
    use HasFactory;

    protected $table = 'assistente_casos_clinicos';

    protected $fillable = [
        'codigo',
        'tipo_pele',
        'manchas',
        'rugas',
        'acne',
        'flacidez',
        'faixa_etaria',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    // Options for each field
    public static function getTipoPeleOptions(): array
    {
        return ['Normal', 'Oleosa', 'Seca', 'Mista'];
    }

    public static function getIntensidadeOptions(): array
    {
        return ['Não', 'Leve', 'Moderado', 'Intenso'];
    }

    public static function getFaixaEtariaOptions(): array
    {
        return ['Até 30', '30-40', '40-50', '50-60', 'Acima de 60'];
    }

    /**
     * Get the tratamentos.
     */
    public function tratamentos(): HasMany
    {
        return $this->hasMany(AssistenteTratamento::class, 'caso_clinico_id')
            ->orderBy('ordem');
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Find matching case for given conditions.
     */
    public static function encontrarCaso(array $condicoes): ?self
    {
        $query = static::ativo();

        foreach (['tipo_pele', 'manchas', 'rugas', 'acne', 'flacidez', 'faixa_etaria'] as $campo) {
            if (!empty($condicoes[$campo])) {
                $query->where($campo, $condicoes[$campo]);
            }
        }

        return $query->first();
    }
}




