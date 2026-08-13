<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use App\Services\TinyContatoMapper;
use App\Services\TinyErpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Relê a ficha completa de contatos no oList e traz o que a lista não traz.
 *
 * O pull incremental usa a lista de contatos, que devolve nome, e-mail, fone e endereço — mas não
 * data de nascimento, celular nem sexo. Quando a clínica corrige um desses campos no oList, a
 * correção só chega aqui na releitura completa. Este comando força essa releitura na hora, sem
 * esperar a validade do pull, para quando alguém do outro lado avisa "já arrumei lá".
 *
 * Sem `--force` só compara e mostra a diferença.
 */
class RelerContatosTiny extends Command
{
    protected $signature = 'tiny:reler-contatos
                            {--ids= : Ids de pacientes daqui, separados por vírgula}
                            {--nascimento-impossivel : Todos com data de nascimento no futuro ou anterior a 1900}
                            {--force : Grava as diferenças (sem isto, só compara)}';

    protected $description = 'Relê a ficha completa dos contatos no oList e atualiza o que a lista não traz';

    /** Campos que só existem na ficha completa — é para eles que este comando existe. */
    private const CAMPOS = ['data_nascimento', 'celular', 'sexo'];

    public function handle(TinyErpClient $client): int
    {
        $pacientes = $this->alvos();

        if ($pacientes->isEmpty()) {
            $this->error('Nenhum paciente. Use --ids=1,2,3 ou --nascimento-impossivel.');

            return 1;
        }

        $aplicar = (bool) $this->option('force');
        $this->line(sprintf('%d paciente(s) · modo %s', $pacientes->count(), $aplicar ? 'APLICAR' : 'comparação'));
        $this->newLine();

        $linhas = [];
        $alterados = 0;
        $erros = 0;

        foreach ($pacientes as $paciente) {
            $r = $client->obterContato((int) $paciente->tiny_id);
            if (($r['status'] ?? '') !== 'success' || ! is_array($r['data'] ?? null)) {
                $erros++;
                $linhas[] = [$paciente->id, mb_strimwidth((string) $paciente->nome, 0, 32, '…'), '—', '—', 'ERRO: '.($r['message'] ?? 'sem dados')];

                continue;
            }

            $doOlist = TinyContatoMapper::toPacienteAttributes($r['data'], includeCpf: false);
            $mudancas = [];
            foreach (self::CAMPOS as $campo) {
                if (! array_key_exists($campo, $doOlist)) {
                    continue;
                }
                $atual = $campo === 'data_nascimento'
                    ? $paciente->data_nascimento?->format('Y-m-d')
                    : $paciente->{$campo};
                if ((string) $atual === (string) $doOlist[$campo]) {
                    continue;
                }
                $mudancas[$campo] = [$atual, $doOlist[$campo]];
            }

            if ($mudancas === []) {
                $linhas[] = [$paciente->id, mb_strimwidth((string) $paciente->nome, 0, 32, '…'), '—', '—', 'já igual'];

                continue;
            }

            $alterados++;
            $de = implode(', ', array_map(fn ($c) => $c.'='.($mudancas[$c][0] ?? '—'), array_keys($mudancas)));
            $para = implode(', ', array_map(fn ($c) => $c.'='.$mudancas[$c][1], array_keys($mudancas)));

            if ($aplicar) {
                $novos = array_map(fn ($m) => $m[1], $mudancas);
                $novos['tiny_detalhe_sync_at'] = Carbon::now();
                $novos['tiny_sync_at'] = Carbon::now();
                Paciente::withoutEvents(fn () => $paciente->forceFill($novos)->save());
            }

            $linhas[] = [$paciente->id, mb_strimwidth((string) $paciente->nome, 0, 32, '…'), $de, $para, $aplicar ? 'atualizado' : 'a atualizar'];
        }

        $this->table(['id', 'paciente', 'aqui', 'no oList', 'resultado'], $linhas);
        $this->newLine();
        $this->line(sprintf('%d com diferença · %d erro(s)', $alterados, $erros));

        if (! $aplicar && $alterados > 0) {
            $this->comment('Comparação. Repita com --force para gravar.');
        }

        return $erros > 0 ? 1 : 0;
    }

    /** @return \Illuminate\Support\Collection<int, Paciente> */
    private function alvos()
    {
        $q = Paciente::query()->whereNotNull('tiny_id')->where('tiny_id', '!=', '');

        $ids = trim((string) $this->option('ids'));
        if ($ids !== '') {
            $q->whereIn('id', array_filter(array_map('intval', explode(',', $ids))));
        } elseif ($this->option('nascimento-impossivel')) {
            $q->whereNotNull('data_nascimento')->where(function ($w) {
                $w->where('data_nascimento', '>', now()->toDateString())
                    ->orWhere('data_nascimento', '<', '1900-01-01');
            });
        } else {
            return collect();
        }

        return $q->orderBy('nome')->get();
    }
}
