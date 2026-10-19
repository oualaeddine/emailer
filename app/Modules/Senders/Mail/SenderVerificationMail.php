<?php

namespace App\Modules\Senders\Mail;

use App\Modules\Senders\Models\Sender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SenderVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Sender $sender, public readonly string $verificationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmez votre adresse d\'expéditeur');
    }

    public function content(): Content
    {
        return new Content(view: 'senders.verification');
    }
}
