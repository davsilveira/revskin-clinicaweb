<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpeza dos usuários duplicados herdados da importação legado.
 *
 * Decisões do cliente já embutidas:
 *  1. 14 pares médico (legado com dados + shell vazia): consolidar mantendo o e-mail
 *     limpo @revskin.com.br e os dados; apagar a shell (revalidando 0 dependências).
 *  2. Contas legado sem par (27): limpar o sufixo @legado (renomear para @revskin),
 *     com checagem de colisão.
 *  3. "Secretaria Administrativa N": manter (confirmado que existem no dump legado).
 *
 * Dry-run por padrão; aplica só com --force.
 */
class AuditarDuplicadosLegado extends Command
{
    protected $signature = 'usuarios:auditar-duplicados-legado {--force : aplica de fato (sem = dry-run)}';

    protected $description = 'Consolida pares médico legado+shell e limpa o sufixo @legado das contas em uso.';

    private const LEGADO_DOMAIN = '@legado.revskin.com.br';

    private const LIMPO_DOMAIN = '@revskin.com.br';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $this->info('== Limpeza de duplicados legado ('.($force ? 'APLICANDO' : 'DRY-RUN').') ==');

        $users = User::all(['id', 'name', 'email', 'role', 'medico_id']);
        $porLocalPart = $users->groupBy(fn ($u) => mb_strtolower(trim(explode('@', $u->email)[0] ?? '')));

        $paresConsolidados = 0;
        $emailsLimpos = 0;
        $ignoradosColisao = 0;
        $shellsComDependencia = 0;
        $legadoDePares = []; // ids de legado já tratados no passo 1 (não repetir no passo 2)

        foreach ($porLocalPart as $grupo) {
            $legado = $grupo->first(fn ($u) => str_contains($u->email, self::LEGADO_DOMAIN) && $u->medico_id);
            $shell = $grupo->first(fn ($u) => ! str_contains($u->email, self::LEGADO_DOMAIN) && ! $u->medico_id && $u->role === 'medico');

            // 1) Par médico legado+shell → consolidar mantendo e-mail limpo.
            if ($legado && $shell && $grupo->count() >= 2) {
                $emailLimpo = $shell->email;

                if ($this->shellTemDependencia((int) $shell->id)) {
                    $shellsComDependencia++;
                    $this->warn("  [pulado] shell #{$shell->id} <{$shell->email}> tem dependências — revisar manualmente.");

                    continue;
                }

                $this->line("  [par] manter dados do legado #{$legado->id}, e-mail → {$emailLimpo}; apagar shell #{$shell->id}");
                $paresConsolidados++;
                $legadoDePares[(int) $legado->id] = true;
                if ($force) {
                    DB::transaction(function () use ($legado, $shell, $emailLimpo) {
                        DB::table('users')->where('id', $shell->id)->delete();
                        DB::table('users')->where('id', $legado->id)->update(['email' => $emailLimpo]);
                    });
                }

                continue;
            }
        }

        // 2) Contas legado sem par → limpar sufixo @legado (renomear).
        foreach ($users as $u) {
            if (! str_contains($u->email, self::LEGADO_DOMAIN)) {
                continue;
            }
            // Legado de um par já consolidado no passo 1 (e-mail cuidado lá).
            if (isset($legadoDePares[(int) $u->id])) {
                continue;
            }
            $emailLimpo = str_replace(self::LEGADO_DOMAIN, self::LIMPO_DOMAIN, $u->email);

            // Colisão: já existe outro usuário com o e-mail limpo?
            $colide = User::where('email', $emailLimpo)->where('id', '!=', $u->id)->exists();
            if ($colide) {
                // Se o colidente é a shell que será apagada no mesmo run, não é colisão real.
                $ignoradosColisao++;
                $this->warn("  [colisão] #{$u->id} {$u->email} → {$emailLimpo} já existe; pulado.");

                continue;
            }

            $this->line("  [limpar] #{$u->id} {$u->email} → {$emailLimpo}");
            $emailsLimpos++;
            if ($force) {
                DB::table('users')->where('id', $u->id)->update(['email' => $emailLimpo]);
            }
        }

        $this->newLine();
        $this->info('Resumo:');
        $this->line("  Pares consolidados (shell apagada): {$paresConsolidados}");
        $this->line("  E-mails legado limpos: {$emailsLimpos}");
        $this->line("  Shells com dependência (revisar): {$shellsComDependencia}");
        $this->line("  Colisões ignoradas: {$ignoradosColisao}");
        $this->line('  Secretárias genéricas "Secretaria Administrativa N": mantidas (existem no legado).');

        if (! $force) {
            $this->warn('DRY-RUN: nada foi gravado. Rode com --force para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * Uma shell só é apagável se não tiver nenhuma referência (criou/alterou registros,
     * pivot user_medico, etc.).
     */
    private function shellTemDependencia(int $userId): bool
    {
        $checks = [
            ['pacientes', 'created_by_user_id'],
            ['pacientes', 'updated_by_user_id'],
            ['medico_paciente', 'created_by_user_id'],
            ['medico_paciente', 'updated_by_user_id'],
            ['atendimentos_callcenter', 'usuario_id'],
            ['atendimentos_callcenter', 'usuario_alteracao_id'],
            ['user_medico', 'user_id'],
        ];

        foreach ($checks as [$tabela, $coluna]) {
            if (! DB::getSchemaBuilder()->hasColumn($tabela, $coluna)) {
                continue;
            }
            if (DB::table($tabela)->where($coluna, $userId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
