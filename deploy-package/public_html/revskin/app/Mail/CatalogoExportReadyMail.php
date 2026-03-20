<?php

namespace App\Mail;

use App\Models\CatalogoExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CatalogoExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public CatalogoExportRequest $catalogoExportRequest;

    public function __construct(CatalogoExportRequest $catalogoExportRequest)
    {
        $this->catalogoExportRequest = $catalogoExportRequest;
    }

    public function build(): self
    {
        return $this->subject('Catálogo de produtos pronto para download')
            ->markdown('emails.catalogo-export-ready', [
                'catalogoExportRequest' => $this->catalogoExportRequest,
                'downloadUrl' => route('catalogo.export.download', $this->catalogoExportRequest),
            ]);
    }
}
