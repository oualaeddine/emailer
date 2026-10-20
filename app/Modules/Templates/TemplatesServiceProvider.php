<?php

namespace App\Modules\Templates;

use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Listeners\QueueThumbnailRender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(TemplateSaved::class, QueueThumbnailRender::class);
        Event::listen(CampaignDesignSaved::class, QueueThumbnailRender::class);
    }
}
