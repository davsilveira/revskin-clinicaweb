@component('mail::message')
# Catálogo de produtos pronto!

Olá {{ $catalogoExportRequest->user->name }},

Sua exportação do catálogo de produtos foi concluída com sucesso e está disponível para download.

**Detalhes da exportação:**
- **Formato:** {{ $catalogoExportRequest->formatLabel() }}
- **Produtos:** {{ $catalogoExportRequest->total_produtos ?? 'N/A' }}
- **Data:** {{ $catalogoExportRequest->completed_at?->format('d/m/Y H:i') }}

@component('mail::button', ['url' => $downloadUrl])
Baixar Arquivo
@endcomponent

**Atenção:** O arquivo estará disponível por tempo limitado.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
