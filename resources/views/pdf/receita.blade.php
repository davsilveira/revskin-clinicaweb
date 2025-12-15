<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receita {{ $receita->numero }}</title>
    <style>
        @page {
            margin: 25mm 20mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #059669;
        }
        .header h1 {
            font-size: 22px;
            color: #059669;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        .header .clinica-nome {
            font-size: 14px;
            color: #333;
            font-weight: bold;
        }
        .header .clinica-info {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
        .meta-info {
            position: absolute;
            top: 25mm;
            right: 20mm;
            text-align: right;
        }
        .meta-info .numero-receita {
            font-size: 9px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-info .data-receita {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #059669;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            width: 100px;
            padding: 4px 0;
            color: #555;
        }
        .info-value {
            display: table-cell;
            padding: 4px 0;
        }
        table.produtos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.produtos th {
            background: #059669;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.produtos td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.produtos tr:nth-child(even) {
            background: #f9fafb;
        }
        table.produtos .produto-nome {
            font-weight: bold;
            color: #333;
        }
        table.produtos .produto-codigo {
            font-size: 9px;
            color: #059669;
            margin-right: 5px;
        }
        table.produtos .produto-anotacoes {
            font-size: 9px;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        .anotacoes-paciente {
            margin-top: 20px;
            padding: 15px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
        }
        .anotacoes-paciente h3 {
            font-size: 11px;
            font-weight: bold;
            color: #059669;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .anotacoes-paciente p {
            font-size: 11px;
            color: #333;
            white-space: pre-line;
        }
        .assinatura {
            margin-top: 50px;
            text-align: center;
        }
        .assinatura-img {
            max-height: 70px;
            max-width: 200px;
            margin-bottom: 10px;
        }
        .assinatura .linha {
            border-top: 1px solid #333;
            width: 220px;
            margin: 0 auto 8px;
        }
        .assinatura .nome {
            font-weight: bold;
            font-size: 12px;
            color: #333;
        }
        .assinatura .crm {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        .assinatura .especialidade {
            font-size: 9px;
            color: #666;
            font-style: italic;
        }
        .rodape {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
            line-height: 1.6;
        }
        .watermark {
            position: fixed;
            bottom: 10mm;
            right: 10mm;
            font-size: 8px;
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="meta-info">
            <div class="numero-receita">Receita N° {{ $receita->numero }}</div>
            <div class="data-receita">{{ $receita->data_receita->format('d/m/Y') }}</div>
        </div>

        <div class="header">
            <h1>RECEITA MÉDICA</h1>
            @if($receita->medico->clinica)
                <div class="clinica-nome">{{ $receita->medico->clinica->nome }}</div>
                @if($receita->medico->clinica->endereco)
                    <div class="clinica-info">
                        {{ $receita->medico->clinica->endereco }}
                        @if($receita->medico->clinica->numero), {{ $receita->medico->clinica->numero }}@endif
                        @if($receita->medico->clinica->bairro) - {{ $receita->medico->clinica->bairro }}@endif
                        @if($receita->medico->clinica->cidade) | {{ $receita->medico->clinica->cidade }}@endif
                        @if($receita->medico->clinica->uf)/{{ $receita->medico->clinica->uf }}@endif
                    </div>
                @endif
            @endif
        </div>

        <div class="section">
            <div class="section-title">Dados do Paciente</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nome:</div>
                    <div class="info-value">{{ $receita->paciente->nome }}</div>
                </div>
                @if($receita->paciente->data_nascimento)
                <div class="info-row">
                    <div class="info-label">Nascimento:</div>
                    <div class="info-value">{{ $receita->paciente->data_nascimento->format('d/m/Y') }}</div>
                </div>
                @endif
                @if($receita->paciente->telefone1)
                <div class="info-row">
                    <div class="info-label">Telefone:</div>
                    <div class="info-value">{{ $receita->paciente->telefone1 }}</div>
                </div>
                @endif
                @if($receita->paciente->endereco)
                <div class="info-row">
                    <div class="info-label">Endereço:</div>
                    <div class="info-value">
                        {{ $receita->paciente->endereco }}
                        @if($receita->paciente->numero), {{ $receita->paciente->numero }}@endif
                        @if($receita->paciente->complemento) - {{ $receita->paciente->complemento }}@endif
                        @if($receita->paciente->bairro) - {{ $receita->paciente->bairro }}@endif
                        @if($receita->paciente->cidade) | {{ $receita->paciente->cidade }}@endif
                        @if($receita->paciente->uf)/{{ $receita->paciente->uf }}@endif
                        @if($receita->paciente->cep) - CEP: {{ $receita->paciente->cep }}@endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Produtos Prescritos</div>
            <table class="produtos">
                <thead>
                    <tr>
                        <th style="width: 100px;">Local de Uso</th>
                        <th>Produto</th>
                        <th style="width: 60px; text-align: center;">Qtd</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receita->itens as $item)
                    <tr>
                        <td>{{ $item->local_uso ?: '-' }}</td>
                        <td>
                            <div class="produto-nome">
                                @if($item->produto->codigo)
                                    <span class="produto-codigo">[{{ $item->produto->codigo }}]</span>
                                @endif
                                {{ $item->produto->nome }}
                            </div>
                            @if($item->anotacoes)
                                <div class="produto-anotacoes">{{ $item->anotacoes }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantidade }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($receita->anotacoes_paciente)
        <div class="anotacoes-paciente">
            <h3>Observações Importantes</h3>
            <p>{{ $receita->anotacoes_paciente }}</p>
        </div>
        @endif

        <div class="assinatura">
            @if($receita->medico->assinatura_path && file_exists(storage_path('app/public/' . $receita->medico->assinatura_path)))
                <img class="assinatura-img" src="{{ storage_path('app/public/' . $receita->medico->assinatura_path) }}" alt="Assinatura">
            @else
                <div class="linha"></div>
            @endif
            <div class="nome">{{ $receita->medico->nome }}</div>
            @if($receita->medico->crm)
                <div class="crm">CRM: {{ $receita->medico->crm }}@if($receita->medico->clinica && $receita->medico->clinica->uf)/{{ $receita->medico->clinica->uf }}@endif</div>
            @endif
            @if($receita->medico->especialidade)
                <div class="especialidade">{{ $receita->medico->especialidade }}</div>
            @endif
        </div>

        @if($receita->medico->rodape_receita)
        <div class="rodape">
            {!! nl2br(e($receita->medico->rodape_receita)) !!}
        </div>
        @endif

        <div class="watermark">
            Gerado em {{ now()->format('d/m/Y H:i') }} | RevSkin
        </div>
    </div>
</body>
</html>
