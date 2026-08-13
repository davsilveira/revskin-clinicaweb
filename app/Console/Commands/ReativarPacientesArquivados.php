<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Devolve à busca cadastros que ficaram `ativo=0` sem ninguém ter arquivado nada no CLW3.
 *
 * A carga do CLW2 copiou `paciente.ativo` do dump e, quando duas fichas do CLW2 tinham o mesmo
 * CPF, ficou a de id menor — que costuma ser a que a clínica havia marcado como repetida
 * (renomeada com `z-`/`zzz`). Depois `pacientes:backfill-vinculos` copiou esse `ativo` para o
 * pivot, então o cadastro leva dois cadeados: `pacientes.ativo=0` (esconde de todos) e
 * `medico_paciente.ativo=0` (esconde do médico). Resultado: paciente com receita recente que o
 * médico não acha no Assistente de Receita e acaba recadastrando (ver job f8b5e9c5).
 *
 * Só reativa, nunca desativa. Sem `--ids` apenas lista os candidatos (somente leitura), porque
 * `ativo=0` também é arquivamento legítimo feito por admin — quem decide é gente.
 */
class ReativarPacientesArquivados extends Command
{
    protected $signature = 'pacientes:reativar
                            {--ids= : Ids de pacientes separados por vírgula (obrigatório para aplicar)}
                            {--limpar-prefixo : Remove marcador de repetido do nome (z-, zzz, xx…)}
                            {--force : Aplica de fato (sem isto, só simula)}';

    protected $description = 'Lista (ou reativa, por ids) cadastros arquivados que ainda têm receita — os que o médico não acha na busca.';

    /**
     * Marcador de "ficha repetida" que a clínica usava no CLW2 renomeando o cadastro: `zzz Nome`
     * ou `z-Nome`. Letra sozinha só conta com separador depois, senão "Zilda" viraria "ilda".
     */
    private const REGEX_PREFIXO = '/^\s*([zx][zx]+\s*[-–—_.]?\s*|[zx]\s*[-–—_.]\s*)(?=\p{L})/iu';

