<?php

namespace App\Mail;

use App\Models\Incidente;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvisoIncidente extends Mailable
{
    /** @param  string  $tipo  caida | recuperacion */
    public function __construct(public Incidente $incidente, public string $tipo) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Consola Central] '.($this->tipo === 'caida' ? 'EN FALLA' : 'Recuperada').': '.$this->incidente->nombre());
    }

    public function content(): Content
    {
        return new Content(view: 'correo.incidente', with: [
            'incidente' => $this->incidente,
            'tipo' => $this->tipo,
            'nombre' => $this->incidente->nombre(),
        ]);
    }
}
