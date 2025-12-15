<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Receitas por Médico</title>
    <style>
        @page {
            margin: 20mm 15mm;
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
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #059669;
        }
        .header h1 {
            font-size: 18px;
            color: #059669;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 12px;
            color: #666;
        }
        .header .periodo {
            font-size: 10px;
            color: #999;
            margin-top: 5px;
        }
        .resumo {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 5px;
        }
        .resumo-item {
            display: table-cell;
            padding: 15px;
            text-align: center;
            border-right: 1px solid #bbf7d0;
        }
        .resumo-item:last-child {
            border-right: none;
        }
        .resumo-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .resumo-valor {
            font-size: 16px;
            font-weight: bold;
            color: #059669;
            margin-top: 5px;
        }
        table.dados {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.dados th {
            background: #059669;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.dados th:last-child {
            text-align: right;
        }
        table.dados td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }
        table.dados td:last-child {
            text-align: right;
            font-weight: bold;
        }
        table.dados tr:nth-child(even) {
            background: #f9fafb;
        }
        .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-finalizada {
            background: #d1fae5;
            color: #059669;
        }
        .status-rascunho {
            background: #f3f4f6;
            color: #6b7280;
        }
        .status-cancelada {
            background: #fee2e2;
            color: #dc2626;
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
        .total-row {
            background: #f0fdf4 !important;
            font-weight: bold;
        }
        .total-row td {
            border-top: 2px solid #059669;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE RECEITAS</h1>
        @if($medico)
            <div class="subtitle">Médico: {{ $medico->nome }}</div>
        @else
            <div class="subtitle">Todos os Médicos</div>
        @endif
        @if($dataInicio || $dataFim)
            <div class="periodo">
                Período: 
                {{ $dataInicio ? \Carbon\Carbon::parse($dataInicio)->format('d/m/Y') : 'Início' }}
                a 
                {{ $dataFim ? \Carbon\Carbon::parse($dataFim)->format('d/m/Y') : 'Hoje' }}
            </div>
        @endif
    </div>

    <div class="resumo">
        <div class="resumo-item">
            <div class="resumo-label">Total de Receitas</div>
            <div class="resumo-valor">{{ $totais['quantidade'] }}</div>
        </div>
        <div class="resumo-item">
            <div class="resumo-label">Valor Total</div>
            <div class="resumo-valor">R$ {{ number_format($totais['valor_total'], 2, ',', '.') }}</div>
        </div>
        <div class="resumo-item">
            <div class="resumo-label">Média por Receita</div>
            <div class="resumo-valor">
                R$ {{ $totais['quantidade'] > 0 ? number_format($totais['valor_total'] / $totais['quantidade'], 2, ',', '.') : '0,00' }}
            </div>
        </div>
    </div>

    <table class="dados">
        <thead>
            <tr>
                <th style="width: 60px;">Número</th>
                <th style="width: 70px;">Data</th>
                <th>Paciente</th>
                @if(!$medico)
                    <th>Médico</th>
                @endif
                <th style="width: 70px;">Status</th>
                <th style="width: 80px;">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($receitas as $receita)
                <tr>
                    <td>#{{ $receita->numero ?? str_pad($receita->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $receita->data_receita->format('d/m/Y') }}</td>
                    <td>{{ $receita->paciente->nome ?? '-' }}</td>
                    @if(!$medico)
                        <td>{{ $receita->medico->nome ?? '-' }}</td>
                    @endif
                    <td>
                        <span class="status status-{{ $receita->status }}">
                            {{ ucfirst($receita->status) }}
                        </span>
                    </td>
                    <td>R$ {{ number_format($receita->valor_total, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $medico ? 5 : 6 }}" style="text-align: center; padding: 20px; color: #999;">
                        Nenhuma receita encontrada para os filtros selecionados.
                    </td>
                </tr>
            @endforelse
            
            @if($receitas->count() > 0)
                <tr class="total-row">
                    <td colspan="{{ $medico ? 4 : 5 }}" style="text-align: right;">
                        <strong>TOTAL:</strong>
                    </td>
                    <td>
                        <strong>R$ {{ number_format($totais['valor_total'], 2, ',', '.') }}</strong>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} | RevSkin - Sistema de Gestão de Receitas
    </div>
</body>
</html>

