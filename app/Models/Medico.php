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
     * Get the clinica (legacy - direct FK).
     */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    /**
     * Get the clinicas (many-to-many).
     */
    public function clinicas(): BelongsToMany
    {
        return $this->belongsToMany(Clinica::class, 'clinica_medico')
            ->withTimestamps();
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










