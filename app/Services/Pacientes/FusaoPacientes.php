<?php

namespace App\Services\Pacientes;

use App\Models\Paciente;
use App\Models\Receita;
use App\Support\EmailPlaceholder;
use Illuminate\Support\Facades\DB;

/**
 * Funde dois cadastros de paciente num só.
 *
 * Regras que valem a pena entender antes de mexer:
 *
 * - **Só preenche o que está vazio.** O cadastro que fica nunca tem um campo sobrescrito; o que sai
 *   só completa buraco. É o que evita que um dado ruim do oList (telefone sem o 9, data de
 *   nascimento com o século trocado) atropele o que a clínica digitou.
 * - **Data de nascimento no futuro é recusada.** A base tem 70 registros com `2071` no lugar de
 *   `1971`; copiar isso para o cadastro bom só espalharia o erro.
 * - **CPF diferente aborta.** Dois CPFs válidos e distintos querem dizer que não é a mesma pessoa —
 *   ninguém funde nada nesse caso.
 * - **O número da receita NÃO é reescrito por padrão.** `receitas.numero` vai para o oList como
 *   `numero_pedido_ecommerce` e sai impresso na receita do paciente: renumerar quebraria a
 *   referência de um pedido que já existe no ERP. O número antigo (`16435-0001` num paciente
 *   `17401`) fica visualmente estranho, mas é o preço de não mentir sobre um documento já emitido.
 *   `--renumerar` existe para quando não houver pedido nenhum envolvido.
 * - **Nada de eventos.** `PacienteObserver` empurraria cada alteração de volta para o oList; a
 *   fusão grava com `withoutEvents` justamente porque o dado veio de lá.
 */
class FusaoPacientes
{
    /** Campos preenchidos a partir do cadastro que sai, quando o que fica estiver vazio. */
    private const CAMPOS_COMPLEMENTARES = [
        'codigo', 'data_nascimento', 'sexo', 'fototipo', 'cpf', 'outro_documento', 'rg',
        'telefone1', 'celular', 'telefone3', 'email1', 'email2',
        'tipo_endereco', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
        'indicado_por', 'rd_organization_id', 'rd_contact_id',
    ];

