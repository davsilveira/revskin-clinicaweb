<?php

namespace App\Console\Commands;

use App\Models\Medico;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara o efeito colateral do bug de troca de papel médico -> admin -> médico:
 * ao voltar para médico era criado um médico NOVO e vazio, deixando os pacientes
 * (e receitas) presos num médico ANTIGO, agora órfão (sem usuário vinculado).
 *
 * Este comando move os pacientes/receitas/atendimentos do médico órfão (origem)
 * de volta para o médico ao qual o usuário está atualmente vinculado (destino),
 * fazendo os pacientes reaparecerem para a médica sem mexer no login nem no
 * perfil/CRM atual dela.
 *
 * É seguro por padrão: roda em modo simulação (dry-run) e só aplica com --force.
 */
class ReestabelecerPacientesMedico extends Command
{
    protected $signature = 'medicos:reestabelecer-pacientes
                            {email : E-mail do usuário médico afetado (ex.: giovana.naccarato@revskin.com.br)}
                            {--origem= : ID do médico de origem (órfão). Auto-detecta por CRM se omitido.}
                            {--apagar-origem : Remove o médico de origem depois de mover tudo (opcional).}
                            {--force : Aplica de fato. Sem esta flag, apenas simula (dry-run).}';

    protected $description = 'Reestabelece os pacientes de uma médica presos num médico órfão (bug de troca de papel), movendo-os para o médico atual do usuário.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $force = (bool) $this->option('force');

        if (! $force) {
            $this->warn('MODO SIMULAÇÃO (dry-run). Nada será alterado. Use --force para aplicar.');
            $this->newLine();
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Usuário não encontrado para o e-mail: {$email}");

            return self::FAILURE;
        }

        if (! $user->medico_id) {
            $this->error("O usuário #{$user->id} ({$user->name}) não está vinculado a nenhum médico (medico_id nulo). Corrija o vínculo primeiro (ex.: editar o usuário como médico no painel).");

            return self::FAILURE;
        }

        $destino = Medico::find($user->medico_id);
        if (! $destino) {
            $this->error("O medico_id={$user->medico_id} do usuário aponta para um médico inexistente. Rode 'migration:audit-medico-links --fix' primeiro.");

            return self::FAILURE;
        }

        $this->line("Usuário:  #{$user->id} {$user->name} <{$user->email}> (papel: {$user->role})");
        $this->line("Destino:  médico #{$destino->id} (CRM ".($destino->crm ?? '—').') — pacientes atuais: '.$this->countPacientes($destino->id));
        $this->newLine();

        // Resolve o médico de origem (órfão que segura os pacientes)
        $origem = $this->resolverOrigem($destino);
        if ($origem instanceof Command) {
            // sinalizador de erro já emitido
            return self::FAILURE;
        }
        if ($origem === null) {
            $this->info('Nenhum médico órfão correspondente foi encontrado por CRM.');
            $this->imprimirDiagnostico($user, $destino);

            return self::SUCCESS;
        }

        if ((int) $origem->id === (int) $destino->id) {
            $this->error('Origem e destino são o mesmo médico — nada a mover.');

            return self::FAILURE;
        }

        $pac = $this->countPacientes($origem->id);
        $rec = $this->countReceitas($origem->id);
        $atd = $this->countAtendimentos($origem->id);

        $this->line("Origem:   médico #{$origem->id} (CRM ".($origem->crm ?? '—').', nome_legado: '.($origem->nome_legado ?? '—').')');
        $this->line("          → pacientes: {$pac} | receitas: {$rec} | atendimentos: {$atd}");
        $this->newLine();

