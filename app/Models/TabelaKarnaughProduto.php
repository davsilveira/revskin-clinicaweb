<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabelaKarnaughProduto extends Model
{
    protected $table = 'tabela_karnaugh_produtos';

    protected $fillable = [
        'tabela_karnaugh_id',
        'caso_clinico',
        'categoria',
        'produto_codigo',
        'grupo',
        'marcar',
        'ordem',
        'sequencia_coluna',
    ];

    protected $casts = [
        'marcar' => 'boolean',
        'ordem' => 'integer',
        'sequencia_coluna' => 'integer',
    ];

    /**
     * Tabela Karnaugh a qual pertence.
     */
    public function tabelaKarnaugh(): BelongsTo
    {
        return $this->belongsTo(TabelaKarnaugh::class);
    }

    /**
     * Verifica se é do primeiro grupo (produtos recomendados/marcados).
     */
    public function isPrimeiroGrupo(): bool
    {
        return $this->grupo === 'primeiro';
    }

    /**
     * Verifica se é do segundo grupo (produtos opcionais/não marcados).
     */
    public function isSegundoGrupo(): bool
    {
        return $this->grupo === 'segundo';
    }
}
