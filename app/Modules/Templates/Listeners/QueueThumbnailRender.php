<?php

namespace App\Modules\Templates\Listeners;

use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Jobs\RenderThumbnailJob;
use App\Modules\Templates\Services\TemplateThumbnailService;

/**
 * docs/45 §45.6.2 / §45.9 — dispatches {@see RenderThumbnailJob} after a
 * template or campaign design save.
 */
class QueueThumbnailRender
{
    public function handle(TemplateSaved|CampaignDesignSaved $event): void
    {
        if (! TemplateThumbnailService::enabled()) {
            return;
        }

        if ($event instanceof TemplateSaved) {
            RenderThumbnailJob::dispatch(TemplateThumbnailService::MODEL_TEMPLATE, $event->templateId);
        } else {
            RenderThumbnailJob::dispatch(TemplateThumbnailService::MODEL_CAMPAIGN, $event->campaignId);
        }
    }
}
