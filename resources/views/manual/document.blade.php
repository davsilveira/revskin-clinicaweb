@php
    /**
     * Documento do manual para PDF (dompdf) e impressão (navegador).
     * Sem menu lateral: o índice ocupa a primeira página e cada item aponta para
     * a âncora da seção correspondente; o conteúdo ocupa 100% da largura útil.
     *
     * Variáveis:
     *   $modules    array de módulos (ManualContent::forUser)
     *   $forPdf     bool  — true quando renderizado pelo dompdf
     *   $autoprint  bool  — dispara window.print() ao carregar (versão impressão)
     *   $generatedAt string
     *   $appName    string
     */
    $imgSrc = function (string $src) use ($forPdf) {
        if ($forPdf) {
            // dompdf lê o arquivo do disco.
            return public_path(ltrim($src, '/'));
        }
        return $src;
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Manual de uso — {{ $appName }}</title>
    <style>
        /* Margens da página no PDF (mesmo padrão dos outros PDFs do sistema).
           IMPORTANTE (dompdf): as margens vêm do @page; NÃO zerar margin/padding do
           body (nem via `*`) — o dompdf mapeia a margem do body para a página e o
           conteúdo cola nas bordas. Além disso, @page só aceita UM valor de margem
           (o atalho de dois valores, ex. 18mm 16mm, é ignorado). */
        @page { margin: 16mm; }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.55;
            color: #1f2937;
            background: #fff;
        }
        @if(! $forPdf)
        /* Navegador/impressão: o @page do PDF não se aplica; largura confortável e recuo próprio. */
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { max-width: 900px; margin: 0 auto; padding: 32px 24px 64px; }
        @endif
        a { color: #047857; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .doc-title {
            font-size: 26px;
            font-weight: 700;
            color: #064e3b;
            margin-bottom: 4px;
        }
        .doc-sub { color: #6b7280; font-size: 12px; margin-bottom: 24px; }

        /* Índice (primeira página) */
        .toc-heading {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 2px solid #10b981;
            padding-bottom: 6px;
            margin-bottom: 14px;
        }
        .toc-mod { margin-bottom: 12px; }
        .toc-mod > a {
            font-size: 13px;
            font-weight: 700;
            color: #065f46;
        }
        nav > ul { list-style: none; margin: 0; padding: 0; }
        .toc-sections { list-style: none; margin: 4px 0 0 18px; padding: 0; }
        .toc-sections li { list-style: none; margin: 2px 0; }
        .toc-sections a { color: #374151; font-size: 12px; }
        .toc-sec-3 a { color: #6b7280; font-size: 11px; }

        .page-break { page-break-before: always; }

        /* Conteúdo */
        .module { page-break-inside: auto; margin-bottom: 26px; }
        .module + .module { margin-top: 0; }
        .module-title {
            font-size: 19px;
            font-weight: 700;
            color: #064e3b;
            border-bottom: 2px solid #d1fae5;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .section { margin-bottom: 16px; }
        .section-h2 { font-size: 15px; font-weight: 700; color: #111827; margin: 14px 0 6px; }
        .section-h3 { font-size: 13px; font-weight: 700; color: #1f2937; margin: 12px 0 6px; }
        .section p { margin: 0 0 8px; }
        .section ol, .section ul { margin: 6px 0 10px 0; padding-left: 22px; }
        .section li { margin: 3px 0; }

        figure {
            margin: 10px 0 14px;
            page-break-inside: avoid;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        figure img { width: 100%; height: auto; display: block; }
        figcaption {
            font-size: 11px;
            color: #6b7280;
            padding: 6px 10px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        @media print {
            body { max-width: none; margin: 0; padding: 0; }
            a { color: #047857; }
        }
    </style>
</head>
<body>
    <h1 class="doc-title">Manual de uso</h1>
    <p class="doc-sub">{{ $appName }} — gerado em {{ $generatedAt }}</p>

    {{-- Índice na primeira página --}}
    <nav>
        <p class="toc-heading">Índice</p>
        <ul>
            @foreach($modules as $mod)
                <li class="toc-mod">
                    <a href="#mod-{{ $mod['id'] }}">{{ $mod['title'] }}</a>
                    @if(!empty($mod['sections']))
                        <ul class="toc-sections">
                            @foreach($mod['sections'] as $sec)
                                <li class="{{ ($sec['level'] ?? 2) === 3 ? 'toc-sec-3' : '' }}">
                                    <a href="#{{ $mod['id'] }}-{{ $sec['slug'] }}">{{ $sec['title'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Conteúdo (começa em nova página) --}}
    @foreach($modules as $mod)
        <div class="module {{ $loop->first ? 'page-break' : '' }}">
            <h2 class="module-title" id="mod-{{ $mod['id'] }}">{{ $mod['title'] }}</h2>
            @foreach($mod['sections'] ?? [] as $sec)
                <div class="section" id="{{ $mod['id'] }}-{{ $sec['slug'] }}">
                    @if(($sec['level'] ?? 2) === 3)
                        <h4 class="section-h3">{{ $sec['title'] }}</h4>
                    @else
                        <h3 class="section-h2">{{ $sec['title'] }}</h3>
                    @endif

                    @foreach($sec['paragraphs'] ?? [] as $p)
                        <p>{!! \App\Manual\ManualContent::richTextToHtml($p) !!}</p>
                    @endforeach

                    @if(!empty($sec['numbered_bullets']))
                        <ol>
                            @foreach($sec['numbered_bullets'] as $item)
                                <li>{!! \App\Manual\ManualContent::richTextToHtml($item) !!}</li>
                            @endforeach
                        </ol>
                    @endif

                    @foreach($sec['paragraphs_after_numbered'] ?? [] as $p)
                        <p>{!! \App\Manual\ManualContent::richTextToHtml($p) !!}</p>
                    @endforeach

                    @if(!empty($sec['bullets']))
                        <ul>
                            @foreach($sec['bullets'] as $item)
                                <li>{!! \App\Manual\ManualContent::richTextToHtml($item) !!}</li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach($sec['images'] ?? [] as $img)
                        <figure>
                            <img src="{{ $imgSrc($img['src']) }}" alt="{{ $img['alt'] ?? '' }}">
                            @if(!empty($img['caption']))
                                <figcaption>{{ $img['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach

    @if(! $forPdf && $autoprint)
        <script>
            window.addEventListener('load', function () {
                window.setTimeout(function () { window.print(); }, 350);
            });
        </script>
    @endif
</body>
</html>
