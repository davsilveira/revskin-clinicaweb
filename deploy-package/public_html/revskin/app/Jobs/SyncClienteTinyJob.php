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

    public int $tries = 3;

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

    protected function validarCamposObrigatorios(): bool
    {
        // Campos obrigatórios: nome e CPF (para pessoa física)
        if (empty($this->paciente->nome)) {
            return false;
        }

        // CPF é obrigatório para pessoa física no Tiny
        if (empty($this->paciente->cpf)) {
            return false;
        }

        return true;
    }

    protected function prepararDadosContato(): array
    {
        $cpf = preg_replace('/\D/', '', $this->paciente->cpf ?? '');
        $telefone = preg_replace('/\D/', '', $this->paciente->telefone1 ?? '');
        $celular = preg_replace('/\D/', '', $this->paciente->celular ?? '');
        $cep = preg_replace('/\D/', '', $this->paciente->cep ?? '');

        $dados = [
            'nome' => $this->paciente->nome,
            'tipoPessoa' => 'F',
            'cpfCnpj' => $cpf,
        ];

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
                'cidade' => $this->paciente->cidade ?? '',
                'uf' => $this->paciente->uf ?? '',
                'cep' => $cep,
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
