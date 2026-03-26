<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Produtos</title>
    <style>
        @page {
            margin: 20mm 20mm;
            size: landscape;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 5mm;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #059669;
        }
        .header h1 {
            font-size: 18px;
            color: #059669;
            margin-bottom: 3px;
        }
        .header .subtitle {
            font-size: 10px;
            color: #666;
        }
        table.dados {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.dados th {
            background: #059669;
            color: white;
            padding: 8px 8px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.dados td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
            vertical-align: top;
        }
        table.dados tr:nth-child(even) {
            background: #f9fafb;
        }
        .col-nome { width: 15%; }
        .col-codigo { width: 12%; }
        .col-formula { width: 25%; }
        .col-modo { width: 23%; }
        .col-anotacoes { width: 25%; }
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-size: 7px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
    <div class="header">
        <h1>CATÁLOGO DE PRODUTOS</h1>
        <div class="subtitle">{{ $total }} produtos disponíveis ativos</div>
    </div>

    <table class="dados">
        <thead>
            <tr>
                <th class="col-nome">Nome (Tipo)</th>
                <th class="col-codigo">Código</th>
                <th class="col-formula">Fórmula (Etiqueta)</th>
                <th class="col-modo">Modo de Uso</th>
                <th class="col-anotacoes">Anotações dos Especialistas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($produtos as $produto)
                <tr>
                    <td>{{ $produto->nome }}</td>
                    <td>{{ $produto->codigo }}</td>
                    <td>{!! nl2br(e(str_replace(['\\n', '/n'], "\n", $produto->descricao ?? ''))) !!}</td>
                    <td>{!! nl2br(e(str_replace(['\\n', '/n'], "\n", $produto->modo_uso ?? ''))) !!}</td>
                    <td>{{ $produto->anotacoes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #999;">
                        Nenhum produto encontrado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} | ClincaWeb - Catálogo de Produtos
    </div>
</body>
</html>