    public function handle(): int
    {
        $idsOpt = trim((string) $this->option('ids'));
        $force = (bool) $this->option('force');

        if ($force && $idsOpt === '') {
            $this->error('Reativar exige --ids= com a lista conferida. Rode sem --ids para ver os candidatos.');

            return self::FAILURE;
        }

        $pacientes = $idsOpt !== '' ? $this->porIds($idsOpt) : $this->candidatos();

        if ($pacientes->isEmpty()) {
            $this->info('Nenhum cadastro arquivado com receita.');

            return self::SUCCESS;
        }

        $planos = $pacientes->map(fn (Paciente $p) => $this->plano($p))->all();

        $this->table(
            ['id', 'nome', 'nome depois', 'ativo', 'cpf', 'receitas', 'última', 'vínculos a reativar', 'vínculos mantidos'],
            array_map(fn (array $pl) => [
                $pl['id'],
                mb_strimwidth($pl['nome'], 0, 34, '…'),
                $pl['nome'] === $pl['nome_novo'] ? '=' : mb_strimwidth($pl['nome_novo'], 0, 34, '…'),
                $pl['ativo'] ? 'sim' : 'NÃO',
                $pl['cpf'],
                (string) $pl['receitas'],
                $pl['ultima'],
                $pl['vinculos_reativar'] === [] ? '—' : implode(', ', $pl['vinculos_reativar']),
                $pl['vinculos_manter'] === [] ? '—' : implode(', ', $pl['vinculos_manter']),
            ], $planos)
        );

        if ($idsOpt === '') {
            $this->newLine();
            $this->warn('Lista de CANDIDATOS (somente leitura): `ativo=0` também pode ser arquivamento legítimo.');
            $this->line('Confira ficha por ficha e reative os aprovados:');
            $this->line('  php artisan pacientes:reativar --ids=1,2,3 --limpar-prefixo --force');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Simulação: nada foi gravado. Repita com --force para valer.');

            return self::SUCCESS;
        }

        $reativados = 0;
        $renomeados = 0;
        $vinculos = 0;

        DB::transaction(function () use ($planos, &$reativados, &$renomeados, &$vinculos) {
            foreach ($planos as $pl) {
                // Query builder de propósito: nome/ativo aqui é reparo de migração, não deve
                // acordar o observer que empurra o cadastro para o oList/RD Station.
                $mudancas = [];
                if (! $pl['ativo']) {
                    $mudancas['ativo'] = 1;
                    $reativados++;
                }
                if ($pl['nome_novo'] !== $pl['nome']) {
                    $mudancas['nome'] = $pl['nome_novo'];
                    $renomeados++;
                }
                if ($mudancas !== []) {
                    DB::table('pacientes')->where('id', $pl['id'])->update($mudancas);
                }

                if ($pl['vinculos_reativar'] !== []) {
                    $vinculos += DB::table('medico_paciente')
                        ->where('paciente_id', $pl['id'])
                        ->whereIn('medico_id', $pl['vinculos_reativar'])
                        ->update(['ativo' => 1, 'updated_at' => now()]);
                }
            }
        });

        $this->info(sprintf(
            'Reativados: %d cadastro(s), %d vínculo(s) médico-paciente. Nomes limpos: %d.',
            $reativados,
            $vinculos,
            $renomeados
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function plano(Paciente $paciente): array
    {
        // Só reativa o vínculo de quem tem receita com este paciente: é a prova de que o médico
        // atende a pessoa. Vínculo inativo sem receita pode ser arquivamento de verdade.
        $medicosComReceita = DB::table('receitas')
            ->where('paciente_id', $paciente->id)
            ->whereNotNull('medico_id')
            ->distinct()
            ->pluck('medico_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $inativos = DB::table('medico_paciente')
            ->where('paciente_id', $paciente->id)
            ->where('ativo', 0)
            ->pluck('medico_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $reativar = array_values(array_intersect($inativos, $medicosComReceita));
        $manter = array_values(array_diff($inativos, $reativar));

        $ultima = DB::table('receitas')->where('paciente_id', $paciente->id)->max('data_receita');

        return [
            'id' => $paciente->id,
            'nome' => (string) $paciente->nome,
            'nome_novo' => $this->option('limpar-prefixo')
                ? $this->nomeSemPrefixo((string) $paciente->nome)
                : (string) $paciente->nome,
            'ativo' => (bool) $paciente->ativo,
            'cpf' => $paciente->cpf ?: '—',
            'receitas' => (int) ($paciente->receitas_count ?? 0),
            'ultima' => $ultima ? (string) $ultima : '—',
            'vinculos_reativar' => $reativar,
            'vinculos_manter' => $manter,
        ];
    }

    /**
     * "z-Fanilde Pirro" → "Fanilde Pirro". Nome que só tem marcador (ex.: "zzz Marcelo2") fica
     * como está: sem sobrenome, não há como saber se é pessoa de verdade.
     */
    private function nomeSemPrefixo(string $nome): string
    {
        $limpo = trim((string) preg_replace(self::REGEX_PREFIXO, '', $nome));

        if ($limpo === '' || mb_strlen($limpo) < 5 || ! str_contains($limpo, ' ')) {
            return $nome;
        }

        return $limpo;
    }

    /** @return \Illuminate\Support\Collection<int, Paciente> */
    private function porIds(string $idsOpt): \Illuminate\Support\Collection
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $idsOpt))));

        $encontrados = Paciente::query()->withCount('receitas')->whereIn('id', $ids)->orderBy('id')->get();

        $faltando = array_diff($ids, $encontrados->pluck('id')->all());
        if ($faltando !== []) {
            $this->warn('Ids inexistentes, ignorados: '.implode(', ', $faltando));
        }

        return $encontrados;
    }

    /**
     * Candidatos: cadastro arquivado que ainda tem receita — alguém prescreveu para essa pessoa,
     * então ela deveria aparecer na busca de quem prescreveu.
     *
     * @return \Illuminate\Support\Collection<int, Paciente>
     */
    private function candidatos(): \Illuminate\Support\Collection
    {
        return Paciente::query()
            ->withCount('receitas')
            ->where('ativo', 0)
            ->has('receitas')
            ->orderBy('id')
            ->get();
    }
}
