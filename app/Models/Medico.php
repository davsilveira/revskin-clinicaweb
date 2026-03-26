<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medico extends Model
{
    use HasFactory;

    protected $table = 'medicos';

    protected $fillable = [
        'apelido',
        'crm',
        'uf_crm',
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
     * Clínica para cabeçalho da receita (PDF): FK legada ou primeira do vínculo N:N (por nome).
     */
    public function clinicaParaReceita(): ?Clinica
    {
        if ($this->clinica_id) {
            if (! $this->relationLoaded('clinica')) {
                $this->load('clinica');
            }

            return $this->clinica;
        }

        if (! $this->relationLoaded('clinicas')) {
            return $this->clinicas()->orderBy('clinicas.nome')->first();
        }

        return $this->clinicas->sortBy('nome')->first();
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
     * Get the primary user (via medico_id).
     */
    public function linkedUser(): HasOne
    {
        return $this->hasOne(User::class, 'medico_id');
    }

    /**
     * Get the enderecos for this medico.
     */
    public function enderecos(): HasMany
    {
        return $this->hasMany(MedicoEndereco::class);
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
     * Get nome from linked User (fonte unica).
     */
    public function getNomeAttribute(): ?string
    {
        return $this->linkedUser?->name;
    }

    /**
     * Get full name with CRM.
     */
    public function getNomeCompletoAttribute(): string
    {
        $nome = $this->nome ?? '';
        if ($this->crm) {
            $nome .= ($nome ? ' - ' : '')."CRM: {$this->crm}";
        }

        return $nome;
    }

    /**
     * Get the signature URL.
     */
    public function getAssinaturaUrlAttribute(): ?string
    {
        if ($this->assinatura_path) {
            return asset('storage/'.$this->assinatura_path);
        }

        return null;
    }

    /**
     * Append accessors to JSON (nome vem de linkedUser->name).
     */
    protected $appends = ['assinatura_url', 'nome'];
}
