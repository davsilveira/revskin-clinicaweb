<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    use HasFactory;

    protected $table = 'pacientes';

    protected $fillable = [
        'codigo',
        'nome',
        'data_nascimento',
        'sexo',
        'fototipo',
        'cpf',
        'rg',
        'telefone1',
        'celular',
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
        'pais',
        'cep',
        'indicado_por',
        'anotacoes',
        'medico_id',
        'tiny_id',
        'tiny_sync_at',
        'tiny_updated_at',
        'rd_organization_id',
        'rd_contact_id',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'ativo' => 'boolean',
            'tiny_sync_at' => 'datetime',
            'tiny_updated_at' => 'datetime',
        ];
    }

    /**
     * Get the medico (legado: FK única de origem/primeiro vínculo).
     */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    /**
     * Médicos vinculados (Opção 2 — N:N). Carrega os campos privados por médico.
     */
    public function medicos(): BelongsToMany
    {
        return $this->belongsToMany(Medico::class, 'medico_paciente')
            ->using(MedicoPaciente::class)
            ->withPivot(['id', 'anotacoes', 'codigo', 'indicado_por', 'ativo', 'origem', 'created_by_user_id', 'updated_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Retorna o vínculo (pivot) do paciente com um médico específico, ou null.
     */
    public function vinculoDoMedico(int $medicoId): ?MedicoPaciente
    {
        $medico = $this->medicos()->wherePivot('medico_id', $medicoId)->first();

        return $medico?->pivot instanceof MedicoPaciente ? $medico->pivot : null;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Get the receitas.
     */
    public function receitas(): HasMany
    {
        return $this->hasMany(Receita::class);
    }

    /**
     * Get the telefones.
     */
    public function telefones(): HasMany
    {
        return $this->hasMany(PacienteTelefone::class);
    }

    /**
     * Get the atendimentos callcenter.
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
     * Get calculated age.
     */
    public function getIdadeAttribute(): ?int
    {
        if (!$this->data_nascimento) {
            return null;
        }
        return $this->data_nascimento->age;
    }

    /**
     * Get telefone principal.
     */
    public function getTelefonePrincipalAttribute(): ?string
    {
        return $this->telefone1 ?? $this->celular ?? $this->telefone3;
    }

    /**
     * Get email principal.
     */
    public function getEmailPrincipalAttribute(): ?string
    {
        return $this->email1 ?? $this->email2;
    }

    /**
     * Get endereco completo.
     */
    public function getEnderecoCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->endereco,
            $this->numero ? "nº {$this->numero}" : null,
            $this->complemento,
            $this->bairro,
            $this->cidade,
            $this->uf,
            $this->cep ? "CEP: {$this->cep}" : null,
        ]);
        return implode(', ', $partes);
    }
}










