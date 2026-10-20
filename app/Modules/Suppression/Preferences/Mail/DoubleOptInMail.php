<?php

namespace App\Modules\Suppression\Preferences\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * docs/45 §45.6.6 — double opt-in confirmation. Uses the seeded transactional
 * template (merge tag `{{ doi_link }}`) when `$bodyHtml` is given, otherwise the
 * `emails.double-opt-in` view.
 */
class DoubleOptInMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $confirmationUrl,
        public readonly ?string $bodyHtml = null,
        public readonly string $mailSubject = 'Confirmez votre inscription',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return $this->bodyHtml !== null
            ? new Content(htmlString: $this->bodyHtml)
            : new Content(view: 'emails.double-opt-in');
    }
}
