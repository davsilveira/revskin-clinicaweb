<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receita {{ $receita->numero }}</title>
    <style>
        /* Margens laterais: padding no body (Dompdf); ~14mm lateral (metade dos 28mm anteriores). */
        @page {
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #111;
            background: #fff;
            /* Metade do padding anterior (~28mm → 14mm laterais) para aproximar do anexo legado */
            padding: 10mm 14mm 12mm 14mm;
        }
        .container {
            max-width: 100%;
            padding: 0;
        }

        /* Topo — médico centrado (padrão legado) */
        .topo-legado-logo {
            text-align: center;
            margin-bottom: 10px;
        }
        .topo-legado-logo img {
            max-height: 48px;
            max-width: 120px;
        }
        .topo-legado-medico {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 1px solid #111;
            margin-bottom: 12px;
        }
        .topo-legado-nome {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .topo-legado-esp {
            font-size: 11px;
            margin-bottom: 4px;
        }
        .topo-legado-crm {
            font-size: 11px;
            color: #333;
        }

        /* Paciente — legado: Para: + caps */
        .bloco-paciente {
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #bbb;
        }
        .receita-numero {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }
        .paciente-para {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.02em;
        }
        .paciente-detalhes {
            margin-top: 10px;
            font-size: 9.5px;
            color: #444;
            line-height: 1.4;
        }
        .paciente-detalhes div {
            margin: 0 0 2px 0;
        }

        /* Corpo */
        .secao-local-uso {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }
        .formula {
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e0e0e0;
            page-break-inside: avoid;
        }
        .formula.formula-ultima {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        table.formula-head {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        td.formula-titulo {
            font-size: 12px;
            font-weight: bold;
            vertical-align: top;
            padding: 0 6mm 0 0;
            width: 64%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        td.formula-codigo {
            vertical-align: top;
            text-align: right;
            font-size: 10px;
            color: #888;
            padding: 1px 0 0 3mm;
            width: 36%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .formula-comp {
            font-size: 10.5px;
            color: #222;
            line-height: 1.65;
            margin-bottom: 9px;
        }
        .det-item {
            font-size: 10.5px;
            margin-bottom: 5px;
            line-height: 1.45;
            color: #333;
        }
        .det-lbl {
            font-weight: bold;
            color: #222;
        }

        /* Rodapé — data esq., assinatura dir., morada centro */
        .rodape-legado {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #ccc;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        table.rodape-super {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 14px;
        }
        td.rodape-data {
            width: 40%;
            vertical-align: bottom;
            font-size: 11px;
            padding-right: 5mm;
        }
        td.rodape-assin {
            width: 60%;
            vertical-align: bottom;
            text-align: right;
            padding-left: 4mm;
        }
        .rodape-assin img {
            max-height: 64px;
            max-width: 170px;
            display: inline-block;
        }
        .rodape-sem-assin-div {
            min-height: 48px;
        }
        .rodape-sem-assin-linha {
            display: inline-block;
            border-top: 1px solid #444;
            width: 170px;
            margin-top: 22px;
        }
        .rodape-assin-legenda {
            text-align: right;
            margin-top: 6px;
            margin-left: auto;
            max-width: 58%;
            padding-right: 1mm;
            font-size: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .rodape-assin-nome {
            font-weight: bold;
        }
        .rodape-assin-meta {
            color: #444;
            margin-top: 1px;
        }
        .morada-centro {
            text-align: center;
            font-size: 10px;
            color: #333;
            line-height: 1.55;
            margin-top: 10mm;
            padding-bottom: 2mm;
        }
        .morada-centro .linha-tel {
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $med = $receita->medico;
        $pac = $receita->paciente;

        $normNomeReceita = static function (?string $s): string {
            $s = trim((string) $s);
            if ($s === '') {
                return '';
            }
            $s = preg_replace('/^\s*(dra\.|dr\.|doutora\.?|doutor\.?)\s+/iu', '', $s);
            $s = preg_replace('/\s+/', ' ', $s);

            return mb_strtolower(trim($s), 'UTF-8');
        };

        $clinicaView = isset($clinica) ? $clinica : null;
        $logoPathTop = isset($clinicaLogoFullPath) ? $clinicaLogoFullPath : null;

        if ($med && ! empty($clinicaView)) {
            $nClin = $normNomeReceita($clinicaView->nome ?? '');
            $nMed = $normNomeReceita($med->nome ?? '');
            if ($nClin !== '' && $nClin === $nMed) {
                $clinicaView = null;
                $logoPathTop = null;
            }
        }

        if ($med) {
            $medFones = array_values(array_filter([$med->telefone1 ?? null, $med->telefone2 ?? null], fn ($t) => filled($t)));
            $medLogra = trim(implode(', ', array_filter([
                trim(implode(', ', array_filter([$med->endereco ?? null, $med->numero ?? null], fn ($x) => filled($x)))),
                filled($med->complemento ?? null) ? $med->complemento : null,
                filled($med->bairro ?? null) ? $med->bairro : null,
            ], fn ($x) => filled($x))));
            $medCidadeLinha = trim(implode(', ', array_filter([$med->cidade ?? null, $med->uf ?? null], fn ($x) => filled($x))));
        } else {
            $medFones = [];
            $medLogra = '';
            $medCidadeLinha = '';
        }

        $cTel = [];
        $cEndRaw = '';
        $cBairro = '';
        $cCity = '';
        $cCep = null;
        if (! empty($clinicaView)) {
            $cTel = array_values(array_filter([
                $clinicaView->telefone1 ?? null,
                $clinicaView->telefone2 ?? null,
                $clinicaView->telefone3 ?? null,
            ], fn ($t) => filled($t)));
            $cEndRaw = trim(implode(', ', array_filter([
                $clinicaView->endereco ?? null,
                $clinicaView->numero ?? null,
                $clinicaView->complemento ?? null,
            ], fn ($x) => filled($x))));
            $cBairro = $clinicaView->bairro ?? '';
            $cCity = trim(implode(', ', array_filter([$clinicaView->cidade ?? null, $clinicaView->uf ?? null], fn ($x) => filled($x))));
            $cCep = $clinicaView->cep ?? null;
        }

        $pacLogra = trim(implode(', ', array_filter([
            trim(implode(', ', array_filter([$pac->endereco ?? null, $pac->numero ?? null, $pac->complemento ?? null], fn ($x) => filled($x)))),
            filled($pac->bairro ?? null) ? $pac->bairro : null,
        ], fn ($x) => filled($x))));
        $pacCidadeLinha = trim(implode(' — ', array_filter([
            trim(implode(', ', array_filter([$pac->cidade ?? null, $pac->uf ?? null], fn ($x) => filled($x)))),
            filled($pac->cep ?? null) ? 'CEP '.$pac->cep : null,
        ], fn ($x) => filled($x))));

        $mostrarTopoMedico = $med !== null;
        /** Rodapé com data/assinatura/morada */
        $mostrarRodape = $med !== null;

        /** Morada centro do rodapé: clínica; senão médico */
        $linhasRodapeMorada = [];
        if (! empty($clinicaView)) {
            $linha1 = trim(implode(' - ', array_filter([$cEndRaw, filled($cBairro) ? $cBairro : null], fn ($x) => filled($x))));
            $linha2Parts = [$cCity];
            if (filled($cCep)) {
                $linha2Parts[] = 'CEP '.$cCep;
            }
            $linha2 = trim(implode(' — ', array_filter($linha2Parts, fn ($x) => filled($x))));
            if ($linha1 !== '') {
                $linhasRodapeMorada[] = $linha1;
            }
            if ($linha2 !== '') {
                $linhasRodapeMorada[] = $linha2;
            }
        } elseif ($med) {
            if ($medLogra !== '') {
                $linhasRodapeMorada[] = $medLogra;
            }
            $mLine = trim(implode(' — ', array_filter([
                $medCidadeLinha !== '' ? $medCidadeLinha : null,
                filled($med->cep ?? null) ? 'CEP '.$med->cep : null,
            ], fn ($x) => filled($x))));
            if ($mLine !== '') {
                $linhasRodapeMorada[] = $mLine;
            }
        }
        $foneRodapeLabel = '';
        if (! empty($clinicaView) && count($cTel) > 0) {
            $foneRodapeLabel = 'Fone: '.implode(' · ', $cTel);
        } elseif ($med && count($medFones) > 0 && empty($clinicaView)) {
            $foneRodapeLabel = 'Telefone: '.implode(' · ', $medFones);
        }

        /** CRM texto legível */
        $crmLinhaTopo = '';
        if ($med && filled(trim((string) ($med->crm ?? '')))) {
            $raw = trim((string) $med->crm);
            if (stripos($raw, 'crm') === false) {
                $ufTxt = trim((string) ($med->uf_crm ?? ''));
                $crmLinhaTopo = ($ufTxt !== '')
                    ? 'CRM-'.$ufTxt.' '.$raw
                    : 'CRM: '.$raw;
            } else {
                $crmLinhaTopo = $raw;
            }
        }
        $assinaturaUri = isset($assinaturaDataUri) ? $assinaturaDataUri : null;

    @endphp

    @if($mostrarTopoMedico)
        @if(! empty($logoPathTop) && is_readable((string) $logoPathTop))
            <div class="topo-legado-logo">
                <img src="{{ $logoPathTop }}" alt="">
            </div>
        @endif
        <div class="topo-legado-medico">
            <div class="topo-legado-nome">{{ $med->nome }}</div>
            @if(trim((string) ($med->especialidade ?? '')) !== '')
                <div class="topo-legado-esp">{{ $med->especialidade }}</div>
            @endif
            @if($crmLinhaTopo !== '')
                <div class="topo-legado-crm">{{ $crmLinhaTopo }}</div>
            @endif
        </div>
    @endif

    <div class="bloco-paciente">
        <div class="receita-numero">Receita nº {{ $receita->numero ?? ('REC-'.$receita->id) }}</div>
        <div class="paciente-para">Para:
            {{ mb_strtoupper(trim((string) ($pac->nome ?? '')), 'UTF-8') }}
        </div>
        @if($pacLogra !== '' || $pacCidadeLinha !== '' || filled($pac->sexo ?? null))
            <div class="paciente-detalhes">
                @if($pacLogra !== '')
                    <div><strong>Endereço:</strong> {{ $pacLogra }}</div>
                @endif
                @if($pacCidadeLinha !== '')
                    <div>{{ $pacCidadeLinha }}</div>
                @endif
                @if(filled($pac->sexo ?? null))
                    <div><strong>Sexo:</strong> {{ ucfirst((string) $pac->sexo) }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="secao-local-uso">USO TÓPICO</div>

    @foreach($receita->itens as $item)
        @php
            $codPdf = trim((string) ($item->produto->codigo ?? ''));
            if ($codPdf === '') {
                $codPdf = trim((string) ($item->produto->codigo_cq ?? ''));
            }
        @endphp
        <div class="formula{{ $loop->last ? ' formula-ultima' : '' }}">
            <table class="formula-head">
                <tr>
                    <td class="formula-titulo">{{ $item->produto->nome }}</td>
                    <td class="formula-codigo">
                        @if($codPdf !== '')
                            {{ $codPdf }}
                        @endif
                    </td>
                </tr>
            </table>

            @if($item->produto->descricao)
                <div class="formula-comp">
                    @php
                        $desc = preg_replace("/\r\n|\r|\n/", "\n", $item->produto->descricao);
                        $desc = str_replace(['\n', '/n'], "\n", $desc);
                        $desc = preg_replace("/\n{2,}/", "\n", $desc);
                    @endphp
                    {!! nl2br(e($desc)) !!}
                </div>
            @endif

            <div class="det-item">
                @if($item->produto->modo_uso)
                    @php
                        $modo = preg_replace("/\r\n|\r|\n/", "\n", $item->produto->modo_uso);
                        $modo = str_replace(['\n', '/n'], "\n", $modo);
                        $modo = preg_replace("/\n{2,}/", "\n", $modo);
                    @endphp
                    <span class="det-lbl">Modo de uso:</span>
                    <div style="margin-top:2px;">{!! nl2br(e($modo)) !!}</div>
                @elseif($item->anotacoes)
                    <span class="det-lbl">Modo de uso:</span>
                    <div style="margin-top:2px;">{{ $item->anotacoes }}</div>
                @endif
            </div>

            @if($item->quantidade)
                <div class="det-item">
                    <span class="det-lbl">Quantidade:</span>
                    {{ $item->quantidade }}
                    @if($item->produto->unidade)
                        {{ $item->produto->unidade }}
                    @endif
                </div>
            @endif
        </div>
    @endforeach

    @if($mostrarRodape && $med)
        <div class="rodape-legado">
            <table class="rodape-super">
                <tr>
                    <td class="rodape-data">{{ $receita->data_receita->format('d/m/Y') }}</td>
                    <td class="rodape-assin">
                        @if($assinaturaUri)
                            <img src="{{ $assinaturaUri }}" alt="">
                        @else
                            <div class="rodape-sem-assin-div">
                                <span class="rodape-sem-assin-linha"></span>
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
            <div class="rodape-assin-legenda">
                <div class="rodape-assin-nome">{{ $med->nome }}</div>
                @if($crmLinhaTopo !== '')
                    <div class="rodape-assin-meta">{{ $crmLinhaTopo }}</div>
                @endif
                @if(trim((string) ($med->especialidade ?? '')) !== '')
                    <div class="rodape-assin-meta">{{ $med->especialidade }}</div>
                @endif
            </div>

            @if(count($linhasRodapeMorada) > 0 || $foneRodapeLabel !== '')
                <div class="morada-centro">
                    @foreach($linhasRodapeMorada as $lin)
                        <div>{{ $lin }}</div>
                    @endforeach
                    @if($foneRodapeLabel !== '')
                        <div class="linha-tel">{{ $foneRodapeLabel }}</div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
</body>
</html>
