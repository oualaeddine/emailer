<?php

namespace App\Modules\Automations\Listeners;

use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\Tracking\Events\MessageEventRecorded;
use Illuminate\Support\Facades\DB;

/**
 * Triggers `campaign_opened` — { campaign_ids: uuid[]|null } and
 * `campaign_clicked` — { campaign_ids, link_url: exact URL or prefix
 * ending with "*" | null }. `campaign_ids = null` = any campaign. Test
 * sends and non-campaign messages never trigger.
 */
class StartRunsOnCampaignEngagement
{
    public function __construct(private readonly TriggerDispatcher $dispatcher) {}

    public function handle(MessageEventRecorded $event): void
    {
        $type = match ($event->type) {
            'opened' => AutomationTriggerType::CampaignOpened,
            'clicked' => AutomationTriggerType::CampaignClicked,
            default => null,
        };

        if ($type === null || $event->campaignId === null || $event->recipientId === null) {
            return;
        }

        $automations = $this->dispatcher->activeAutomations($type);
        if ($automations->isEmpty() || (bool) DB::table('messages')->where('id', $event->messageId)->value('is_test')) {
            return;
        }

        $campaignUuid = DB::table('campaigns')->where('id', $event->campaignId)->value('uuid');

        $matching = $automations->filter(function ($automation) use ($campaignUuid, $type, $event): bool {
            $config = $automation->trigger_config;
            $campaignIds = $config['campaign_ids'] ?? null;

            if (is_array($campaignIds) && $campaignIds !== [] && ! in_array($campaignUuid, $campaignIds, true)) {
                return false;
            }

            return $type !== AutomationTriggerType::CampaignClicked || self::urlMatches($config['link_url'] ?? null, $event->url);
        });

        $this->dispatcher->dispatch($matching, [$event->recipientId], [
            'trigger' => $type->value,
            'campaign_id' => $campaignUuid,
            'url' => $event->url,
        ]);
    }

    public static function urlMatches(?string $pattern, ?string $url): bool
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            return true;
        }

        if ($url === null) {
            return false;
        }

        return str_ends_with($pattern, '*')
            ? str_starts_with($url, substr($pattern, 0, -1))
            : $url === $pattern;
    }
}
