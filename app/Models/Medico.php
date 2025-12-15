<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medico extends Model
{
    use HasFactory;

    protected $table = 'medicos';

    protected $fillable = [
        'nome',
        'apelido',
        'crm',
        'cpf',
        'rg',
        'especialidade',
        'clinica_id',
        'tabela_preco_id',
        'telefone1',
        'telefone2',
        'telefone3',
        'email1',
        'email2',
        'tipo_endereco',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'cep',
        'rodape_receita',
        'assinatura_path',
        'anotacoes',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Get the clinica.
     */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    /**
     * Get the tabela de preco.
     */
    public function tabelaPreco(): BelongsTo
    {
        return $this->belongsTo(TabelaPreco::class, 'tabela_preco_id');
    }

    /**
     * Get the pacientes for this medico.
     */
    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    /**
     * Get the receitas for this medico.
     */
    public function receitas(): HasMany
    {
        return $this->hasMany(Receita::class);
    }

    /**
     * Get the users linked to this medico.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_medico');
    }

    /**
     * Get the atendimentos callcenter for this medico.
     */
    public function atendimentosCallcenter(): HasMany
    {
        return $this->hasMany(AtendimentoCallcenter::class);
    }

    /**
     * Scope for active records.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Get full name with CRM.
     */
    public function getNomeCompletoAttribute(): string
    {
        $nome = $this->nome;
        if ($this->crm) {
            $nome .= " - CRM: {$this->crm}";
        }
        return $nome;
    }
}




