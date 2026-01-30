<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receita {{ $receita->numero }}</title>
    <style>
        @page {
            margin: 20mm 25mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 12px;
            line-height: 1.6;
            color: #1a1a1a;
            background: #fff;
        }
        .container {
            max-width: 100%;
            padding: 0 15mm;
        }
        
        /* Cabeçalho */
        .cabecalho {
            margin-bottom: 25px;
            margin-top: 0;
            padding-bottom: 18px;
            padding-top: 0;
            border-bottom: 1px solid #ddd;
            display: table;
            width: 100%;
        }
        .cabecalho-logo {
            display: table-cell;
            width: 80px;
            vertical-align: top;
            padding-right: 20px;
            padding-top: 0;
        }
        .logo-placeholder {
            width: 70px;
            height: 70px;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #999;
            text-align: center;
            padding: 5px;
            margin-top: 2px;
        }
        .cabecalho-profissional {
            display: table-cell;
            vertical-align: top;
            padding-top: 0;
        }
        .nome-profissional {
            font-size: 15px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 4px;
            margin-top: 0;
            padding-top: 0;
            line-height: 1.1;
        }
        .profissao-especialidade {
            font-size: 12px;
            color: #333;
            margin-bottom: 5px;
        }
        .registro-profissional {
            font-size: 11px;
            color: #555;
            margin-bottom: 3px;
        }
        .contato-profissional {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Dados do Paciente */
        .dados-paciente {
            margin-bottom: 22px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        .dados-paciente-title {
            font-size: 10px;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .paciente-info {
            font-size: 12px;
            line-height: 1.7;
            color: #1a1a1a;
        }
        .paciente-info > div {
            margin-bottom: 4px;
        }
        .paciente-info strong {
            font-weight: bold;
            color: #333;
        }
        
        /* Corpo da Receita - Fórmulas */
        .corpo-receita {
            margin-bottom: 30px;
        }
        .formula {
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e5e5e5;
        }
        .formula:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .formula-nome {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .formula-composicao {
            font-size: 11px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
            white-space: pre-line;
        }
        .formula-detalhes {
            margin-top: 10px;
        }
        .formula-detalhe-item {
            font-size: 11px;
            color: #555;
            margin-bottom: 5px;
            line-height: 1.5;
        }
        .formula-detalhe-label {
            font-weight: bold;
            color: #444;
        }
        
        /* Rodapé */
        .rodape {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #ddd;
            text-align: center;
        }
        .assinatura-section {
            margin-bottom: 20px;
        }
        .assinatura-container {
            display: inline-block;
            text-align: center;
        }
        .assinatura-img {
            max-height: 55px;
            max-width: 160px;
            margin-bottom: 12px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            width: 180px;
            margin: 0 auto 12px;
        }
        .assinatura-nome {
            font-weight: bold;
            font-size: 12px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .assinatura-registro {
            font-size: 11px;
            color: #555;
            margin-bottom: 2px;
        }
        .validade {
            font-size: 11px;
            color: #555;
            margin-top: 15px;
        }
        .validade-label {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="cabecalho">
            <div class="cabecalho-logo">
                <div class="logo-placeholder">
                    [ LOGO ]
                </div>
            </div>
            <div class="cabecalho-profissional">
                <div class="nome-profissional">
                    {{ $receita->medico->nome }}
                </div>
                <div class="profissao-especialidade">
                    Médico
                    @if($receita->medico->especialidade)
                        {{ $receita->medico->especialidade }}
                    @endif
                </div>
                @if($receita->medico->crm)
                    <div class="registro-profissional">
                        CRM
                        @if($receita->medico->uf)
                            -{{ $receita->medico->uf }}
                        @endif
                        {{ $receita->medico->crm }}
                    </div>
                @endif
                @if($receita->medico->cidade || $receita->medico->uf || $receita->medico->telefone1)
                    <div class="contato-profissional">
                        @if($receita->medico->cidade)
                            {{ $receita->medico->cidade }}
                        @endif
                        @if($receita->medico->cidade && $receita->medico->uf)
                            /
                        @endif
                        @if($receita->medico->uf)
                            {{ $receita->medico->uf }}
                        @endif
                        @if($receita->medico->telefone1)
                            @if($receita->medico->cidade || $receita->medico->uf)
                                | 
                            @endif
                            {{ $receita->medico->telefone1 }}
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Dados do Paciente -->
        <div class="dados-paciente">
            <div class="dados-paciente-title">Dados do Paciente</div>
            <div class="paciente-info">
                <div>
                    <strong>Paciente:</strong> {{ $receita->paciente->nome }}
                </div>
                @if($receita->paciente->data_nascimento)
                    <div>
                        <strong>Data de Nascimento:</strong> {{ $receita->paciente->data_nascimento->format('d/m/Y') }}
                        @if($receita->paciente->idade)
                            ({{ $receita->paciente->idade }} anos)
                        @endif
                    </div>
                @endif
                @if($receita->paciente->sexo)
                    <div>
                        <strong>Sexo:</strong> {{ ucfirst($receita->paciente->sexo) }}
                    </div>
                @endif
                <div>
                    <strong>Data:</strong> {{ $receita->data_receita->format('d/m/Y') }}
                </div>
            </div>
        </div>

        <!-- Corpo da Receita -->
        <div class="corpo-receita">
            @foreach($receita->itens as $index => $item)
                <div class="formula">
                    <div class="formula-nome">
                        {{ $item->produto->nome }}
                    </div>
                    
                    @if($item->produto->descricao)
                        <div class="formula-composicao">
                            {{ $item->produto->descricao }}
                        </div>
                    @endif
                    
                    <div class="formula-detalhes">
                        @if($item->quantidade)
                            <div class="formula-detalhe-item">
                                <span class="formula-detalhe-label">Quantidade:</span> {{ $item->quantidade }}
                                @if($item->produto->unidade)
                                    {{ $item->produto->unidade }}
                                @endif
                            </div>
                        @endif
                        
                        @if($item->produto->modo_uso)
                            <div class="formula-detalhe-item">
                                <span class="formula-detalhe-label">Posologia / Modo de usar:</span> {{ $item->produto->modo_uso }}
                            </div>
                        @elseif($item->anotacoes)
                            <div class="formula-detalhe-item">
                                <span class="formula-detalhe-label">Posologia / Modo de usar:</span> {{ $item->anotacoes }}
                            </div>
                        @endif
                        
                        @if($item->local_uso)
                            <div class="formula-detalhe-item">
                                <span class="formula-detalhe-label">Via de uso:</span> {{ $item->local_uso }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Rodapé -->
        <div class="rodape">
            <div class="assinatura-section">
                <div class="assinatura-container">
                    @php
                        $assinaturaPath = null;
                        if ($receita->medico->assinatura_path) {
                            $fullPath = storage_path('app/public/' . $receita->medico->assinatura_path);
                            if (file_exists($fullPath)) {
                                $assinaturaPath = $fullPath;
                            }
                        }
                    @endphp
                    @if($assinaturaPath)
                        <img class="assinatura-img" src="{{ $assinaturaPath }}" alt="Assinatura">
                    @else
                        <div class="assinatura-linha"></div>
                    @endif
                    
                    <div class="assinatura-nome">
                        {{ $receita->medico->nome }}
                    </div>
                    
                    @if($receita->medico->crm)
                        <div class="assinatura-registro">
                            CRM
                            @if($receita->medico->uf)
                                -{{ $receita->medico->uf }}
                            @endif
                            {{ $receita->medico->crm }}
                        </div>
                    @endif
                    
                    @if($receita->medico->especialidade)
                        <div class="assinatura-registro">
                            {{ $receita->medico->especialidade }}
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="validade">
                <span class="validade-label">Validade:</span> 30 dias
            </div>
        </div>
    </div>
</body>
</html>
