<?php

namespace App\Modules\DeliveryEngine\Support;

/**
 * next-sender — custom headers of transactional messages. They may never
 * replace the envelope or MIME headers the platform sets itself (From, To,
 * Subject, Sender, Reply-To, List-Unsubscribe, X-Mailer-*…).
 */
final class CustomHeaders
{
    private const RESERVED = [
        'from', 'to', 'cc', 'bcc', 'subject', 'sender', 'reply-to', 'return-path',
        'list-unsubscribe', 'list-unsubscribe-post', 'message-id', 'date',
        'mime-version', 'content-type', 'content-transfer-encoding', 'content-disposition',
        'dkim-signature', 'received',
    ];

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) === 1;
    }

    public static function isReserved(string $name): bool
    {
        $lower = strtolower(trim($name));

        return in_array($lower, self::RESERVED, true) || str_starts_with($lower, 'x-mailer-');
    }

    /** A header usable as-is: valid name, not reserved, single-line string value. */
    public static function isAllowed(mixed $name, mixed $value): bool
    {
        return is_string($name) && self::isValidName($name) && ! self::isReserved($name)
            && is_string($value) && preg_match('/[\r\n]/', $value) !== 1;
    }
}
