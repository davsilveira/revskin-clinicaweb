<?php

namespace App\Jobs;

use App\Models\Receita;
use App\Models\Setting;
use App\Services\RdStationCrmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CriarNegociacaoRdStationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('rd-sync');
    }

    public function handle(): void
    {
        if (!Setting::get('rd_enabled', false)) {
            Log::info('RD Station CRM: Integração desabilitada', [
                'receita_id' => $this->receita->id,
            ]);
            return;
        }

        $receita = $this->receita->fresh(['paciente', 'medico.linkedUser', 'itens.produto']);

        if (!$receita->paciente) {
            Log::warning('RD Station CRM: Receita não possui paciente', [
                'receita_id' => $receita->id,
            ]);
            return;
        }

        $client = new RdStationCrmClient();
        $paciente = $receita->paciente;

        // 1. Upsert Organization
        $orgResult = $client->upsertOrganizacao($paciente->nome);
        if ($orgResult['status'] !== 'success') {
            Log::error('RD Station CRM: Erro ao criar/encontrar organização', [
                'receita_id' => $receita->id,
                'error' => $orgResult['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception('Erro ao criar organização no RD Station: ' . ($orgResult['message'] ?? ''));
        }

        $orgData = $orgResult['data']['data'] ?? $orgResult['data'] ?? [];
        $organizationId = $orgData['id'] ?? null;

        if (!$organizationId) {
            throw new \Exception('Não foi possível obter ID da organização no RD Station');
        }

        $paciente->update(['rd_organization_id' => $organizationId]);

        // 2. Upsert Contact
        $telefone = $paciente->telefonePrincipal;
        if ($telefone) {
            $telefone = preg_replace('/\D/', '', $telefone);
        }
        $email = $paciente->emailPrincipal;

        $contactResult = $client->upsertContato($paciente->nome, $telefone, $email, $organizationId);
        if ($contactResult['status'] !== 'success') {
            Log::error('RD Station CRM: Erro ao criar/atualizar contato', [
                'receita_id' => $receita->id,
                'error' => $contactResult['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception('Erro ao criar contato no RD Station: ' . ($contactResult['message'] ?? ''));
        }

        $contactData = $contactResult['data']['data'] ?? $contactResult['data'] ?? [];
        $contactId = $contactData['id'] ?? null;

        if (!$contactId) {
            throw new \Exception('Não foi possível obter ID do contato no RD Station');
        }

        $paciente->update(['rd_contact_id' => $contactId]);

        // 3. Create Deal
        $stageId = Setting::get('rd_stage_id', '6929f60257dcba001d9b375b');
        $medicoFieldId = Setting::get('rd_medico_field_id', '69a955ea78fde3001f6f61dc');
        $receitaFieldId = Setting::get('rd_receita_field_id', '699efc3a13a467001cb81ea1');

        $medicoNome = $receita->medico?->nome ?? 'Não informado';
        $receitaNumero = $receita->numero ?? 'REC-' . $receita->id;

        $dealData = [
            'name' => "Receita #{$receitaNumero} - {$paciente->nome}",
            'status' => 'ongoing',
            'stage_id' => $stageId,
            'organization_id' => $organizationId,
            'contact_ids' => [$contactId],
            'one_time_price' => (float) $receita->valor_total,
            'custom_fields' => [
                $medicoFieldId => $medicoNome,
                $receitaFieldId => $receitaNumero,
            ],
        ];

        $dealResult = $client->criarNegociacao($dealData);
        if ($dealResult['status'] !== 'success') {
            Log::error('RD Station CRM: Erro ao criar negociação', [
                'receita_id' => $receita->id,
                'error' => $dealResult['message'] ?? 'Erro desconhecido',
            ]);
            throw new \Exception('Erro ao criar negociação no RD Station: ' . ($dealResult['message'] ?? ''));
        }

        $dealResponseData = $dealResult['data']['data'] ?? $dealResult['data'] ?? [];
        $dealId = $dealResponseData['id'] ?? null;

        if (!$dealId) {
            throw new \Exception('Não foi possível obter ID da negociação no RD Station');
        }

        $receita->update(['rd_deal_id' => $dealId]);

        Log::info('RD Station CRM: Negociação criada com sucesso', [
            'receita_id' => $receita->id,
            'rd_deal_id' => $dealId,
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
        ]);

        // 4. Add Produto Padrão to Deal
        $produtoPadraoId = Setting::get('rd_produto_padrao_id');
        if ($produtoPadraoId) {
            $productResult = $client->criarProdutoNegociacao($dealId, [
                'product_id' => $produtoPadraoId,
                'price' => (float) $receita->valor_total,
                'quantity' => 1,
                'discount' => 0,
                'discount_type' => 'amount',
                'billing_frequency' => 'one-time',
            ]);

            if ($productResult['status'] !== 'success') {
                Log::warning('RD Station CRM: Erro ao adicionar produto à negociação (não crítico)', [
                    'deal_id' => $dealId,
                    'produto_padrao_id' => $produtoPadraoId,
                    'error' => $productResult['message'] ?? 'Erro desconhecido',
                ]);
            } else {
                Log::info('RD Station CRM: Produto padrão adicionado à negociação', [
                    'deal_id' => $dealId,
                    'produto_padrao_id' => $produtoPadraoId,
                ]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RD Station CRM: Job de criação de negociação falhou', [
            'receita_id' => $this->receita->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
