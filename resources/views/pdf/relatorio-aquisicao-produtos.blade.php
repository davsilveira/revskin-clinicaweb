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
        .paciente-header .telefone {
            display: inline-block;
            margin-left: 10px;
            font-weight: normal;
        }
        table.produtos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.produtos th {
            background: #059669;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.produtos th:nth-child(4),
        table.produtos th:nth-child(5),
        table.produtos th:nth-child(6) {
            text-align: right;
        }
        table.produtos td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        table.produtos tr:nth-child(even) {
            background: #f9fafb;
        }
        table.produtos td:nth-child(4),
        table.produtos td:nth-child(5),
        table.produtos td:nth-child(6) {
            text-align: right;
        }
        .paciente-footer {
            background: #f0fdf4;
            padding: 8px 6px;
            font-size: 9px;
            font-weight: bold;
            color: #059669;
            border-top: 2px solid #059669;
            margin-bottom: 15px;
        }
        .paciente-footer td {
            padding: 8px 6px;
        }
        .totais-gerais {
            margin-top: 20px;
            padding: 15px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 5px;
            font-weight: bold;
            font-size: 10px;
            color: #059669;
        }
        .totais-gerais td {
            padding: 5px 0;
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
        .page-number {
            position: fixed;
            bottom: 10mm;
            right: 10mm;
            font-size: 8px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
    <div class="header">
        <h1>RELATÓRIO DE AQUISIÇÃO DE PRODUTOS</h1>
        <div class="periodo">
            Período: {{ \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dataFim)->format('d/m/Y') }}
        </div>
    </div>

    @foreach($dados['pacientes'] as $pacienteData)
        <div class="paciente-section">
            <!-- Cabeçalho do Paciente -->
            <div class="paciente-header">
                <span class="nome">{{ strtoupper($pacienteData['paciente']['nome']) }}</span>
                @if($pacienteData['paciente']['telefone'])
                    @php
                        $telefone = preg_replace('/\D/', '', $pacienteData['paciente']['telefone']);
                        $telefoneFormatado = '';
                        if (strlen($telefone) === 11) {
                            $telefoneFormatado = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
                        } elseif (strlen($telefone) === 10) {
                            $telefoneFormatado = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
                        } else {
                            $telefoneFormatado = $pacienteData['paciente']['telefone'];
                        }
                    @endphp
                    <span class="telefone">({{ $telefoneFormatado }})</span>
                @endif
            </div>

            <!-- Tabela de Produtos -->
            <table class="produtos">
                <thead>
                    <tr>
                        <th style="width: 40%;">Produto</th>
                        <th style="width: 12%;">Data Receita</th>
                        <th style="width: 12%;">Data Aquisição</th>
                        <th style="width: 12%;">Valor Unit.</th>
                        <th style="width: 8%;">Qtd</th>
                        <th style="width: 16%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacienteData['produtos'] as $produto)
                        <tr>
                            <td>{{ $produto['produto_nome'] }}</td>
                            <td>{{ $produto['data_receita'] }}</td>
                            <td>{{ $produto['data_aquisicao'] }}</td>
                            <td>R$ {{ number_format($produto['valor_unitario'], 2, ',', '.') }}</td>
                            <td>{{ $produto['quantidade'] }}</td>
                            <td>R$ {{ number_format($produto['valor_total'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Rodapé do Paciente -->
            <table class="paciente-footer" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 40%;">Qtd. Produtos: {{ $pacienteData['totais']['qtd_produtos'] }}</td>
                    <td style="width: 20%; text-align: right;">Vlr.Frete: R$ {{ number_format($pacienteData['totais']['vlr_frete'], 2, ',', '.') }}</td>
                    <td style="width: 20%; text-align: right;">Vlr.Desconto: R$ {{ number_format($pacienteData['totais']['vlr_desconto'], 2, ',', '.') }}</td>
                    <td style="width: 20%; text-align: right; font-weight: bold;">Total: R$ {{ number_format($pacienteData['totais']['total'], 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @endforeach

    <!-- Totais Gerais -->
    <div class="totais-gerais">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">Qtd. Total Produtos: {{ $dados['totais_gerais']['qtd_total_produtos'] }}</td>
                <td style="width: 50%; text-align: right;">Valor Total de Produtos: R$ {{ number_format($dados['totais_gerais']['valor_total_produtos'], 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    </div>
    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} | RevSkin - Sistema de Gestão de Receitas
    </div>
</body>
</html>
