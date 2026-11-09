<?php

namespace App\Mail;

use App\Models\Informe;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InformeDiario extends Mailable
{
    public function __construct(public Informe $informe, public bool $prueba = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->prueba ? '[PRUEBA] ' : '').$this->informe->asunto());
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->informe->html);
    }
}
