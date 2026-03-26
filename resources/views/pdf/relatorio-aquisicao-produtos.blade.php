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
        .paciente-header .cpf,
        .paciente-header .medico {
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
        table.produtos td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        table.produtos tr:nth-child(even) {
            background: #f9fafb;
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
        @php
            $ultimaModPorProduto = [];
            foreach ($pacienteData['produtos'] as $p) {
                $nome = $p['produto_nome'];
                if (!isset($ultimaModPorProduto[$nome])) {
                    $ultimaModPorProduto[$nome] = $p['data_receita'];
                } else {
                    try {
                        $atual = \Carbon\Carbon::createFromFormat('d/m/Y', $p['data_receita']);
                        $max = \Carbon\Carbon::createFromFormat('d/m/Y', $ultimaModPorProduto[$nome]);
                        if ($atual->gt($max)) {
                            $ultimaModPorProduto[$nome] = $p['data_receita'];
                        }
                    } catch (\Exception $e) {
                        if (strcmp($p['data_receita'], $ultimaModPorProduto[$nome]) > 0) {
                            $ultimaModPorProduto[$nome] = $p['data_receita'];
                        }
                    }
                }
            }
            $cpfFormatado = '';
            if (!empty($pacienteData['paciente']['cpf'])) {
                $cpf = preg_replace('/\D/', '', $pacienteData['paciente']['cpf']);
                if (strlen($cpf) === 11) {
                    $cpfFormatado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
                } else {
                    $cpfFormatado = $pacienteData['paciente']['cpf'];
                }
            }
        @endphp
        <div class="paciente-section">
            <!-- Cabeçalho do Paciente: Nome, CPF, Dra. (só admin) -->
            <div class="paciente-header">
                <span class="nome">{{ strtoupper($pacienteData['paciente']['nome']) }}</span>
                @if($cpfFormatado)
                    <span class="cpf">CPF: {{ $cpfFormatado }}</span>
                @endif
                @if(!empty($isAdmin) && !empty($pacienteData['paciente']['medico_nome']))
                    <span class="medico">Dra. {{ $pacienteData['paciente']['medico_nome'] }}</span>
                @endif
            </div>

            <!-- Tabela: Produto | Última Modificação | Data Aquisição | Qtd (colunas separadas) -->
            <table class="produtos">
                <thead>
                    <tr>
                        <th style="width: 35%;">Produto</th>
                        <th style="width: 20%;">Última Modificação</th>
                        <th style="width: 22%;">Aquisições no Período</th>
                        <th style="width: 10%; text-align: center;">Qtd</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacienteData['produtos'] as $p)
                        <tr>
                            <td>{{ $p['produto_nome'] }}</td>
                            <td>{{ $ultimaModPorProduto[$p['produto_nome']] ?? $p['data_receita'] }}</td>
                            <td>{{ $p['data_aquisicao'] }}</td>
                            <td style="text-align: center;">{{ $p['quantidade'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    </div>
    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} | ClincaWeb - Sistema de Gestão de Receitas
    </div>
</body>
</html>
