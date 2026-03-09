@component('mail::message')
# Sua exportação está pronta!

Olá {{ $exportRequest->user->name }},

Sua exportação foi concluída com sucesso e está disponível para download.

**Detalhes da exportação:**
- **Tipo:** {{ ucfirst($exportRequest->type) }}
- **Registros:** {{ $exportRequest->total_records ?? 'N/A' }}
- **Data:** {{ $exportRequest->completed_at?->format('d/m/Y H:i') }}

@component('mail::button', ['url' => $downloadUrl])
Baixar Arquivo
@endcomponent

**Atenção:** O arquivo estará disponível por tempo limitado.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent

