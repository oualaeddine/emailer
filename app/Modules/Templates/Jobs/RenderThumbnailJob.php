<?php

namespace App\Modules\Templates\Jobs;

use App\Modules\Templates\Services\TemplateThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/45 §45.9 — `RenderThumbnailJob(modelType, modelId)`; `modelType` is
 * `template` | `campaign` | `saved_section`.
 */
class RenderThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly string $modelType, public readonly int $modelId) {}

    public function handle(TemplateThumbnailService $thumbnails): void
    {
        $thumbnails->renderFor($this->modelType, $this->modelId);
    }
}
