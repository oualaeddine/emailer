<?php

namespace App\Modules\Forms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notification sent to the addresses of a form's `settings.notify_emails` after a public
 * submission (same queued Mail path as the double opt-in mail, so it goes through the
 * configured mailer). The view escapes every value.
 */
class FormSubmissionNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public const STATUS_CREATED = 'created';

    public const STATUS_UPDATED = 'updated';

    public const STATUS_PENDING = 'pending_confirmation';

    /** @param  list<array{label: string, value: string}>  $values */
    public function __construct(
        public readonly string $formName,
        public readonly array $values,
        public readonly string $contactStatus,
        public readonly ?string $contactUrl,
    ) {}

    public function statusLabel(): string
    {
        return match ($this->contactStatus) {
            self::STATUS_CREATED => 'Contact créé',
            self::STATUS_PENDING => 'En attente de confirmation (double opt-in) : le contact n’est ajouté aux listes qu’après sa confirmation',
            default => 'Contact mis à jour',
        };
    }

    public function envelope(): Envelope
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $this->formName));

        return new Envelope(subject: 'Nouvelle inscription : '.mb_substr($name, 0, 150));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.form-submission-notification', with: ['statusLabel' => $this->statusLabel()]);
    }
}
