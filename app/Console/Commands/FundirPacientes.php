<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use App\Services\Pacientes\FusaoPacientes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Junta cadastros repetidos de paciente.
 *
 * Sem `--force` só simula — mostra campo a campo o que seria copiado e o que seria movido.
 * A lista de pares nunca é adivinhada pelo comando: ou vem em `--pares`, ou num CSV via `--lote`,
 * porque quem decide se dois homônimos são a mesma pessoa é a clínica, não o banco.
 */
class FundirPacientes extends Command
{
    protected $signature = 'pacientes:fundir
                            {--pares= : Pares manter:apagar separados por vírgula (ex.: 16488:17743,16643:17631)}
                            {--lote= : CSV com manter,apagar por linha (cabeçalho opcional)}
                            {--force : Aplica de verdade (sem isto, só simula)}
                            {--permitir-historico : Autoriza mover receitas e vínculos do cadastro que sai}
                            {--renumerar : Renumera as receitas do cadastro que fica (quebra referência de pedido no oList)}';

    protected $description = 'Funde cadastros repetidos de paciente, completando os campos vazios antes de remover';

    public function handle(FusaoPacientes $fusao): int
    {
        $pares = $this->pares();
        if ($pares === []) {
            $this->error('Informe --pares=manter:apagar,... ou --lote=arquivo.csv');

            return 1;
        }

        $aplicar = (bool) $this->option('force');
        $this->line(sprintf('%d par(es) · modo %s', count($pares), $aplicar ? 'APLICAR' : 'simulação'));
        $this->newLine();

        $antes = $this->invariantes();
        $linhas = [];
        $erros = 0;
        $avisos = [];

        foreach ($pares as [$manterId, $apagarId]) {
            $r = $fusao->fundir(
                $manterId,
                $apagarId,
                $aplicar,
                (bool) $this->option('permitir-historico'),
                (bool) $this->option('renumerar'),
            );

            if (! $r['ok']) {
                $erros++;
                $linhas[] = [$manterId, $apagarId, '—', '—', '—', 'ERRO: '.$r['erro']];

                continue;
            }

            foreach ($r['avisos'] as $aviso) {
                $avisos[] = "#{$apagarId}: {$aviso}";
            }

            $linhas[] = [
                $manterId,
                $apagarId,
                $r['campos'] === [] ? '—' : implode(', ', array_keys($r['campos'])),
                $r['receitas'] ?: '—',
                $r['vinculos'] ?: '—',
                $aplicar ? 'fundido' : 'ok (simulado)',
            ];
        }

        $this->table(['manter', 'apagar', 'campos copiados', 'receitas', 'vínculos', 'resultado'], $linhas);

        if ($avisos !== []) {
            $this->newLine();
            $this->warn('Avisos:');
            foreach (array_unique($avisos) as $aviso) {
                $this->line('  · '.$aviso);
            }
        }

        $depois = $this->invariantes();
        $this->newLine();
        $this->line('Invariantes (antes → depois):');
        foreach ($antes as $chave => $valor) {
            $mudou = $valor !== $depois[$chave];
            $this->line(sprintf(
                '  %-34s %8s → %-8s %s',
                $chave,
                $valor,
                $depois[$chave],
                $mudou ? ($this->esperado($chave) ? '(esperado)' : '⚠ INESPERADO') : ''
            ));
        }

        if ($erros > 0) {
            $this->newLine();
            $this->error("{$erros} par(es) com erro — nada foi feito neles.");

            return 1;
        }

        if (! $aplicar) {
            $this->newLine();
            $this->comment('Simulação. Repita com --force para aplicar.');
        }

        return 0;
    }

    /** Só a contagem de pacientes pode cair; o resto tem de ficar idêntico. */
    private function esperado(string $chave): bool
    {
        return $chave === 'pacientes';
    }

    /** @return array<string, int> */
    private function invariantes(): array
    {
        return [
            'pacientes' => DB::table('pacientes')->count(),
            'receitas' => DB::table('receitas')->count(),
            'receita_itens' => DB::table('receita_itens')->count(),
            'aquisicoes' => DB::table('receita_item_aquisicoes')->count(),
            'medico_paciente' => DB::table('medico_paciente')->count(),
            'atendimentos_callcenter' => DB::table('atendimentos_callcenter')->count(),
            'receitas orfas' => DB::table('receitas as r')->leftJoin('pacientes as p', 'p.id', '=', 'r.paciente_id')->whereNull('p.id')->count(),
            'itens orfaos' => DB::table('receita_itens as i')->leftJoin('receitas as r', 'r.id', '=', 'i.receita_id')->whereNull('r.id')->count(),
            'aquisicoes orfas' => DB::table('receita_item_aquisicoes as a')->leftJoin('receita_itens as i', 'i.id', '=', 'a.receita_item_id')->whereNull('i.id')->count(),
            'vinculos orfaos' => DB::table('medico_paciente as mp')->leftJoin('pacientes as p', 'p.id', '=', 'mp.paciente_id')->whereNull('p.id')->count(),
            'pivot duplicado' => DB::table('medico_paciente')->select('medico_id', 'paciente_id')->groupBy('medico_id', 'paciente_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'numero de receita repetido' => DB::table('receitas')->select('paciente_id', 'numero')->groupBy('paciente_id', 'numero')->havingRaw('COUNT(*) > 1')->get()->count(),
            'tiny_id repetido' => DB::table('pacientes')->select('tiny_id')->whereNotNull('tiny_id')->groupBy('tiny_id')->havingRaw('COUNT(*) > 1')->get()->count(),
        ];
    }

    /** @return list<array{0:int,1:int}> */
    private function pares(): array
    {
        $pares = [];

        $opt = trim((string) $this->option('pares'));
        if ($opt !== '') {
            foreach (explode(',', $opt) as $par) {
                $p = explode(':', trim($par));
                if (count($p) === 2 && ctype_digit(trim($p[0])) && ctype_digit(trim($p[1]))) {
                    $pares[] = [(int) trim($p[0]), (int) trim($p[1])];
                }
            }
        }

        $lote = trim((string) $this->option('lote'));
        if ($lote !== '' && is_readable($lote)) {
            $fh = fopen($lote, 'r');
            while (($linha = fgetcsv($fh)) !== false) {
                if (count($linha) < 2 || ! ctype_digit(trim((string) $linha[0])) || ! ctype_digit(trim((string) $linha[1]))) {
                    continue;
                }
                $pares[] = [(int) $linha[0], (int) $linha[1]];
            }
            fclose($fh);
        } elseif ($lote !== '') {
            $this->error("Arquivo não encontrado: {$lote}");
        }

        return $pares;
    }
}
