<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove cadastros de teste (paciente + receitas em cascata).
 *
 * A busca por "test/teste" no nome é sugestão, nunca critério de exclusão: "Nicodemos" casa com
 * "demo" e "Modesto" com "test". Por isso apagar exige `--ids` explícito — a lista é conferida por
 * gente antes. O banco já cascateia de `pacientes` para receitas, itens, aquisições, vínculos,
 * telefones e atendimentos; `receitas.receita_origem_id` vira NULL.
 */
class RemoverPacientesTeste extends Command
{
    protected $signature = 'pacientes:remover-teste
                            {--ids= : Ids de pacientes separados por vírgula (obrigatório para apagar)}
                            {--force : Apaga de verdade (sem isto, só simula)}
                            {--ignorar-alertas : Apaga mesmo com sinal de dado autêntico (pedido no oList)}';

    protected $description = 'Lista candidatos a cadastro de teste e remove os ids aprovados (com receitas em cascata)';

    /** Marcadores que o pessoal usou no CLW2/CLW3 para lixo de teste. */
    private const REGEX_SUGESTAO = '(^|[^a-zà-ÿ])(teste?s?|testando|demo)([^a-zà-ÿ]|$)|^(x{2,}|z{2,})[^a-zà-ÿ]*test';

    public function handle(): int
    {
        $idsOpt = trim((string) $this->option('ids'));
        $force = (bool) $this->option('force');

        if ($force && $idsOpt === '') {
            $this->error('Apagar exige --ids= com a lista conferida. Rode sem --force para ver os candidatos.');

            return 1;
        }

        $pacientes = $idsOpt !== ''
            ? $this->porIds($idsOpt)
            : $this->candidatos();

        if ($pacientes->isEmpty()) {
            $this->info('Nenhum paciente encontrado.');

            return 0;
        }

        $linhas = [];
        $bloqueados = [];
        foreach ($pacientes as $p) {
            $alertas = $this->alertas($p->id);
            if ($alertas !== [] && ! $this->option('ignorar-alertas')) {
                $bloqueados[] = $p->id;
            }
            $linhas[] = [
                $p->id,
                mb_strimwidth((string) $p->nome, 0, 38, '…'),
                $p->cpf ?: '—',
                $p->celular ?: ($p->telefone1 ?: '—'),
                (string) $p->receitas_count,
                $p->tiny_id ? 'sim' : '—',
                $alertas === [] ? '' : implode('; ', $alertas),
            ];
        }

        $this->table(
            ['id', 'nome', 'cpf', 'telefone', 'receitas', 'oList', 'alerta'],
            $linhas
        );

        if ($idsOpt === '') {
            $this->newLine();
            $this->warn('Lista de SUGESTÃO — confira nome por nome; "Nicodemos"/"Modesto" casam com o padrão.');
            $this->line('Para apagar: php artisan pacientes:remover-teste --ids=1,2,3 --force');

            return 0;
        }

        if ($bloqueados !== []) {
            $this->error('Bloqueado por sinal de dado autêntico nos ids: '.implode(', ', $bloqueados));
            $this->line('Revise; se for teste mesmo, repita com --ignorar-alertas.');

            return 1;
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Simulação: nada foi apagado. Repita com --force para valer.');

            return 0;
        }

        $ids = $pacientes->pluck('id')->all();
        $receitas = DB::table('receitas')->whereIn('paciente_id', $ids)->count();

        DB::transaction(function () use ($ids) {
            // Cascata do banco cuida de receitas → itens → aquisições, vínculos, telefones e
            // atendimentos. Query builder de propósito: não acorda observer nem sync do oList.
            DB::table('pacientes')->whereIn('id', $ids)->delete();
        });

        $this->info(sprintf('Removidos %d pacientes e %d receitas (em cascata).', count($ids), $receitas));
        $comIntegracao = $pacientes->filter(fn ($p) => $p->tiny_id || $p->rd_contact_id)->pluck('id')->all();
        if ($comIntegracao !== []) {
            $this->warn('Contatos correspondentes no oList/RD Station NÃO são apagados — remova por lá se quiser: ids '.implode(', ', $comIntegracao));
        }

        return 0;
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
     * Filtro em PHP em vez de REGEXP no banco: são poucos milhares de linhas e o sqlite dos
     * testes não tem REGEXP.
     *
     * @return \Illuminate\Support\Collection<int, Paciente>
     */
    private function candidatos(): \Illuminate\Support\Collection
    {
        $ids = Paciente::query()
            ->select(['id', 'nome', 'email1'])
            ->get()
            ->filter(fn ($p) => $this->pareceTeste((string) $p->nome, (string) ($p->email1 ?? '')))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Paciente::query()->withCount('receitas')->whereIn('id', $ids)->orderBy('nome')->get();
    }

    private function pareceTeste(string $nome, string $email): bool
    {
        if (preg_match('/'.self::REGEX_SUGESTAO.'/iu', $nome) === 1) {
            return true;
        }

        // Pega tanto probe@example.com quanto ale@ale.test.
        return $email !== '' && preg_match('/(@|\.)(test|exemplo|example|invalid|local)(\.|$)/i', $email) === 1;
    }

    /**
     * Sinais de que o cadastro é real apesar do nome.
     *
     * @return list<string>
     */
    private function alertas(int $pacienteId): array
    {
        $alertas = [];

        $comPedido = DB::table('receita_item_aquisicoes as a')
            ->join('receita_itens as ri', 'ri.id', '=', 'a.receita_item_id')
            ->join('receitas as r', 'r.id', '=', 'ri.receita_id')
            ->where('r.paciente_id', $pacienteId)
            ->whereNotNull('a.tiny_pedido_id')
            ->count();
        if ($comPedido > 0) {
            $alertas[] = "{$comPedido} aquisição(ões) com pedido no oList";
        }

        $origemDeOutro = DB::table('receitas as filha')
            ->join('receitas as mae', 'mae.id', '=', 'filha.receita_origem_id')
            ->where('mae.paciente_id', $pacienteId)
            ->where('filha.paciente_id', '!=', $pacienteId)
            ->count();
        if ($origemDeOutro > 0) {
            $alertas[] = "{$origemDeOutro} receita(s) de outro paciente derivam desta";
        }

        return $alertas;
    }
}