        if ($pac === 0 && $rec === 0 && $atd === 0) {
            $this->info('O médico de origem não tem pacientes/receitas/atendimentos a mover. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->line("PLANO: mover {$pac} paciente(s), {$rec} receita(s) e {$atd} atendimento(s) do médico #{$origem->id} → #{$destino->id}.");
        if ($this->option('apagar-origem')) {
            $this->line("       e, em seguida, APAGAR o médico de origem #{$origem->id}.");
        } else {
            $this->line("       o médico de origem #{$origem->id} será mantido (ficará sem pacientes/receitas).");
        }
        $this->newLine();

        if (! $force) {
            $this->warn('Simulação concluída. Reveja o plano acima e rode novamente com --force para aplicar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($origem, $destino) {
            DB::table('pacientes')->where('medico_id', $origem->id)->update(['medico_id' => $destino->id]);
            DB::table('receitas')->where('medico_id', $origem->id)->update(['medico_id' => $destino->id]);
            if (Schema::hasTable('atendimentos_callcenter')) {
                DB::table('atendimentos_callcenter')->where('medico_id', $origem->id)->update(['medico_id' => $destino->id]);
            }

            if ($this->option('apagar-origem')) {
                // Só apaga se realmente não sobrou nada apontando para a origem.
                $resto = $this->countPacientes($origem->id) + $this->countReceitas($origem->id) + $this->countAtendimentos($origem->id);
                if ($resto === 0) {
                    DB::table('medicos')->where('id', $origem->id)->delete();
                }
            }
        });

        $this->info('Aplicado com sucesso.');
        $this->line("Destino #{$destino->id} agora tem ".$this->countPacientes($destino->id).' paciente(s) e '.$this->countReceitas($destino->id).' receita(s).');
        if ($this->option('apagar-origem')) {
            $this->line('Médico de origem '.(Medico::find($origem->id) ? "#{$origem->id} mantido (havia registros remanescentes)" : 'removido').'.');
        }

        return self::SUCCESS;
    }

    /**
     * Determina o médico de origem (órfão). Retorna Medico, null (nada a fazer)
     * ou $this (erro — mensagem já emitida) para abortar.
     */
    private function resolverOrigem(Medico $destino): Medico|Command|null
    {
        $explicito = $this->option('origem');
        if ($explicito !== null && $explicito !== '') {
            $origem = Medico::find((int) $explicito);
            if (! $origem) {
                $this->error("Médico de origem #{$explicito} (--origem) não existe.");

                return $this;
            }
            if ($this->temUsuarioVinculado((int) $origem->id)) {
                $this->error("Médico de origem #{$origem->id} está vinculado a um usuário — não é um órfão. Revise manualmente antes de mover.");

                return $this;
            }

            return $origem;
        }

        // Auto-detecção: médicos órfãos (sem usuário) com pacientes, cujo CRM
        // (apenas dígitos) bate com o do médico destino.
        $crmDestino = $this->crmDigits($destino->crm);
        if ($crmDestino === '') {
            $this->error('Não foi possível auto-detectar a origem: o médico destino não tem CRM para casar. Informe --origem=ID explicitamente.');

            return $this;
        }

        $candidatos = [];
        foreach (Medico::query()->where('id', '!=', $destino->id)->cursor() as $m) {
            if ($this->crmDigits($m->crm) !== $crmDestino) {
                continue;
            }
            if ($this->temUsuarioVinculado((int) $m->id)) {
                continue;
            }
            if ($this->countPacientes((int) $m->id) === 0 && $this->countReceitas((int) $m->id) === 0) {
                continue;
            }
            $candidatos[(int) $m->id] = $m;
        }

        if (count($candidatos) === 0) {
            return null;
        }

        if (count($candidatos) > 1) {
            $ids = implode(', ', array_keys($candidatos));
            $this->error("Mais de um médico órfão com o mesmo CRM ({$crmDestino}) foi encontrado: [{$ids}]. Informe --origem=ID explicitamente.");

            return $this;
        }

        return array_values($candidatos)[0];
    }

    /**
     * Quando a auto-detecção por CRM falha, ajuda o operador a achar a origem:
     * lista médicos relacionados ao nome do usuário (nome_legado/apelido) e
     * médicos órfãos com pacientes, com contagens e o usuário vinculado.
     */
    private function imprimirDiagnostico(User $user, Medico $destino): void
    {
        $this->newLine();
        $this->line('DIAGNÓSTICO (somente leitura) — para escolher --origem=ID manualmente:');

        // Tokens significativos do nome do usuário (>= 4 letras)
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower(trim((string) $user->name), 'UTF-8')) ?: [],
            fn ($t) => mb_strlen($t, 'UTF-8') >= 4
        ));

        $medicos = Medico::query()->where('id', '!=', $destino->id)->get();
        $relacionados = $medicos->filter(function (Medico $m) use ($tokens) {
            $hay = mb_strtolower((string) ($m->nome_legado ?? '').' '.($m->apelido ?? ''), 'UTF-8');
            foreach ($tokens as $t) {
                if ($t !== '' && str_contains($hay, $t)) {
                    return true;
                }
            }

            return false;
        });

        if ($relacionados->isEmpty()) {
            $this->line('  (nenhum médico com nome_legado/apelido parecido com "'.$user->name.'")');
        } else {
            $this->line('  Médicos com nome parecido com "'.$user->name.'":');
            foreach ($relacionados as $m) {
                $vinc = User::where('medico_id', $m->id)->pluck('email')->implode(', ');
                $this->line(sprintf(
                    '   - médico #%d | CRM %s | nome_legado="%s" | apelido="%s" | pacientes=%d receitas=%d | usuário=[%s]',
                    $m->id,
                    $m->crm ?? '—',
                    $m->nome_legado ?? '',
                    $m->apelido ?? '',
                    $this->countPacientes($m->id),
                    $this->countReceitas($m->id),
                    $vinc !== '' ? $vinc : 'nenhum'
                ));
            }
        }

        // Órfãos (sem usuário) com pacientes — candidatos naturais a origem
        $this->newLine();
        $this->line('  Médicos ÓRFÃOS (sem usuário) com pacientes:');
        $achouOrfao = false;
        foreach ($medicos as $m) {
            if ($this->temUsuarioVinculado((int) $m->id)) {
                continue;
            }
            $pac = $this->countPacientes((int) $m->id);
            if ($pac === 0) {
                continue;
            }
            $achouOrfao = true;
            $this->line(sprintf(
                '   - médico #%d | CRM %s | nome_legado="%s" | pacientes=%d receitas=%d',
                $m->id,
                $m->crm ?? '—',
                $m->nome_legado ?? '',
                $pac,
                $this->countReceitas((int) $m->id)
            ));
        }
        if (! $achouOrfao) {
            $this->line('   (nenhum médico órfão com pacientes)');
        }

        $this->newLine();
        $this->line('Se identificar a origem correta acima, rode com --origem=<ID> (e --force para aplicar).');
    }

    private function temUsuarioVinculado(int $medicoId): bool
    {
        if (User::where('medico_id', $medicoId)->exists()) {
            return true;
        }

        return DB::table('user_medico')->where('medico_id', $medicoId)->exists();
    }

    private function crmDigits(?string $crm): string
    {
        return preg_replace('/\D+/', '', (string) $crm);
    }

    private function countPacientes(int $medicoId): int
    {
        return (int) DB::table('pacientes')->where('medico_id', $medicoId)->count();
    }

    private function countReceitas(int $medicoId): int
    {
        return (int) DB::table('receitas')->where('medico_id', $medicoId)->count();
    }

    private function countAtendimentos(int $medicoId): int
    {
        if (! Schema::hasTable('atendimentos_callcenter')) {
            return 0;
        }

        return (int) DB::table('atendimentos_callcenter')->where('medico_id', $medicoId)->count();
    }
}
