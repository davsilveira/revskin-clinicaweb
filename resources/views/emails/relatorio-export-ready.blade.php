@component('mail::message')
# Relatório pronto!

Olá {{ $relatorioExportRequest->user->name }},

Sua exportação do relatório foi concluída com sucesso e está disponível para download.

**Detalhes da exportação:**
- **Relatório:** {{ $relatorioExportRequest->typeLabel() }}
- **Formato:** {{ $relatorioExportRequest->formatLabel() }}
- **Registros:** {{ $relatorioExportRequest->total_records ?? 'N/A' }}
- **Data:** {{ $relatorioExportRequest->completed_at?->format('d/m/Y H:i') }}

@component('mail::button', ['url' => $downloadUrl])
Baixar Arquivo
@endcomponent

**Atenção:** O arquivo estará disponível por tempo limitado.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
