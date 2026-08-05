<?php

namespace App\Console\Commands;

use App\Jobs\PullPacientesTinyJob;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Carga de clientes do oList (Tiny) para `pacientes`.
 *
 * O job agendado (`PullPacientesTinyJob`) roda no modo incremental. Este comando é a carga
 * inicial em volume: varre a base inteira de contatos ativos do oList.
 *
 * A varredura é retomável: quando o budget de API ou o rate limit estouram, o job grava um
 * checkpoint (`tiny_contatos_backfill_checkpoint`) e sai; rodar o comando de novo continua da
 * página onde parou. Como o oList limita ~25 req/min e cada contato novo custa 1 chamada,
 * uma base de ~1.400 contatos leva algumas execuções.
 */
class ImportarClientesTinyCommand extends Command
{
    protected $signature = 'tiny:importar-clientes
        {--full : Varre a base inteira do oList (carga inicial), ignorando a marca d\'água do incremental}
        {--budget=200 : Máximo de chamadas de API nesta execução}
        {--paginas= : Máximo de páginas (100 contatos por página) nesta execução}
        {--dry-run : Só conta o que faria, sem gravar nada}
        {--reiniciar : Descarta o checkpoint e começa da primeira página}';

    protected $description = 'Importa clientes do oList (Tiny) como pacientes, incluindo os que ainda não existem aqui';

    public function handle(): int
    {
        if (! Setting::get('tiny_enabled', false)) {
            $this->error('Integração com o oList está desabilitada (setting tiny_enabled).');

            return self::FAILURE;
        }

        $full = (bool) $this->option('full');
        $dryRun = (bool) $this->option('dry-run');
        $budget = max(1, (int) $this->option('budget'));
        $paginas = $this->option('paginas') !== null ? max(1, (int) $this->option('paginas')) : null;

        if ($this->option('reiniciar')) {
            // Em dry-run o checkpoint é intocável: apagá-lo faria a próxima execução real
            // repetir páginas já varridas (centenas de chamadas de API a ~25/min).
            if ($dryRun) {
                $this->warn('--reiniciar ignorado em dry-run (o checkpoint da varredura real não é alterado).');
            } else {
                Setting::set($full ? 'tiny_contatos_backfill_checkpoint' : 'tiny_contatos_pull_checkpoint', null);
                $this->line('Checkpoint descartado.');
            }
        }

        $this->info(sprintf(
            'Importando clientes do oList — modo %s%s (budget de API: %d%s)',
            $full ? 'carga completa' : 'incremental',
            $dryRun ? ' [DRY RUN]' : '',
            $budget,
            $paginas !== null ? ", máx. {$paginas} páginas" : ''
        ));

        $job = new PullPacientesTinyJob(
            backfillCompleto: $full,
            apiBudget: $budget,
            maxPaginas: $paginas,
            dryRun: $dryRun,
        );

        $job->onProgress = function (int $pagina, int $total, array $stats): void {
            $this->line(sprintf(
                '  página %d/%d — novos: %d | conciliados: %d | atualizados: %d | ignorados: %d | api: %d',
                $pagina,
                $total,
                $stats['importados'] ?? 0,
                $stats['conciliados'] ?? 0,
                $stats['atualizados'] ?? 0,
                $stats['ignorados'] ?? 0,
                $stats['api_calls'] ?? 0,
            ));
        };

        $job->handle();

        $s = $job->stats;
        if ($s === []) {
            $this->warn('Nada a fazer (integração desligada ou API fora da V2).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Importados (novos)', 'Conciliados (já existiam)', 'Atualizados', 'Ignorados', 'Chamadas de API'],
            [[$s['importados'], $s['conciliados'], $s['atualizados'], $s['ignorados'], $s['api_calls']]]
        );

        // Quanto cada regra de conciliação segurou de cadastro repetido.
        if (! empty($s['conciliados_por'])) {
            $rotulos = [
                'cpf' => 'CPF igual',
                'email+nascimento' => 'e-mail + data de nascimento',
                'email+nome' => 'e-mail único + nome compatível',
                'celular+nome' => 'celular + nome compatível',
                'nascimento+nome' => 'data de nascimento + nome compatível',
            ];
            arsort($s['conciliados_por']);
            $this->line('Conciliados por:');
            foreach ($s['conciliados_por'] as $motivo => $qtd) {
                $rotulo = $rotulos[$motivo] ?? $motivo;
                // Alinhamento por caractere, não por byte: os rótulos têm acento.
                $this->line('  '.$rotulo.str_repeat(' ', max(1, 38 - mb_strlen($rotulo))).$qtd);
            }
        }

        if (($s['homonimos'] ?? 0) > 0) {
            $this->warn(sprintf(
                '%d dos importados têm um homônimo já cadastrado (sem CPF/e-mail/celular/data em comum para confirmar). '
                .'O médico decide na busca por nome, que mostra os dois lado a lado. Detalhes no log.',
                $s['homonimos']
            ));
        }

        if ($s['pausado'] ?? false) {
            $this->warn('Execução pausada (budget/rate limit/limite de páginas). Rode o comando de novo para continuar de onde parou.');
        } elseif ($s['concluido'] ?? false) {
            $this->info('Varredura concluída.');
        }

        if ($dryRun) {
            $this->comment('DRY RUN: nada foi gravado.');
        }

        return self::SUCCESS;
    }
}
