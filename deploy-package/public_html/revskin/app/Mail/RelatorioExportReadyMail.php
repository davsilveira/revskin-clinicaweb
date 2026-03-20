<?php

namespace App\Mail;

use App\Models\RelatorioExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RelatorioExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public RelatorioExportRequest $relatorioExportRequest;

    public function __construct(RelatorioExportRequest $relatorioExportRequest)
    {
        $this->relatorioExportRequest = $relatorioExportRequest;
    }

    public function build(): self
    {
        return $this->subject('Relatório pronto para download')
            ->markdown('emails.relatorio-export-ready', [
                'relatorioExportRequest' => $this->relatorioExportRequest,
                'downloadUrl' => route('relatorios.export.download', $this->relatorioExportRequest),
            ]);
    }
}
