<?php

namespace App\Services;

use Illuminate\Support\Arr;

class InfosimplesResultFormatter
{
    public static function extractCpfDetails(array $payload): ?array
    {
        $nome = Arr::get($payload, 'nome');
        $situacao = Arr::get($payload, 'situacao_cadastral');
        $dataInscricao = Arr::get($payload, 'data_inscricao');

        if (!$nome && !$situacao && !$dataInscricao) {
            return null;
        }

        return [
            'holder_name' => $nome,
            'status' => $situacao,
            'registration_date' => $dataInscricao,
        ];
    }

    public static function extractCnpjDetails(array $payload): ?array
    {
        $razaoSocial = Arr::get($payload, 'razao_social');
        $situacao = Arr::get($payload, 'situacao_cadastral');
        $situacaoData = Arr::get($payload, 'situacao_cadastral_data');

        if (!$razaoSocial && !$situacao && !$situacaoData) {
            return null;
        }

        $endereco = array_filter([
            Arr::get($payload, 'endereco_logradouro'),
            Arr::get($payload, 'endereco_numero'),
            Arr::get($payload, 'endereco_complemento'),
            Arr::get($payload, 'endereco_bairro'),
            Arr::get($payload, 'endereco_municipio'),
            Arr::get($payload, 'endereco_uf'),
            Arr::get($payload, 'endereco_cep'),
        ]);

        $extra = [];

        $extra[] = [
            'label' => 'Nome fantasia',
            'value' => Arr::get($payload, 'nome_fantasia') ?: '—',
        ];

        $extra[] = [
            'label' => 'Capital social',
            'value' => Arr::get($payload, 'capital_social') ?: '—',
        ];

        $extra[] = [
            'label' => 'Porte',
            'value' => Arr::get($payload, 'porte') ?: '—',
        ];

        $extra[] = [
            'label' => 'Natureza jurídica',
            'value' => Arr::get($payload, 'natureza_juridica') ?: '—',
        ];

        if (!empty($endereco)) {
            $extra[] = [
                'label' => 'Endereço',
                'value' => implode(', ', array_filter($endereco)),
            ];
        }

        $extra[] = [
            'label' => 'E-mail',
            'value' => Arr::get($payload, 'email') ?: '—',
        ];

        $extra[] = [
            'label' => 'Telefone',
            'value' => Arr::get($payload, 'telefone') ?: '—',
        ];

        $atividadePrincipal = Arr::get($payload, 'atividade_economica');
        if ($atividadePrincipal) {
            $extra[] = [
                'label' => 'Atividade principal',
                'value' => $atividadePrincipal,
            ];
        }

        $atividadesSecundarias = Arr::get($payload, 'atividade_economica_secundaria_lista', []);
        if (is_array($atividadesSecundarias) && !empty($atividadesSecundarias)) {
            $extra[] = [
                'label' => 'Atividades secundárias',
                'value' => $atividadesSecundarias,
            ];
        }

        $socios = Arr::get($payload, 'qsa', []);
        if (is_array($socios) && !empty($socios)) {
            $extra[] = [
                'label' => 'Quadro societário',
                'value' => array_map(function ($socio) {
                    return trim((string) Arr::get($socio, 'nome') . ' - ' . (string) Arr::get($socio, 'qualificacao'));
                }, $socios),
            ];
        }

        return [
            'summary' => [
                'company_name' => $razaoSocial,
                'status' => $situacao,
                'status_date' => $situacaoData,
            ],
            'extra' => $extra,
        ];
    }

    public static function extractCroDetails(array $payload): ?array
    {
        $nome = Arr::get($payload, 'nome');
        $categoria = Arr::get($payload, 'categoria');
        $status = Arr::get($payload, 'situacao') ?: Arr::get($payload, 'situacao_detalhe');

        if (!$nome && !$categoria && !$status) {
            return null;
        }

        $extra = [];

        $extra[] = [
            'label' => 'Número da inscrição',
            'value' => Arr::get($payload, 'inscricao') ?: '—',
        ];

        $extra[] = [
            'label' => 'Data da inscrição',
            'value' => Arr::get($payload, 'inscricao_data') ?: '—',
        ];

        $extra[] = [
            'label' => 'Tipo de inscrição',
            'value' => Arr::get($payload, 'inscricao_tipo') ?: '—',
        ];

        $extra[] = [
            'label' => 'Situação detalhada',
            'value' => Arr::get($payload, 'situacao_detalhe') ?: '—',
        ];

        $extra[] = [
            'label' => 'Data da situação',
            'value' => Arr::get($payload, 'situacao_data') ?: '—',
        ];

        $extra[] = [
            'label' => 'Especialidades',
            'value' => Arr::get($payload, 'especialidades') ?: '—',
        ];

        $extra[] = [
            'label' => 'E-mail',
            'value' => Arr::get($payload, 'email') ?: '—',
        ];

        $extra[] = [
            'label' => 'Telefone',
            'value' => Arr::get($payload, 'telefone') ?: '—',
        ];

        $extra[] = [
            'label' => 'Última atualização',
            'value' => Arr::get($payload, 'atualizacao_data') ?: '—',
        ];

        return [
            'summary' => [
                'name' => $nome,
                'category' => $categoria,
                'status' => $status,
            ],
            'extra' => $extra,
        ];
    }
}

