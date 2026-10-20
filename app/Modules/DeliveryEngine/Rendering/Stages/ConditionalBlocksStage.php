<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;

/**
 * docs/38 §38.2 stage 3 / §38.5 — conditional blocks: a v1 no-op
 * pass-through that keeps the slot so adding it later is not a reorder.
 */
class ConditionalBlocksStage implements RenderingStage
{
    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        return $email;
    }
}
