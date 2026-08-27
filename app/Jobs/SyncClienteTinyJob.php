<?php

namespace App\Jobs;

use App\Models\Paciente;
use App\Models\Setting;
use App\Services\TinyContatoMapper;
use App\Services\TinyErpClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncClienteTinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Paciente $paciente
    ) {
        $this->onQueue('tiny-sync');
    }

    public function handle(): void
    {
        // Verificar se integração está habilitada
        if (! Setting::get('tiny_enabled', false)) {
            Log::info('Tiny ERP: Sincronização de cliente desabilitada', [
                'paciente_id' => $this->paciente->id,
            ]);

            return;
        }

        // Verificar campos obrigatórios
        if (! $this->validarCamposObrigatorios()) {
            Log::warning('Tiny ERP: Paciente não possui campos obrigatórios para sincronização', [
                'paciente_id' => $this->paciente->id,
            ]);

            return;
        }

        $client = new TinyErpClient;
        $paciente = $this->paciente->fresh();

        // Sem tiny_id local, o contato pode já existir no Tiny (cadastro legado);
        // criar às cegas falharia por CPF duplicado — vincular pelo CPF antes.
        if (! $paciente->tiny_id) {
            $tinyIdExistente = $this->buscarTinyIdPorCpf($client);
            if ($tinyIdExistente) {
                $paciente->update(['tiny_id' => (string) $tinyIdExistente]);
                Log::info('Tiny ERP: Contato existente no Tiny vinculado por CPF', [
                    'paciente_id' => $paciente->id,
                    'tiny_id' => $tinyIdExistente,
                ]);
            }
        }

        if ($paciente->tiny_id) {
            $result = $client->obterContato((int) $paciente->tiny_id);

            if ($result['status'] === 'success') {
                $contatoTiny = $result['data'] ?? [];
                $dataTiny = TinyContatoMapper::parseDataAtualizacao(
                    TinyContatoMapper::contatoDataAtualizacaoRaw($contatoTiny)
                );

                if ($dataTiny && $paciente->updated_at && $paciente->updated_at->lte($dataTiny)) {
                    $paciente->update([
                        'tiny_updated_at' => $dataTiny,
                        'tiny_sync_at' => now(),
                    ]);
                    Log::info('Tiny ERP: Dados do Tiny são mais recentes, não atualizando', [
                        'paciente_id' => $paciente->id,
                        'tiny_id' => $paciente->tiny_id,
                    ]);

                    return;
                }

                $result = $client->atualizarContato((int) $paciente->tiny_id, $this->prepararDadosContato());
            } else {
                $result = $client->criarContato($this->prepararDadosContato());
            }
        } else {
            $result = $client->criarContato($this->prepararDadosContato());
        }

        if ($result['status'] === 'success') {
            $data = $result['data'] ?? [];
            $tinyId = $data['id'] ?? $paciente->tiny_id;

            if ($tinyId) {
                $tinyId = (string) $tinyId;
                $ref = $client->obterContato((int) $tinyId);
                $tinyUpdated = Carbon::now();
                if ($ref['status'] === 'success' && is_array($ref['data'] ?? null)) {
                    $tinyUpdated = TinyContatoMapper::tinUpdatedAtFromContato($ref['data']);
                }

                $paciente->update([
                    'tiny_id' => $tinyId,
                    'tiny_sync_at' => now(),
                    'tiny_updated_at' => $tinyUpdated,
                ]);

                Log::info('Tiny ERP: Cliente sincronizado com sucesso', [
                    'paciente_id' => $paciente->id,
                    'tiny_id' => $tinyId,
                ]);
            }
        } else {
            Log::error('Tiny ERP: Erro ao sincronizar cliente', [
                'paciente_id' => $paciente->id,
                'error' => $result['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception($result['message'] ?? 'Erro ao sincronizar cliente');
        }
    }

    protected function buscarTinyIdPorCpf(TinyErpClient $client): ?int
    {
        $cpf = preg_replace('/\D/', '', $this->paciente->cpf ?? '');
        if (strlen($cpf) !== 11) {
            return null;
        }

        $result = $client->listarContatos(['cpf_cnpj' => $cpf]);
        if ($result['status'] !== 'success') {
            return null;
        }

        $itens = $result['data']['itens'] ?? [];
        $id = $itens[0]['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    protected function validarCamposObrigatorios(): bool
    {
        // Só o nome é obrigatório: a API oList/Tiny aceita contato sem cpf_cnpj
        // (verificado contra a API v2 em 30/07/2026), inclusive pessoa física.
        return ! empty($this->paciente->nome);
    }

    protected function prepararDadosContato(): array
    {
        $cpf = preg_replace('/\D/', '', $this->paciente->cpf ?? '');
        $telefone = preg_replace('/\D/', '', $this->paciente->telefone1 ?? '');
        $celular = preg_replace('/\D/', '', $this->paciente->celular ?? '');
        $cep = preg_replace('/\D/', '', $this->paciente->cep ?? '');
        $brasil = $this->paciente->isBrasil();

        $dados = [
            'nome' => $this->paciente->nome,
            // 'E' = Estrangeiro na tabela de tipo_pessoa do oList/Tiny (F/J/E).
            'tipoPessoa' => $brasil ? 'F' : 'E',
            'cpfCnpj' => $cpf,
            // Sem isso o oList cria o contato como "Outro". Paciente do CLW3 é Cliente.
            'tiposContato' => [['tipo' => 'Cliente']],
        ];

        if (! $brasil && $this->paciente->pais) {
            $dados['pais'] = $this->paciente->pais;
        }

        if ($this->paciente->email1) {
            $dados['email'] = $this->paciente->email1;
        }

        if ($telefone) {
            $dados['telefone'] = $telefone;
        }

        if ($celular) {
            $dados['celular'] = $celular;
        }

        if ($this->paciente->endereco) {
            $dados['endereco'] = [
                'endereco' => $this->paciente->endereco,
                'numero' => $this->paciente->numero ?? '',
                'complemento' => $this->paciente->complemento ?? '',
                'bairro' => $this->paciente->bairro ?? '',
                // Cidade/UF estrangeiras são recusadas pelo contato.incluir (valida contra a
                // lista de municípios do Brasil); fora do Brasil vão só no país + logradouro.
                'cidade' => $brasil ? ($this->paciente->cidade ?? '') : '',
                'uf' => $brasil ? ($this->paciente->uf ?? '') : '',
                'cep' => $brasil ? $cep : '',
            ];
        }

        return $dados;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Tiny ERP: Job de sincronização de cliente falhou', [
            'paciente_id' => $this->paciente->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
