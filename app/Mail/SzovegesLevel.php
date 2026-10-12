<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Egyszerű, sima szöveges rendszerlevél (tárgy + törzs). A rendszer minden levele ilyen —
 * a szövegeket a küldő hely állítja össze (BekuldesLevelek), így egy helyen olvashatók.
 */
class SzovegesLevel extends Mailable
{
    public function __construct(string $targy, public string $szoveg)
    {
        $this->subject = $targy;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.szoveges', with: ['szoveg' => $this->szoveg]);
    }
}
