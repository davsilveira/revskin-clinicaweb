<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Aquisição de Produtos</title>
    <style>
        @page {
            margin: 20mm 20mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 10mm;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #059669;
        }
        .header h1 {
            font-size: 18px;
            color: #059669;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header .periodo {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
        }
        .paciente-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .paciente-header {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 8px;
        }
        .paciente-header .nome {
            display: inline-block;
        }
        .paciente-header .telefone,
        .paciente-header .cpf,
        .paciente-header .medico {
            display: inline-block;
            margin-left: 10px;
            font-weight: normal;
        }
        table.produtos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        table.produtos th {
            background: #059669;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.produtos th.num {
            text-align: right;
        }
        table.produtos th.qtd {
            text-align: center;
        }
        table.produtos td {
            padding: 6px 4px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
        }
        table.produtos td.num {
            text-align: right;
        }
        table.produtos td.qtd {
            text-align: center;
        }
        table.produtos tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        table.produtos tfoot tr.totais td {
            background: #e5e7eb;
            color: #111827;
            font-weight: bold;
            font-size: 8px;
            padding: 8px 6px;
            border-top: 1px solid #d1d5db;
            border-bottom: none;
        }
        table.produtos tfoot tr.totais td.label-qtd {
            text-align: left;
        }
        table.produtos tfoot tr.totais td.val-frete {
            text-align: right;
        }
        table.produtos tfoot tr.totais td.val-desc {
            text-align: center;
        }
        table.produtos tfoot tr.totais td.val-total {
            text-align: right;
        }
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
    </style>
</head>
<body>
    @php
        $brl = static function ($v): string {
            return 'R$ '.number_format((float) $v, 2, ',', '.');
        };
    @endphp
    <div class="container">
    <div class="header">
        <h1>RELATÓRIO DE AQUISIÇÃO DE PRODUTOS</h1>
        <div class="periodo">
            Período: {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
        </div>
    </div>

    @php $mostrarValoresMonetarios = !empty($isAdmin); @endphp

    @foreach($dados['pacientes'] as $pacienteData)
        @php
            $cpfFormatado = '';
            if (!empty($pacienteData['paciente']['cpf'])) {
                $cpf = preg_replace('/\D/', '', $pacienteData['paciente']['cpf']);
                if (strlen($cpf) === 11) {
                    $cpfFormatado = substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
                } else {
                    $cpfFormatado = $pacienteData['paciente']['cpf'];
                }
            }
            // Sem CPF (paciente estrangeiro, p.ex.) cai no documento livre — nunca rotular passaporte como CPF.
            $documentoLinha = $cpfFormatado !== ''
                ? 'CPF: '.$cpfFormatado
                : (trim((string) ($pacienteData['paciente']['documento'] ?? '')) !== ''
                    ? ($pacienteData['paciente']['documento_label'] ?? 'Documento').': '.$pacienteData['paciente']['documento']
                    : '');
            $telRaw = preg_replace('/\D/', '', (string) ($pacienteData['paciente']['telefone'] ?? ''));
            $telefoneFmt = '';
            if (strlen($telRaw) === 11) {
                $telefoneFmt = '('.substr($telRaw, 0, 2).') '.substr($telRaw, 2, 5).'-'.substr($telRaw, 7);
            } elseif (strlen($telRaw) >= 10) {
                $telefoneFmt = '('.substr($telRaw, 0, 2).') '.substr($telRaw, 2, 4).'-'.substr($telRaw, 6);
            } elseif (!empty($pacienteData['paciente']['telefone'])) {
                $telefoneFmt = $pacienteData['paciente']['telefone'];
            }
            $tot = $pacienteData['totais'] ?? [];
        @endphp
        <div class="paciente-section">
            <div class="paciente-header">
                <span class="nome">{{ strtoupper($pacienteData['paciente']['nome']) }}</span>
                @if($telefoneFmt !== '')
                    <span class="telefone">{{ $telefoneFmt }}</span>
                @endif
                @if($documentoLinha !== '')
                    <span class="cpf">{{ $documentoLinha }}</span>
                @endif
                @if(!empty($isAdmin) && !empty($pacienteData['paciente']['medico_nome']))
                    <span class="medico">{{ $pacienteData['paciente']['medico_nome'] }}</span>
                @endif
            </div>

            <table class="produtos">
                <thead>
                    <tr>
                        <th style="width: {{ $mostrarValoresMonetarios ? '28%' : '52%' }};">Produto</th>
                        <th style="width: 12%;">Data receita</th>
                        <th style="width: 12%;">Data manip.</th>
                        @if($mostrarValoresMonetarios)
                            <th class="num" style="width: 14%;">Vlr. unit.</th>
                            <th class="qtd" style="width: 8%;">Qtd</th>
                            <th class="num" style="width: 14%;">Total</th>
                        @else
                            <th class="qtd" style="width: 12%;">Qtd</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacienteData['produtos'] as $p)
                        <tr>
                            <td>{{ $p['produto_nome'] }}</td>
                            <td>{{ $p['data_receita'] }}</td>
                            <td>{{ $p['data_aquisicao'] }}</td>
                            @if($mostrarValoresMonetarios)
                                <td class="num">{{ $brl($p['valor_unitario'] ?? 0) }}</td>
                                <td class="qtd">{{ (int) ($p['quantidade'] ?? 0) }}</td>
                                <td class="num">{{ $brl($p['valor_total'] ?? 0) }}</td>
                            @else
                                <td class="qtd">{{ (int) ($p['quantidade'] ?? 0) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @if($mostrarValoresMonetarios)
                        <tr class="totais">
                            <td colspan="3" class="label-qtd">
                                Qtd. Produtos: {{ (int) ($tot['qtd_produtos'] ?? 0) }}
                            </td>
                            <td class="val-frete num">
                                Vlr. Frete: {{ $brl($tot['vlr_frete'] ?? 0) }}
                            </td>
                            <td class="val-desc qtd">
                                Vlr. Desconto: {{ $brl($tot['vlr_desconto'] ?? 0) }}
                            </td>
                            <td class="val-total num">
                                Total: {{ $brl($tot['total'] ?? 0) }}
                            </td>
                        </tr>
                    @else
                        <tr class="totais">
                            <td colspan="4" class="label-qtd">
                                Qtd. Produtos: {{ (int) ($tot['qtd_produtos'] ?? 0) }}
                            </td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    @endforeach

    </div>
    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} | ClinicaWeb - Sistema de Gestão de Receitas
    </div>
</body>
</html>
