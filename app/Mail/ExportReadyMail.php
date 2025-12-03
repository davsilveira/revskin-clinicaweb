<?php

namespace App\Mail;

use App\Models\ExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public ExportRequest $exportRequest;

    /**
     * Create a new message instance.
     */
    public function __construct(ExportRequest $exportRequest)
    {
        $this->exportRequest = $exportRequest;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Exportação pronta para download')
            ->markdown('emails.exports.ready', [
                'exportRequest' => $this->exportRequest,
                'downloadUrl' => route('exports.download', $this->exportRequest),
            ]);
    }
}

