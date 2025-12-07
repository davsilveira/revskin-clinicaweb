<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receita {{ $receita->numero }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #059669;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 24px;
            color: #059669;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h2 {
            font-size: 14px;
            color: #059669;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 120px;
            padding: 3px 0;
        }
        .info-value {
            display: table-cell;
            padding: 3px 0;
        }
        table.produtos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.produtos th {
            background: #059669;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        table.produtos td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        table.produtos tr:nth-child(even) {
            background: #f9f9f9;
        }
        .totais {
            margin-top: 20px;
            text-align: right;
        }
        .totais table {
            margin-left: auto;
            border-collapse: collapse;
        }
        .totais td {
            padding: 5px 10px;
        }
        .totais .total-label {
            font-weight: bold;
        }
        .totais .valor-total {
            font-size: 16px;
            font-weight: bold;
            color: #059669;
        }
        .anotacoes {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .anotacoes h3 {
            font-size: 12px;
            margin-bottom: 5px;
        }
        .assinatura {
            margin-top: 50px;
            text-align: center;
        }
        .assinatura img {
            max-height: 80px;
            margin-bottom: 10px;
        }
        .assinatura .linha {
            border-top: 1px solid #333;
            width: 250px;
            margin: 0 auto 5px;
        }
        .assinatura .nome {
            font-weight: bold;
        }
        .assinatura .crm {
            font-size: 11px;
            color: #666;
        }
        .rodape {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .data-receita {
            position: absolute;
            top: 20px;
            right: 20px;
            text-align: right;
        }
        .numero-receita {
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="data-receita">
            <div class="numero-receita">Receita Nº {{ $receita->numero }}</div>
            <div>{{ $receita->data_receita->format('d/m/Y') }}</div>
        </div>

        <div class="header">
            <h1>RECEITA MÉDICA</h1>
            @if($receita->medico->clinica)
                <p>{{ $receita->medico->clinica->nome }}</p>
            @endif
        </div>

        <div class="info-section">
            <h2>Dados do Paciente</h2>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nome:</div>
                    <div class="info-value">{{ $receita->paciente->nome }}</div>
                </div>
                @if($receita->paciente->data_nascimento)
                <div class="info-row">
                    <div class="info-label">Data Nasc.:</div>
                    <div class="info-value">{{ $receita->paciente->data_nascimento->format('d/m/Y') }}</div>
                </div>
                @endif
                @if($receita->paciente->telefone1)
                <div class="info-row">
                    <div class="info-label">Telefone:</div>
                    <div class="info-value">{{ $receita->paciente->telefone1 }}</div>
                </div>
                @endif
            </div>
        </div>

        <div class="info-section">
            <h2>Produtos Prescritos</h2>
            <table class="produtos">
                <thead>
                    <tr>
                        <th>Local de Uso</th>
                        <th>Produto</th>
                        <th>Anotações</th>
                        <th style="text-align: center;">Qtd</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receita->itens as $item)
                    <tr>
                        <td>{{ $item->local_uso ?: '-' }}</td>
                        <td>{{ $item->produto->nome }}</td>
                        <td>{{ $item->anotacoes ?: '-' }}</td>
                        <td style="text-align: center;">{{ $item->quantidade }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($receita->anotacoes)
        <div class="anotacoes">
            <h3>Observações:</h3>
            <p>{{ $receita->anotacoes }}</p>
        </div>
        @endif

        <div class="assinatura">
            @if($receita->medico->assinatura_path)
                <img src="{{ storage_path('app/public/' . $receita->medico->assinatura_path) }}" alt="Assinatura">
            @else
                <div class="linha"></div>
            @endif
            <div class="nome">{{ $receita->medico->nome }}</div>
            @if($receita->medico->crm)
                <div class="crm">CRM: {{ $receita->medico->crm }}</div>
            @endif
            @if($receita->medico->especialidade)
                <div class="crm">{{ $receita->medico->especialidade }}</div>
            @endif
        </div>

        @if($receita->medico->rodape_receita)
        <div class="rodape">
            {!! nl2br(e($receita->medico->rodape_receita)) !!}
        </div>
        @endif
    </div>
</body>
</html>

