<?php

namespace App\Services\Migration;

use App\Models\Medico;
use App\Models\User;
use Illuminate\Support\Collection;

class LegadoMedicoResolver
{
    /**
     * Resolve tokens (CRM, CPF, e-mail ou id CLW3) para médicos CLW3.
     *
     * @param  list<string|int>  $tokens
     * @return array{resolved: list<array{token:string,medico:Medico,match_by:string}>, unresolved: list<string>}
     */
    public function resolveMany(array $tokens): array
    {
        $resolved = [];
        $unresolved = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $hit = $this->resolveOne($token);
            if ($hit === null) {
                $unresolved[] = $token;
            } else {
                $resolved[] = $hit;
            }
        }

        return compact('resolved', 'unresolved');
    }

    /**
     * @return array{token:string,medico:Medico,match_by:string}|null
     */
    public function resolveOne(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (ctype_digit($token)) {
            $byId = Medico::find((int) $token);
            if ($byId) {
                return ['token' => $token, 'medico' => $byId, 'match_by' => 'id'];
            }
        }

        $digits = preg_replace('/\D+/', '', $token) ?? '';

        if (strlen($digits) === 11) {
            $byCpf = Medico::all(['id', 'apelido', 'nome_legado', 'crm', 'uf_crm', 'cpf', 'email1', 'ativo'])
                ->first(fn (Medico $m) => preg_replace('/\D+/', '', (string) $m->cpf) === $digits);
            if ($byCpf) {
                return ['token' => $token, 'medico' => Medico::find($byCpf->id), 'match_by' => 'cpf'];
            }
        }

        if (str_contains($token, '@')) {
            $user = User::where('email', $token)->orWhere('email', strtolower($token))->first();
            if ($user?->medico_id) {
                $m = Medico::find($user->medico_id);
                if ($m) {
                    return ['token' => $token, 'medico' => $m, 'match_by' => 'email'];
                }
            }
            $byEmail = Medico::where('email1', $token)->orWhere('email2', $token)->first();
            if ($byEmail) {
                return ['token' => $token, 'medico' => $byEmail, 'match_by' => 'email_medico'];
            }
        }

        $crmDigits = $this->crmDigits($token);
        if ($crmDigits !== '') {
            $all = Medico::all(['id', 'crm', 'uf_crm', 'cpf', 'apelido', 'nome_legado', 'email1', 'ativo']);
            $matches = $all->filter(fn (Medico $m) => $this->crmDigits((string) $m->crm) === $crmDigits);
            if ($matches->count() === 1) {
                return ['token' => $token, 'medico' => Medico::find($matches->first()->id), 'match_by' => 'crm'];
            }
            if ($matches->count() > 1) {
                $uf = $this->crmUf($token);
                if ($uf) {
                    $byUf = $matches->filter(function (Medico $m) use ($uf) {
                        return strtoupper((string) $m->uf_crm) === $uf
                            || str_contains(strtoupper((string) $m->crm), $uf);
                    });
                    if ($byUf->count() === 1) {
                        return ['token' => $token, 'medico' => Medico::find($byUf->first()->id), 'match_by' => 'crm_uf'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Casa médico CLW3 com registro do dump (medicos.json).
     *
     * @param  array<int, array<string, mixed>>  $legadoMedicos
     * @return array{legado: array<string,mixed>, match_by: string}|null
     */
    public function matchLegado(Medico $medico, array $legadoMedicos): ?array
    {
        $cpf = preg_replace('/\D+/', '', (string) $medico->cpf) ?? '';
        if (strlen($cpf) === 11) {
            foreach ($legadoMedicos as $lm) {
                $lcpf = preg_replace('/\D+/', '', (string) ($lm['cpf'] ?? '')) ?? '';
                if ($lcpf === $cpf) {
                    return ['legado' => $lm, 'match_by' => 'cpf'];
                }
            }
        }

        $crm = $this->crmDigits((string) $medico->crm);
        if ($crm !== '') {
            $hits = [];
            foreach ($legadoMedicos as $lm) {
                if ($this->crmDigits((string) ($lm['crm'] ?? '')) === $crm) {
                    $hits[] = $lm;
                }
            }
            if (count($hits) === 1) {
                return ['legado' => $hits[0], 'match_by' => 'crm'];
            }
            if (count($hits) > 1) {
                $uf = strtoupper((string) $medico->uf_crm);
                foreach ($hits as $lm) {
                    if ($uf !== '' && (
                        strtoupper((string) ($lm['uf_crm'] ?? '')) === $uf
                        || str_contains(strtoupper((string) ($lm['crm'] ?? '')), $uf)
                    )) {
                        return ['legado' => $lm, 'match_by' => 'crm_uf'];
                    }
                }
            }
        }

        $email = strtolower(trim((string) $medico->email1));
        if ($email !== '') {
            foreach ($legadoMedicos as $lm) {
                foreach (['email1', 'email2'] as $k) {
                    if (strtolower(trim((string) ($lm[$k] ?? ''))) === $email) {
                        return ['legado' => $lm, 'match_by' => 'email'];
                    }
                }
            }
        }

        $nome = mb_strtolower(trim((string) ($medico->nome_legado ?: $medico->apelido ?: $medico->nome)));
        if ($nome !== '') {
            foreach ($legadoMedicos as $lm) {
                $ln = mb_strtolower(trim((string) ($lm['nome_legado'] ?? $lm['apelido'] ?? '')));
                if ($ln !== '' && $ln === $nome) {
                    return ['legado' => $lm, 'match_by' => 'nome'];
                }
            }
        }

        return null;
    }

    public function crmDigits(string $crm): string
    {
        return preg_replace('/\D+/', '', $crm) ?? '';
    }

    public function crmUf(string $crm): ?string
    {
        if (preg_match('/\b([A-Z]{2})\b/i', $crm, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * @return Collection<int, Medico>
     */
    public function listActiveMedicos(): Collection
    {
        return Medico::query()
            ->where('ativo', true)
            ->with('linkedUser:id,name,medico_id')
            ->orderBy('nome_legado')
            ->orderBy('apelido')
            ->get(['id', 'apelido', 'nome_legado', 'crm', 'uf_crm', 'cpf', 'email1']);
    }
}
