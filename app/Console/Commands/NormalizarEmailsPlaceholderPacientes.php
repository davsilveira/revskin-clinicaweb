<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use App\Support\EmailPlaceholder;
use Illuminate\Console\Command;

/**
 * Conserta os cadastros travados pelo e-mail de marcação em domínio inválido.
 *
 * Levantado no job e2c44ae4: ~150 pacientes têm `…@cadastrar_email.com` — domínio com
 * underline, que não é e-mail válido. Hoje o médico abre um desses cadastros, muda
 * qualquer coisa e o salvamento devolve 422 ("Informe um e-mail válido"), com o autosave
 * repetindo o erro a cada 2 s. Aqui todos passam para `@cadastraremail.rsk`.
 *
 * Sem eventos de propósito: gravar disparando o observer empurraria as ~150 alterações de
 * volta para o oList numa tacada só, o que não é o objetivo (e estouraria o rate limit).
 */
class NormalizarEmailsPlaceholderPacientes extends Command
{
    protected $signature = 'pacientes:normalizar-emails-placeholder
                            {--apply : Grava as alterações (sem esta opção só lista o que mudaria)}';

    protected $description = 'Normaliza e-mails de marcação dos pacientes para @'.EmailPlaceholder::DOMINIO;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $alvos = Paciente::query()
            ->where(function ($q) {
                foreach (EmailPlaceholder::DOMINIOS as $dominio) {
                    if ($dominio === EmailPlaceholder::DOMINIO) {
                        continue; // já está no formato final
                    }
                    $q->orWhere('email1', 'like', '%@'.$dominio)
                        ->orWhere('email2', 'like', '%@'.$dominio);
                }
            })
            ->orderBy('id')
            ->get(['id', 'nome', 'email1', 'email2']);

        if ($alvos->isEmpty()) {
            $this->info('Nenhum e-mail de marcação para normalizar.');

            return self::SUCCESS;
        }

        $linhas = [];
        $alterados = 0;

        foreach ($alvos as $paciente) {
            $novo = [];
            foreach (['email1', 'email2'] as $campo) {
                $atual = $paciente->{$campo};
                $normalizado = EmailPlaceholder::normalizar($atual);
                if ($normalizado !== $atual) {
                    $novo[$campo] = $normalizado;
                }
            }

            if ($novo === []) {
                continue;
            }

            $linhas[] = [
                $paciente->id,
                mb_substr((string) $paciente->nome, 0, 30),
                (string) $paciente->email1,
                (string) ($novo['email1'] ?? $paciente->email1),
            ];
            $alterados++;

            if ($apply) {
                Paciente::withoutEvents(fn () => $paciente->forceFill($novo)->save());
            }
        }

        $this->table(['id', 'nome', 'de', 'para'], $linhas);
        $this->info(($apply ? 'Normalizados ' : 'Normalizaria ').$alterados.' paciente(s).');

        if (! $apply) {
            $this->comment('Rode de novo com --apply para gravar.');
        }

        return self::SUCCESS;
    }
}
