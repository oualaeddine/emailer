<?php

namespace App\Modules\DeliveryEngine\Rendering\Support;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\Suppression\Preferences\Services\PreferenceTokenService;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Signed URLs behind the `links` merge tags (docs/45 §45.5.1 MergeTag group
 * `links`, §45.6.3): `{{ mirror }}`, `{{ unsubscribe }}`, `{{ update_profile }}`.
 */
class SystemUrls
{
    public const MIRROR_ROUTE = 'mirror.show';

    public const CAMPAIGN_MIRROR_ROUTE = 'mirror.campaign';

    public const PREFERENCES_ROUTE = 'preferences.show';

    public function __construct(
        private readonly UnsubscribeUrlGenerator $unsubscribe,
        private readonly PreferenceTokenService $preferences,
        private readonly ExternalReferences $references,
    ) {}

    public function unsubscribe(RenderContext $context): string
    {
        return $context->remember('url.unsubscribe', fn (): string => $context->preview
            ? '#'
            : $this->unsubscribe->signedUrlFor($context->recipient->email));
    }

    /**
     * WP-74's signed, expiring `/preferences/{token}` link
     * ({@see PreferenceTokenService::preferencesUrl()}, standard expiry) for
     * the campaign's `update_profile_form_id` form; `#` in previews or
     * without the route.
     */
    public function updateProfile(RenderContext $context): string
    {
        return $context->remember('url.update_profile', function () use ($context): string {
            if ($context->preview || ! Route::has(self::PREFERENCES_ROUTE)) {
                return '#';
            }

            $formId = $context->campaign?->update_profile_form_id;
            $formUuid = $formId !== null
                ? $this->references->uuidForId(ExternalReferences::UPDATE_PROFILE_FORMS_TABLE, (int) $formId)
                : null;

            return $this->preferences->preferencesUrl($context->recipient->email, $formUuid);
        });
    }

    /**
     * Signed `/m/{message}`; campaigns only when `mirror_link_enabled`.
     * No expiry unless the campaign sets an e-mail expiration
     * (expiry = send time + duration).
     */
    public function mirror(RenderContext $context): string
    {
        return $context->remember('url.mirror', function () use ($context): string {
            $message = $context->message;

            if ($context->preview || $message === null || blank($message->uuid)) {
                return '';
            }

            if ($context->campaign !== null && ! (bool) ($context->campaign->mirror_link_enabled ?? true)) {
                return '';
            }

            return URL::signedRoute(
                self::MIRROR_ROUTE,
                ['message' => $message->uuid],
                $this->expiry($context->campaign),
            );
        });
    }

    public function campaignMirror(Campaign $campaign): string
    {
        return URL::signedRoute(self::CAMPAIGN_MIRROR_ROUTE, ['campaign' => $campaign->uuid], $this->expiry($campaign));
    }

    private function expiry(?Campaign $campaign): ?Carbon
    {
        $duration = (int) ($campaign?->email_expiration_duration ?? 0);

        if ($duration <= 0) {
            return null;
        }

        $from = Carbon::now();

        return match (strtolower((string) $campaign->email_expiration_unit)) {
            'hour', 'hours' => $from->addHours($duration),
            'week', 'weeks' => $from->addWeeks($duration),
            'month', 'months' => $from->addMonths($duration),
            default => $from->addDays($duration),
        };
    }
}
