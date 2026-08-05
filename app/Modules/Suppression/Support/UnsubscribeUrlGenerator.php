<?php

namespace App\Modules\Suppression\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * docs/23-suppression-list.md §23.4 — the public, unauthenticated
 * unsubscribe link (`/unsubscribe/{token}`). No signed-URL pattern existed
 * anywhere else in the codebase yet (tracking pixels, docs/29 §29.9, are
 * not built), so this establishes it cleanly using Laravel's
 * `URL::signedRoute` convention:
 *
 * - `{token}` is the target email address, URL-safe base64 encoded. It is
 *   deliberately *not* a database-backed token: Laravel's signed URLs
 *   verify the whole URL (including an expiry timestamp) against `APP_KEY`
 *   with no persisted state, and this must work for any address — not just
 *   ones with a `recipients` row (e.g. a purely transactional send).
 * - Tamper/expiry checking is done entirely by the `signed` route
 *   middleware (see `routes/web.php`) before either
 *   `UnsubscribeController` action runs.
 */
class UnsubscribeUrlGenerator
{
    /**
     * docs/23-suppression-list.md §23.4 doesn't specify a link lifetime.
     * 30 days comfortably covers mailbox-provider link-scanning delays and
     * "I'll deal with this later" recipient behaviour without the link
     * staying valid indefinitely.
     */
    private const DEFAULT_TTL_DAYS = 30;

    public function signedUrlFor(string $email, ?Carbon $expiresAt = null): string
    {
        return URL::signedRoute(
            'unsubscribe.show',
            ['token' => self::encode($email)],
            $expiresAt ?? Carbon::now()->addDays(self::DEFAULT_TTL_DAYS),
        );
    }

    public static function encode(string $email): string
    {
        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidArgumentException if the token doesn't decode to a
     *   plausible email address — a malformed/truncated token, distinct
     *   from an invalid *signature* (already rejected by the `signed`
     *   middleware before the controller ever calls this).
     */
    public static function decode(string $token): string
    {
        $padded = strtr($token, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $email = base64_decode($padded, true);

        if ($email === false || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid unsubscribe token.');
        }

        return $email;
    }
}
