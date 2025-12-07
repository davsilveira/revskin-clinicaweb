<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_MEDICO = 'medico';
    const ROLE_CALLCENTER = 'callcenter';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'medico_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get available roles.
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_MEDICO => 'Médico',
            self::ROLE_CALLCENTER => 'Call Center',
        ];
    }

    /**
     * Role check methods
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isMedico(): bool
    {
        return $this->role === self::ROLE_MEDICO;
    }

    public function isCallcenter(): bool
    {
        return $this->role === self::ROLE_CALLCENTER;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get the linked medico (direct relationship).
     */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    /**
     * Get medicos linked via pivot (for admin managing multiple medicos).
     */
    public function medicos(): BelongsToMany
    {
        return $this->belongsToMany(Medico::class, 'user_medico');
    }

    /**
     * Get atendimentos created by this user.
     */
    public function atendimentosCriados(): HasMany
    {
        return $this->hasMany(AtendimentoCallcenter::class, 'usuario_id');
    }

    /**
     * Get atendimentos modified by this user.
     */
    public function atendimentosModificados(): HasMany
    {
        return $this->hasMany(AtendimentoCallcenter::class, 'usuario_alteracao_id');
    }

    /**
     * Get acompanhamentos created by this user.
     */
    public function acompanhamentos(): HasMany
    {
        return $this->hasMany(AcompanhamentoCallcenter::class, 'usuario_id');
    }

    /**
     * Get role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return self::getRoles()[$this->role] ?? ucfirst($this->role);
    }

    /**
     * Check if user can access medico data.
     */
    public function canAccessMedico(Medico $medico): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isMedico() && $this->medico_id === $medico->id) {
            return true;
        }

        return $this->medicos()->where('medicos.id', $medico->id)->exists();
    }

    /**
     * Check if user can access paciente data.
     */
    public function canAccessPaciente(Paciente $paciente): bool
    {
        if ($this->isAdmin() || $this->isCallcenter()) {
            return true;
        }

        if ($this->isMedico()) {
            return $paciente->medico_id === $this->medico_id;
        }

        return false;
    }
}
