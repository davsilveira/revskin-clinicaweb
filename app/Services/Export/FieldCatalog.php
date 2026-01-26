<?php

namespace App\Services\Export;

use App\Models\AtendimentoCallcenter;
use App\Models\ExportRequest;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Produto;
use App\Models\Receita;
use Illuminate\Support\Arr;

class FieldCatalog
{
    private static ?ExportRequest $exportContext = null;

    /**
     * Set export context for fields that need it.
     */
    public static function setExportContext(?ExportRequest $exportRequest): void
    {
        self::$exportContext = $exportRequest;
    }

    /**
     * Clear export context.
     */
    public static function clearExportContext(): void
    {
        self::$exportContext = null;
    }

    /**
     * Get metadata for paciente fields (key + label + optional description).
     */
    public static function pacientesFieldMetadata(): array
    {
        return collect(self::pacienteFieldDefinitions())
            ->map(function (array $definition, string $key) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get metadata for receita fields.
     */
    public static function receitasFieldMetadata(): array
    {
        return collect(self::receitaFieldDefinitions())
            ->map(function (array $definition, string $key) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get metadata for atendimento fields.
     */
    public static function atendimentosFieldMetadata(): array
    {
        return collect(self::atendimentoFieldDefinitions())
            ->map(function (array $definition, string $key) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get metadata for medico fields.
     */
    public static function medicosFieldMetadata(): array
    {
        return collect(self::medicoFieldDefinitions())
            ->map(function (array $definition, string $key) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get metadata for produto fields.
     */
    public static function produtosFieldMetadata(): array
    {
        return collect(self::produtoFieldDefinitions())
            ->map(function (array $definition, string $key) {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Resolve the value for a paciente field.
     */
    public static function resolvePacienteField(string $key, Paciente $paciente): string
    {
        $definition = Arr::get(self::pacienteFieldDefinitions(), $key);

        if (!$definition) {
            return '';
        }

        $resolver = $definition['resolver'];

        $value = $resolver($paciente);

        return self::formatValue($value);
    }

    /**
     * Resolve the value for a receita field.
     */
    public static function resolveReceitaField(string $key, Receita $receita): string
    {
        $definition = Arr::get(self::receitaFieldDefinitions(), $key);

        if (!$definition) {
            return '';
        }

        $resolver = $definition['resolver'];

        $value = $resolver($receita);

        return self::formatValue($value);
    }

    /**
     * Resolve the value for an atendimento field.
     */
    public static function resolveAtendimentoField(string $key, AtendimentoCallcenter $atendimento): string
    {
        $definition = Arr::get(self::atendimentoFieldDefinitions(), $key);

        if (!$definition) {
            return '';
        }

        $resolver = $definition['resolver'];

        $value = $resolver($atendimento);

        return self::formatValue($value);
    }

    /**
     * Resolve the value for a medico field.
     */
    public static function resolveMedicoField(string $key, Medico $medico): string
    {
        $definition = Arr::get(self::medicoFieldDefinitions(), $key);

        if (!$definition) {
            return '';
        }

        $resolver = $definition['resolver'];

        $value = $resolver($medico);

        return self::formatValue($value);
    }

    /**
     * Resolve the value for a produto field.
     */
    public static function resolveProdutoField(string $key, Produto $produto): string
    {
        $definition = Arr::get(self::produtoFieldDefinitions(), $key);

        if (!$definition) {
            return '';
        }

        $resolver = $definition['resolver'];

        $value = $resolver($produto);

        return self::formatValue($value);
    }

    /**
     * Format value for CSV export.
     */
    private static function formatValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Definitions for paciente exportable fields.
     */
    private static function pacienteFieldDefinitions(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'resolver' => fn (Paciente $paciente) => $paciente->id,
            ],
            'codigo' => [
                'label' => 'Código',
                'resolver' => fn (Paciente $paciente) => $paciente->codigo,
            ],
            'nome' => [
                'label' => 'Nome',
                'resolver' => fn (Paciente $paciente) => $paciente->nome,
            ],
            'cpf' => [
                'label' => 'CPF',
                'resolver' => fn (Paciente $paciente) => $paciente->cpf,
            ],
            'rg' => [
                'label' => 'RG',
                'resolver' => fn (Paciente $paciente) => $paciente->rg,
            ],
            'data_nascimento' => [
                'label' => 'Data de Nascimento',
                'resolver' => fn (Paciente $paciente) => $paciente->data_nascimento?->format('d/m/Y'),
            ],
            'idade' => [
                'label' => 'Idade',
                'resolver' => fn (Paciente $paciente) => $paciente->idade,
            ],
            'sexo' => [
                'label' => 'Sexo',
                'resolver' => fn (Paciente $paciente) => $paciente->sexo,
            ],
            'fototipo' => [
                'label' => 'Fototipo',
                'resolver' => fn (Paciente $paciente) => $paciente->fototipo,
            ],
            'telefone1' => [
                'label' => 'Telefone 1',
                'resolver' => fn (Paciente $paciente) => $paciente->telefone1,
            ],
            'telefone2' => [
                'label' => 'Telefone 2',
                'resolver' => fn (Paciente $paciente) => $paciente->telefone2,
            ],
            'telefone3' => [
                'label' => 'Telefone 3',
                'resolver' => fn (Paciente $paciente) => $paciente->telefone3,
            ],
            'telefone_principal' => [
                'label' => 'Telefone Principal',
                'resolver' => fn (Paciente $paciente) => $paciente->telefone_principal,
            ],
            'email1' => [
                'label' => 'E-mail 1',
                'resolver' => fn (Paciente $paciente) => $paciente->email1,
            ],
            'email2' => [
                'label' => 'E-mail 2',
                'resolver' => fn (Paciente $paciente) => $paciente->email2,
            ],
            'email_principal' => [
                'label' => 'E-mail Principal',
                'resolver' => fn (Paciente $paciente) => $paciente->email_principal,
            ],
            'tipo_endereco' => [
                'label' => 'Tipo de Endereço',
                'resolver' => fn (Paciente $paciente) => $paciente->tipo_endereco,
            ],
            'endereco' => [
                'label' => 'Endereço',
                'resolver' => fn (Paciente $paciente) => $paciente->endereco,
            ],
            'numero' => [
                'label' => 'Número',
                'resolver' => fn (Paciente $paciente) => $paciente->numero,
            ],
            'complemento' => [
                'label' => 'Complemento',
                'resolver' => fn (Paciente $paciente) => $paciente->complemento,
            ],
            'bairro' => [
                'label' => 'Bairro',
                'resolver' => fn (Paciente $paciente) => $paciente->bairro,
            ],
            'cidade' => [
                'label' => 'Cidade',
                'resolver' => fn (Paciente $paciente) => $paciente->cidade,
            ],
            'uf' => [
                'label' => 'UF',
                'resolver' => fn (Paciente $paciente) => $paciente->uf,
            ],
            'pais' => [
                'label' => 'País',
                'resolver' => fn (Paciente $paciente) => $paciente->pais,
            ],
            'cep' => [
                'label' => 'CEP',
                'resolver' => fn (Paciente $paciente) => $paciente->cep,
            ],
            'endereco_completo' => [
                'label' => 'Endereço Completo',
                'resolver' => fn (Paciente $paciente) => $paciente->endereco_completo,
            ],
            'medico_id' => [
                'label' => 'ID do Médico',
                'resolver' => fn (Paciente $paciente) => $paciente->medico_id,
            ],
            'medico_nome' => [
                'label' => 'Nome do Médico',
                'resolver' => fn (Paciente $paciente) => $paciente->medico?->nome,
            ],
            'medico_crm' => [
                'label' => 'CRM do Médico',
                'resolver' => fn (Paciente $paciente) => $paciente->medico?->crm,
            ],
            'indicado_por' => [
                'label' => 'Indicado Por',
                'resolver' => fn (Paciente $paciente) => $paciente->indicado_por,
            ],
            'anotacoes' => [
                'label' => 'Anotações',
                'resolver' => fn (Paciente $paciente) => $paciente->anotacoes,
            ],
            'ativo' => [
                'label' => 'Ativo',
                'resolver' => fn (Paciente $paciente) => $paciente->ativo,
            ],
            'total_receitas' => [
                'label' => 'Total de Receitas',
                'resolver' => fn (Paciente $paciente) => $paciente->receitas()->count(),
            ],
            'ultima_receita_data' => [
                'label' => 'Data da Última Receita',
                'resolver' => fn (Paciente $paciente) => $paciente->receitas()->latest('data_receita')->first()?->data_receita?->format('d/m/Y'),
            ],
            'created_at' => [
                'label' => 'Data de Criação',
                'resolver' => fn (Paciente $paciente) => $paciente->created_at,
            ],
            'updated_at' => [
                'label' => 'Data de Atualização',
                'resolver' => fn (Paciente $paciente) => $paciente->updated_at,
            ],
        ];
    }

    /**
     * Definitions for receita exportable fields.
     */
    private static function receitaFieldDefinitions(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'resolver' => fn (Receita $receita) => $receita->id,
            ],
            'numero' => [
                'label' => 'Número',
                'resolver' => fn (Receita $receita) => $receita->numero,
            ],
            'data_receita' => [
                'label' => 'Data da Receita',
                'resolver' => fn (Receita $receita) => $receita->data_receita?->format('d/m/Y'),
            ],
            'status' => [
                'label' => 'Status',
                'resolver' => fn (Receita $receita) => $receita->status,
            ],
            'status_label' => [
                'label' => 'Status (Descrição)',
                'resolver' => fn (Receita $receita) => $receita->status_label,
            ],
            'paciente_id' => [
                'label' => 'ID do Paciente',
                'resolver' => fn (Receita $receita) => $receita->paciente_id,
            ],
            'paciente_nome' => [
                'label' => 'Nome do Paciente',
                'resolver' => fn (Receita $receita) => $receita->paciente?->nome,
            ],
            'paciente_cpf' => [
                'label' => 'CPF do Paciente',
                'resolver' => fn (Receita $receita) => $receita->paciente?->cpf,
            ],
            'paciente_codigo' => [
                'label' => 'Código do Paciente',
                'resolver' => fn (Receita $receita) => $receita->paciente?->codigo,
            ],
            'medico_id' => [
                'label' => 'ID do Médico',
                'resolver' => fn (Receita $receita) => $receita->medico_id,
            ],
            'medico_nome' => [
                'label' => 'Nome do Médico',
                'resolver' => fn (Receita $receita) => $receita->medico?->nome,
            ],
            'medico_crm' => [
                'label' => 'CRM do Médico',
                'resolver' => fn (Receita $receita) => $receita->medico?->crm,
            ],
            'medico_especialidade' => [
                'label' => 'Especialidade do Médico',
                'resolver' => fn (Receita $receita) => $receita->medico?->especialidade,
            ],
            'subtotal' => [
                'label' => 'Subtotal',
                'resolver' => fn (Receita $receita) => number_format($receita->subtotal, 2, ',', '.'),
            ],
            'desconto_percentual' => [
                'label' => 'Desconto (%)',
                'resolver' => fn (Receita $receita) => number_format($receita->desconto_percentual, 2, ',', '.'),
            ],
            'desconto_valor' => [
                'label' => 'Valor do Desconto',
                'resolver' => fn (Receita $receita) => number_format($receita->desconto_valor, 2, ',', '.'),
            ],
            'desconto_motivo' => [
                'label' => 'Motivo do Desconto',
                'resolver' => fn (Receita $receita) => $receita->desconto_motivo,
            ],
            'valor_caixa' => [
                'label' => 'Valor da Caixa',
                'resolver' => fn (Receita $receita) => number_format($receita->valor_caixa, 2, ',', '.'),
            ],
            'valor_frete' => [
                'label' => 'Valor do Frete',
                'resolver' => fn (Receita $receita) => number_format($receita->valor_frete, 2, ',', '.'),
            ],
            'valor_total' => [
                'label' => 'Valor Total',
                'resolver' => fn (Receita $receita) => number_format($receita->valor_total, 2, ',', '.'),
            ],
            'anotacoes' => [
                'label' => 'Anotações',
                'resolver' => fn (Receita $receita) => $receita->anotacoes,
            ],
            'anotacoes_paciente' => [
                'label' => 'Anotações do Paciente',
                'resolver' => fn (Receita $receita) => $receita->anotacoes_paciente,
            ],
            'total_itens' => [
                'label' => 'Total de Itens',
                'resolver' => fn (Receita $receita) => $receita->itens()->count(),
            ],
            'produtos_lista' => [
                'label' => 'Lista de Produtos',
                'resolver' => fn (Receita $receita) => $receita->itens()->with('produto')->get()->map(fn ($item) => $item->produto?->nome)->filter()->implode(', '),
            ],
            'ativo' => [
                'label' => 'Ativo',
                'resolver' => fn (Receita $receita) => $receita->ativo,
            ],
            'created_at' => [
                'label' => 'Data de Criação',
                'resolver' => fn (Receita $receita) => $receita->created_at,
            ],
            'updated_at' => [
                'label' => 'Data de Atualização',
                'resolver' => fn (Receita $receita) => $receita->updated_at,
            ],
        ];
    }

    /**
     * Definitions for atendimento exportable fields.
     */
    private static function atendimentoFieldDefinitions(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->id,
            ],
            'status' => [
                'label' => 'Status',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->status,
            ],
            'status_label' => [
                'label' => 'Status (Descrição)',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->status_label,
            ],
            'receita_id' => [
                'label' => 'ID da Receita',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->receita_id,
            ],
            'receita_numero' => [
                'label' => 'Número da Receita',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->receita?->numero,
            ],
            'receita_data' => [
                'label' => 'Data da Receita',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->receita?->data_receita?->format('d/m/Y'),
            ],
            'receita_valor_total' => [
                'label' => 'Valor Total da Receita',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->receita ? number_format($atendimento->receita->valor_total, 2, ',', '.') : '',
            ],
            'paciente_id' => [
                'label' => 'ID do Paciente',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->paciente_id,
            ],
            'paciente_nome' => [
                'label' => 'Nome do Paciente',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->paciente?->nome,
            ],
            'paciente_cpf' => [
                'label' => 'CPF do Paciente',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->paciente?->cpf,
            ],
            'paciente_telefone' => [
                'label' => 'Telefone do Paciente',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->paciente?->telefone_principal,
            ],
            'medico_id' => [
                'label' => 'ID do Médico',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->medico_id,
            ],
            'medico_nome' => [
                'label' => 'Nome do Médico',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->medico?->nome,
            ],
            'medico_crm' => [
                'label' => 'CRM do Médico',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->medico?->crm,
            ],
            'data_abertura' => [
                'label' => 'Data de Abertura',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->data_abertura,
            ],
            'data_alteracao' => [
                'label' => 'Data de Alteração',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->data_alteracao,
            ],
            'usuario_id' => [
                'label' => 'ID do Usuário Responsável',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->usuario_id,
            ],
            'usuario_nome' => [
                'label' => 'Nome do Usuário Responsável',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->usuario?->name,
            ],
            'usuario_alteracao_id' => [
                'label' => 'ID do Usuário que Alterou',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->usuario_alteracao_id,
            ],
            'usuario_alteracao_nome' => [
                'label' => 'Nome do Usuário que Alterou',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->usuarioAlteracao?->name,
            ],
            'total_acompanhamentos' => [
                'label' => 'Total de Acompanhamentos',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->acompanhamentos()->count(),
            ],
            'ultimo_acompanhamento_data' => [
                'label' => 'Data do Último Acompanhamento',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->acompanhamentos()->latest('data_registro')->first()?->data_registro?->format('d/m/Y H:i'),
            ],
            'tempo_resolucao' => [
                'label' => 'Tempo de Resolução (dias)',
                'resolver' => function (AtendimentoCallcenter $atendimento) {
                    if (!$atendimento->data_abertura || !$atendimento->data_alteracao) {
                        return '';
                    }
                    return $atendimento->data_abertura->diffInDays($atendimento->data_alteracao);
                },
            ],
            'ativo' => [
                'label' => 'Ativo',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->ativo,
            ],
            'created_at' => [
                'label' => 'Data de Criação',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->created_at,
            ],
            'updated_at' => [
                'label' => 'Data de Atualização',
                'resolver' => fn (AtendimentoCallcenter $atendimento) => $atendimento->updated_at,
            ],
        ];
    }

    /**
     * Definitions for medico exportable fields.
     */
    private static function medicoFieldDefinitions(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'resolver' => fn (Medico $medico) => $medico->id,
            ],
            'nome' => [
                'label' => 'Nome',
                'resolver' => fn (Medico $medico) => $medico->nome,
            ],
            'apelido' => [
                'label' => 'Apelido',
                'resolver' => fn (Medico $medico) => $medico->apelido,
            ],
            'nome_completo' => [
                'label' => 'Nome Completo',
                'resolver' => fn (Medico $medico) => $medico->nome_completo,
            ],
            'crm' => [
                'label' => 'CRM',
                'resolver' => fn (Medico $medico) => $medico->crm,
            ],
            'uf_crm' => [
                'label' => 'UF do CRM',
                'resolver' => fn (Medico $medico) => $medico->uf_crm,
            ],
            'crm_completo' => [
                'label' => 'CRM Completo',
                'resolver' => fn (Medico $medico) => $medico->crm && $medico->uf_crm ? "{$medico->crm}-{$medico->uf_crm}" : '',
            ],
            'cpf' => [
                'label' => 'CPF',
                'resolver' => fn (Medico $medico) => $medico->cpf,
            ],
            'rg' => [
                'label' => 'RG',
                'resolver' => fn (Medico $medico) => $medico->rg,
            ],
            'especialidade' => [
                'label' => 'Especialidade',
                'resolver' => fn (Medico $medico) => $medico->especialidade,
            ],
            'telefone1' => [
                'label' => 'Telefone 1',
                'resolver' => fn (Medico $medico) => $medico->telefone1,
            ],
            'telefone2' => [
                'label' => 'Telefone 2',
                'resolver' => fn (Medico $medico) => $medico->telefone2,
            ],
            'telefone3' => [
                'label' => 'Telefone 3',
                'resolver' => fn (Medico $medico) => $medico->telefone3,
            ],
            'email1' => [
                'label' => 'E-mail 1',
                'resolver' => fn (Medico $medico) => $medico->email1,
            ],
            'email2' => [
                'label' => 'E-mail 2',
                'resolver' => fn (Medico $medico) => $medico->email2,
            ],
            'tipo_endereco' => [
                'label' => 'Tipo de Endereço',
                'resolver' => fn (Medico $medico) => $medico->tipo_endereco,
            ],
            'endereco' => [
                'label' => 'Endereço',
                'resolver' => fn (Medico $medico) => $medico->endereco,
            ],
            'numero' => [
                'label' => 'Número',
                'resolver' => fn (Medico $medico) => $medico->numero,
            ],
            'complemento' => [
                'label' => 'Complemento',
                'resolver' => fn (Medico $medico) => $medico->complemento,
            ],
            'bairro' => [
                'label' => 'Bairro',
                'resolver' => fn (Medico $medico) => $medico->bairro,
            ],
            'cidade' => [
                'label' => 'Cidade',
                'resolver' => fn (Medico $medico) => $medico->cidade,
            ],
            'uf' => [
                'label' => 'UF',
                'resolver' => fn (Medico $medico) => $medico->uf,
            ],
            'cep' => [
                'label' => 'CEP',
                'resolver' => fn (Medico $medico) => $medico->cep,
            ],
            'clinica_id' => [
                'label' => 'ID da Clínica',
                'resolver' => fn (Medico $medico) => $medico->clinica_id,
            ],
            'clinica_nome' => [
                'label' => 'Nome da Clínica',
                'resolver' => fn (Medico $medico) => $medico->clinica?->nome,
            ],
            'clinica_cnpj' => [
                'label' => 'CNPJ da Clínica',
                'resolver' => fn (Medico $medico) => $medico->clinica?->cnpj,
            ],
            'clinicas_lista' => [
                'label' => 'Lista de Clínicas',
                'resolver' => fn (Medico $medico) => $medico->clinicas()->pluck('nome')->implode(', '),
            ],
            'rodape_receita' => [
                'label' => 'Rodapé da Receita',
                'resolver' => fn (Medico $medico) => $medico->rodape_receita,
            ],
            'assinatura_path' => [
                'label' => 'Caminho da Assinatura',
                'resolver' => fn (Medico $medico) => $medico->assinatura_path,
            ],
            'assinatura_url' => [
                'label' => 'URL da Assinatura',
                'resolver' => fn (Medico $medico) => $medico->assinatura_url,
            ],
            'anotacoes' => [
                'label' => 'Anotações',
                'resolver' => fn (Medico $medico) => $medico->anotacoes,
            ],
            'total_pacientes' => [
                'label' => 'Total de Pacientes',
                'resolver' => fn (Medico $medico) => $medico->pacientes()->count(),
            ],
            'total_receitas' => [
                'label' => 'Total de Receitas',
                'resolver' => fn (Medico $medico) => $medico->receitas()->count(),
            ],
            'valor_total_receitas' => [
                'label' => 'Valor Total das Receitas',
                'resolver' => fn (Medico $medico) => number_format($medico->receitas()->sum('valor_total'), 2, ',', '.'),
            ],
            'media_valor_receita' => [
                'label' => 'Média do Valor das Receitas',
                'resolver' => function (Medico $medico) {
                    $count = $medico->receitas()->count();
                    if ($count === 0) {
                        return '';
                    }
                    $total = $medico->receitas()->sum('valor_total');
                    return number_format($total / $count, 2, ',', '.');
                },
            ],
            'ativo' => [
                'label' => 'Ativo',
                'resolver' => fn (Medico $medico) => $medico->ativo,
            ],
            'created_at' => [
                'label' => 'Data de Criação',
                'resolver' => fn (Medico $medico) => $medico->created_at,
            ],
            'updated_at' => [
                'label' => 'Data de Atualização',
                'resolver' => fn (Medico $medico) => $medico->updated_at,
            ],
        ];
    }

    /**
     * Definitions for produto exportable fields.
     */
    private static function produtoFieldDefinitions(): array
    {
        return [
            'id' => [
                'label' => 'ID',
                'resolver' => fn (Produto $produto) => $produto->id,
            ],
            'codigo' => [
                'label' => 'Código',
                'resolver' => fn (Produto $produto) => $produto->codigo,
            ],
            'codigo_cq' => [
                'label' => 'Código CQ',
                'resolver' => fn (Produto $produto) => $produto->codigo_cq,
            ],
            'nome' => [
                'label' => 'Nome',
                'resolver' => fn (Produto $produto) => $produto->nome,
            ],
            'nome_completo' => [
                'label' => 'Nome Completo',
                'resolver' => fn (Produto $produto) => $produto->nome_completo,
            ],
            'descricao' => [
                'label' => 'Descrição',
                'resolver' => fn (Produto $produto) => $produto->descricao,
            ],
            'categoria' => [
                'label' => 'Categoria',
                'resolver' => fn (Produto $produto) => $produto->categoria,
            ],
            'local_uso' => [
                'label' => 'Local de Uso',
                'resolver' => fn (Produto $produto) => $produto->local_uso,
            ],
            'modo_uso' => [
                'label' => 'Modo de Uso',
                'resolver' => fn (Produto $produto) => $produto->modo_uso,
            ],
            'preco' => [
                'label' => 'Preço',
                'resolver' => fn (Produto $produto) => number_format($produto->preco, 2, ',', '.'),
            ],
            'preco_custo' => [
                'label' => 'Preço de Custo',
                'resolver' => fn (Produto $produto) => number_format($produto->preco_custo, 2, ',', '.'),
            ],
            'margem_lucro' => [
                'label' => 'Margem de Lucro (%)',
                'resolver' => function (Produto $produto) {
                    if (!$produto->preco_custo || $produto->preco_custo == 0) {
                        return '';
                    }
                    $margem = (($produto->preco - $produto->preco_custo) / $produto->preco_custo) * 100;
                    return number_format($margem, 2, ',', '.');
                },
            ],
            'unidade' => [
                'label' => 'Unidade',
                'resolver' => fn (Produto $produto) => $produto->unidade,
            ],
            'estoque_minimo' => [
                'label' => 'Estoque Mínimo',
                'resolver' => fn (Produto $produto) => $produto->estoque_minimo,
            ],
            'tiny_id' => [
                'label' => 'ID Tiny ERP',
                'resolver' => fn (Produto $produto) => $produto->tiny_id,
            ],
            'anotacoes' => [
                'label' => 'Anotações',
                'resolver' => fn (Produto $produto) => $produto->anotacoes,
            ],
            'total_vendas' => [
                'label' => 'Total de Vendas',
                'resolver' => fn (Produto $produto) => $produto->receitaItens()->count(),
            ],
            'quantidade_total_vendida' => [
                'label' => 'Quantidade Total Vendida',
                'resolver' => fn (Produto $produto) => $produto->receitaItens()->sum('quantidade'),
            ],
            'receita_total' => [
                'label' => 'Receita Total',
                'resolver' => fn (Produto $produto) => number_format($produto->receitaItens()->sum('valor_total'), 2, ',', '.'),
            ],
            'media_preco_venda' => [
                'label' => 'Média do Preço de Venda',
                'resolver' => function (Produto $produto) {
                    $count = $produto->receitaItens()->count();
                    if ($count === 0) {
                        return '';
                    }
                    $total = $produto->receitaItens()->sum('valor_total');
                    return number_format($total / $count, 2, ',', '.');
                },
            ],
            'ultima_venda_data' => [
                'label' => 'Data da Última Venda',
                'resolver' => fn (Produto $produto) => $produto->receitaItens()->latest('created_at')->first()?->created_at?->format('d/m/Y'),
            ],
            'ativo' => [
                'label' => 'Ativo',
                'resolver' => fn (Produto $produto) => $produto->ativo,
            ],
            'created_at' => [
                'label' => 'Data de Criação',
                'resolver' => fn (Produto $produto) => $produto->created_at,
            ],
            'updated_at' => [
                'label' => 'Data de Atualização',
                'resolver' => fn (Produto $produto) => $produto->updated_at,
            ],
        ];
    }
}
