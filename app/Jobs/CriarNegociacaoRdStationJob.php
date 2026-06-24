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

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public Receita $receita
    ) {
        $this->onQueue('rd-sync');
    }

    public function handle(): void
    {
        if (! Setting::get('rd_enabled', false)) {
            Log::info('RD Station CRM: Integração desabilitada', [
                'receita_id' => $this->receita->id,
            ]);

            return;
        }

        $receita = $this->receita->fresh(['paciente', 'medico.linkedUser', 'itens.produto']);

        if ($receita->rd_deal_id) {
            Log::info('RD Station CRM: Negociação já sincronizada, ignorando', [
                'receita_id' => $receita->id,
                'rd_deal_id' => $receita->rd_deal_id,
            ]);

            return;
        }

        if (! $receita->paciente) {
            Log::warning('RD Station CRM: Receita não possui paciente', [
                'receita_id' => $receita->id,
            ]);

            return;
        }

        $client = new RdStationCrmClient;
        $paciente = $receita->paciente;

        $ownerId = trim(Setting::get('rd_owner_id', '') ?? '');
        if (empty($ownerId)) {
            Log::error('RD Station CRM: Owner ID não configurado', ['receita_id' => $receita->id]);
            throw new \Exception('Owner ID não configurado. Configure em Configurações > Integrações > RD Station CRM.');
        }

        Log::info('RD Station CRM: Iniciando criação de negociação', [
            'receita_id' => $receita->id,
            'paciente' => $paciente->nome,
            'owner_id' => $ownerId,
        ]);

        // 1. Upsert Organization
        Log::debug('RD Station CRM: [Etapa 1/4] Upsert organização', [
            'receita_id' => $receita->id,
            'paciente_id' => $paciente->id,
            'paciente_nome' => $paciente->nome,
            'rd_organization_id' => $paciente->rd_organization_id,
        ]);
        $orgResult = $client->upsertOrganizacao(
            $paciente->nome,
            $paciente->rd_organization_id ?: null,
            $ownerId
        );
        if ($orgResult['status'] !== 'success') {
            Log::error('RD Station CRM: [Etapa 1/4] Falha ao criar/encontrar organização', [
                'receita_id' => $receita->id,
                'error' => $orgResult['message'] ?? 'Erro desconhecido',
                'response' => $orgResult,
            ]);
            throw new \Exception('Erro ao criar organização no RD Station: '.($orgResult['message'] ?? ''));
        }

        $orgData = $orgResult['data']['data'] ?? $orgResult['data'] ?? [];
        $organizationId = $orgData['id'] ?? null;

        if (! $organizationId) {
            Log::error('RD Station CRM: [Etapa 1/4] ID da organização não encontrado na resposta', [
                'response' => $orgResult,
            ]);
            throw new \Exception('Não foi possível obter ID da organização no RD Station');
        }

        $orgNameFromApi = $orgData['name'] ?? null;
        Log::debug('RD Station CRM: [Etapa 1/4] Organização OK', [
            'receita_id' => $receita->id,
            'paciente_id' => $paciente->id,
            'paciente_nome' => $paciente->nome,
            'organization_id' => $organizationId,
            'org_name' => $orgNameFromApi,
            'action' => $orgResult['action'] ?? 'unknown',
        ]);

        $paciente->update(['rd_organization_id' => $organizationId]);

        // 2. Upsert Contact
        $telefone = $paciente->telefonePrincipal;
        if ($telefone) {
            $telefone = preg_replace('/\D/', '', $telefone);
        }
        $email = $paciente->emailPrincipal;

        Log::debug('RD Station CRM: [Etapa 2/4] Upsert contato', [
            'nome' => $paciente->nome,
            'telefone' => $telefone ? '***'.substr($telefone, -4) : null,
            'email' => $email ? '***@'.(explode('@', $email)[1] ?? '') : null,
            'organization_id' => $organizationId,
        ]);

        $contactResult = $client->upsertContato($paciente->nome, $telefone, $email, $organizationId, $ownerId);
        if ($contactResult['status'] !== 'success') {
            Log::error('RD Station CRM: [Etapa 2/4] Falha ao criar/atualizar contato', [
                'receita_id' => $receita->id,
                'action_attempted' => $contactResult['action'] ?? 'unknown',
                'error' => $contactResult['message'] ?? 'Erro desconhecido',
                'status_code' => $contactResult['status_code'] ?? null,
                'response' => $contactResult,
            ]);
            throw new \Exception('Erro ao criar contato no RD Station: '.($contactResult['message'] ?? ''));
        }

        $contactData = $contactResult['data']['data'] ?? $contactResult['data'] ?? [];
        $contactId = $contactData['id'] ?? null;

        if (! $contactId) {
            Log::error('RD Station CRM: [Etapa 2/4] ID do contato não encontrado na resposta', [
                'response' => $contactResult,
            ]);
            throw new \Exception('Não foi possível obter ID do contato no RD Station');
        }

        Log::debug('RD Station CRM: [Etapa 2/4] Contato OK', [
            'contact_id' => $contactId,
            'action' => $contactResult['action'] ?? 'unknown',
        ]);

        $paciente->update(['rd_contact_id' => $contactId]);

        // 3. Create Deal
        $stageId = Setting::get('rd_stage_id', '6929f60257dcba001d9b375b');
        $medicoFieldId = Setting::get('rd_medico_field_id', '69a955ea78fde3001f6f61dc');
        $receitaFieldId = Setting::get('rd_receita_field_id', '699efc3a13a467001cb81ea1');

        $medicoNome = $receita->medico?->nome ?? 'Não informado';
        $receitaNumero = $receita->numero ?? 'REC-'.$receita->id;

        $medicoFieldKey = $client->resolverChaveCustomField($medicoFieldId);
        $receitaFieldKey = $client->resolverChaveCustomField($receitaFieldId);

        $valorNegociacao = round(floatval($receita->valor_total ?? 0), 2);

        $dealData = [
            'name' => "Receita #{$receitaNumero} - {$paciente->nome}",
            'status' => 'ongoing',
            'stage_id' => $stageId,
            'owner_id' => $ownerId,
            'organization_id' => $organizationId,
            'contact_ids' => [$contactId],
            'one_time_price' => $valorNegociacao,
            'custom_fields' => [
                $medicoFieldKey => $medicoNome,
                $receitaFieldKey => $receitaNumero,
            ],
        ];

        Log::debug('RD Station CRM: [Etapa 3/4] Criando negociação — payload completo', $dealData);

        $dealResult = $client->criarNegociacao($dealData);
        if ($dealResult['status'] !== 'success') {
            Log::error('RD Station CRM: [Etapa 3/4] Falha ao criar negociação', [
                'receita_id' => $receita->id,
                'error' => $dealResult['message'] ?? 'Erro desconhecido',
                'status_code' => $dealResult['status_code'] ?? null,
                'response' => $dealResult,
            ]);
            throw new \Exception('Erro ao criar negociação no RD Station: '.($dealResult['message'] ?? ''));
        }

        $dealResponseData = $dealResult['data']['data'] ?? $dealResult['data'] ?? [];
        $dealId = $dealResponseData['id'] ?? null;

        if (! $dealId) {
            Log::error('RD Station CRM: [Etapa 3/4] ID da negociação não encontrado na resposta', [
                'response' => $dealResult,
            ]);
            throw new \Exception('Não foi possível obter ID da negociação no RD Station');
        }

        $receita->update(['rd_deal_id' => $dealId]);

        Log::info('RD Station CRM: [Etapa 3/4] Negociação criada com sucesso', [
            'receita_id' => $receita->id,
            'rd_deal_id' => $dealId,
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
        ]);

        // 4. Add Produto Padrão to Deal
        $produtoPadraoId = Setting::get('rd_produto_padrao_id');
        if ($produtoPadraoId) {
            Log::debug('RD Station CRM: [Etapa 4/4] Adicionando produto à negociação', [
                'deal_id' => $dealId,
                'produto_padrao_id' => $produtoPadraoId,
            ]);

            $productData = [
                'product_id' => $produtoPadraoId,
                'price' => $valorNegociacao,
                'quantity' => 1,
                'discount' => 0,
                'discount_type' => 'amount',
                'billing_frequency' => 'one-time',
            ];

            Log::debug('RD Station CRM: [Etapa 4/4] Payload do produto', $productData);

            $productResult = $client->criarProdutoNegociacao($dealId, $productData);

            if ($productResult['status'] !== 'success') {
                Log::warning('RD Station CRM: Erro ao adicionar produto à negociação (não crítico)', [
                    'receita_id' => $receita->id,
                    'deal_id' => $dealId,
                    'produto_padrao_id' => $produtoPadraoId,
                    'error' => $productResult['message'] ?? 'Erro desconhecido',
                    'status_code' => $productResult['status_code'] ?? null,
                    'response_completa' => $productResult,
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