    /**
     * @return array{
     *     ok: bool,
     *     erro: ?string,
     *     manter: int,
     *     apagar: int,
     *     campos: array<string, mixed>,
     *     receitas: int,
     *     vinculos: int,
     *     vinculos_ja_existiam: int,
     *     telefones: int,
     *     atendimentos: int,
     *     renumeradas: int,
     *     avisos: list<string>,
     * }
     */
    public function fundir(
        int $manterId,
        int $apagarId,
        bool $aplicar = false,
        bool $permitirHistorico = false,
        bool $renumerar = false,
    ): array {
        $r = [
            'ok' => false, 'erro' => null, 'manter' => $manterId, 'apagar' => $apagarId,
            'campos' => [], 'receitas' => 0, 'vinculos' => 0, 'vinculos_ja_existiam' => 0,
            'telefones' => 0, 'atendimentos' => 0, 'renumeradas' => 0, 'avisos' => [],
        ];

        if ($manterId === $apagarId) {
            $r['erro'] = 'manter e apagar são o mesmo id';

            return $r;
        }

        $manter = Paciente::find($manterId);
        $apagar = Paciente::find($apagarId);

        if (! $manter) {
            $r['erro'] = "paciente #{$manterId} (manter) não existe";

            return $r;
        }
        if (! $apagar) {
            $r['erro'] = "paciente #{$apagarId} (apagar) não existe";

            return $r;
        }

        $receitas = Receita::where('paciente_id', $apagarId)->count();
        $vinculos = DB::table('medico_paciente')->where('paciente_id', $apagarId)->count();
        if (($receitas > 0 || $vinculos > 0) && ! $permitirHistorico) {
            $r['erro'] = "paciente #{$apagarId} tem histórico ({$receitas} receitas, {$vinculos} vínculos); "
                .'use --permitir-historico para movê-lo';

            return $r;
        }

        $cpfManter = $this->digitos($manter->cpf);
        $cpfApagar = $this->digitos($apagar->cpf);
        if ($cpfManter !== '' && $cpfApagar !== '' && $cpfManter !== $cpfApagar) {
            $r['erro'] = "CPF diferente entre os dois cadastros ({$manter->cpf} × {$apagar->cpf})";

            return $r;
        }

        $campos = $this->camposParaCopiar($manter, $apagar, $r['avisos']);

        if (filled($apagar->tiny_id)) {
            if (blank($manter->tiny_id)) {
                $campos['tiny_id'] = (string) $apagar->tiny_id;
            } elseif ((string) $manter->tiny_id !== (string) $apagar->tiny_id) {
                $r['avisos'][] = "o oList tem dois contatos desta pessoa ({$manter->tiny_id} e "
                    ."{$apagar->tiny_id}); o contato {$apagar->tiny_id} fica órfão e pode recriar o "
                    .'cadastro se for editado por lá';
            }
        }

        $r['campos'] = $campos;
        $r['receitas'] = $receitas;
        $r['telefones'] = DB::table('paciente_telefones')->where('paciente_id', $apagarId)->count();
        $r['atendimentos'] = DB::table('atendimentos_callcenter')->where('paciente_id', $apagarId)->count();

        $medicosDoManter = DB::table('medico_paciente')->where('paciente_id', $manterId)->pluck('medico_id')->all();
        $medicosDoApagar = DB::table('medico_paciente')->where('paciente_id', $apagarId)->pluck('medico_id')->all();
        $r['vinculos_ja_existiam'] = count(array_intersect($medicosDoApagar, $medicosDoManter));
        $r['vinculos'] = count($medicosDoApagar) - $r['vinculos_ja_existiam'];

        if (! $aplicar) {
            $r['ok'] = true;

            return $r;
        }

        DB::transaction(function () use ($manter, $apagar, $manterId, $apagarId, $campos, $medicosDoManter, $renumerar, &$r) {
            if ($campos !== []) {
                Paciente::withoutEvents(function () use ($manter, $campos) {
                    $manter->forceFill($campos)->save();
                });
            }

            // Vínculo que já existe no cadastro que fica não pode duplicar o pivot.
            DB::table('medico_paciente')
                ->where('paciente_id', $apagarId)
                ->whereIn('medico_id', $medicosDoManter)
                ->delete();
            DB::table('medico_paciente')->where('paciente_id', $apagarId)->update(['paciente_id' => $manterId]);

            DB::table('receitas')->where('paciente_id', $apagarId)->update(['paciente_id' => $manterId]);
            DB::table('paciente_telefones')->where('paciente_id', $apagarId)->update(['paciente_id' => $manterId]);
            DB::table('atendimentos_callcenter')->where('paciente_id', $apagarId)->update(['paciente_id' => $manterId]);

            if ($renumerar) {
                $r['renumeradas'] = Receita::renumerarPorData($manterId);
            }

            Paciente::withoutEvents(fn () => $apagar->delete());
        });

        $r['ok'] = true;

        return $r;
    }

    /**
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private function camposParaCopiar(Paciente $manter, Paciente $apagar, array &$avisos): array
    {
        $campos = [];

        foreach (self::CAMPOS_COMPLEMENTARES as $campo) {
            $novo = $apagar->{$campo};
            if (blank($novo)) {
                continue;
            }

            if ($campo === 'data_nascimento') {
                $data = $apagar->data_nascimento?->format('Y-m-d');
                if ($data === null) {
                    continue;
                }
                if ($data > now()->toDateString()) {
                    $avisos[] = "data de nascimento {$data} está no futuro; não foi copiada";

                    continue;
                }
                if (filled($manter->data_nascimento)) {
                    continue;
                }
                $campos[$campo] = $data;

                continue;
            }

            if (in_array($campo, ['email1', 'email2'], true)) {
                if (! $this->ehEmailDeVerdade((string) $novo)) {
                    continue;
                }
                if ($this->ehEmailDeVerdade((string) $manter->{$campo})) {
                    continue;
                }
                $campos[$campo] = $novo;

                continue;
            }

            if (filled($manter->{$campo})) {
                continue;
            }

            $campos[$campo] = $novo;
        }

        return $campos;
    }

    /**
     * Endereço que serve para falar com o paciente.
     *
     * Fora um e-mail de marcação (`<telefone>@cadastraremail.rsk`, que só sinaliza "falta e-mail"),
     * o campo às vezes tem qualquer coisa: a Mariah Pedrosa está cadastrada com o próprio nome no
     * lugar do e-mail. Nada disso pode segurar o endereço de verdade que vem do outro cadastro.
     */
    private function ehEmailDeVerdade(?string $email): bool
    {
        $e = trim((string) $email);

        return $e !== ''
            && filter_var($e, FILTER_VALIDATE_EMAIL) !== false
            && ! EmailPlaceholder::ehPlaceholder($e);
    }

    private function digitos(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }
}
