<?php

namespace App\Modules\Suppression\Preferences\Services;

use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * docs/45 §45.6.6 — "signed token, same scheme as unsubscribe": `{token}` is the
 * URL-safe base64 of the contact's email, the link itself is a Laravel signed
 * (and expiring) URL verified by the `signed` middleware.
 */
class PreferenceTokenService
{
    public const PREFERENCES_TTL_DAYS = 30;

    public const CONFIRM_TTL_DAYS = 7;

    public function preferencesUrl(string $email, ?string $formUuid = null, ?Carbon $expiresAt = null): string
    {
        return URL::signedRoute(
            'preferences.show',
            array_filter(['token' => UnsubscribeUrlGenerator::encode($email), 'form' => $formUuid]),
            $expiresAt ?? now()->addDays(self::PREFERENCES_TTL_DAYS),
        );
    }

    public function confirmUrl(string $email, ?Carbon $expiresAt = null): string
    {
        return URL::signedRoute(
            'preferences.confirm',
            ['token' => UnsubscribeUrlGenerator::encode($email)],
            $expiresAt ?? now()->addDays(self::CONFIRM_TTL_DAYS),
        );
    }

    public function decode(string $token): string
    {
        try {
            return UnsubscribeUrlGenerator::decode($token);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('Invalid preferences link.');
        }
    }
}
