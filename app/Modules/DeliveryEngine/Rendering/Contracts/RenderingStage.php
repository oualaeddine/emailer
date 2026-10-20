<?php

namespace App\Modules\DeliveryEngine\Rendering\Contracts;

use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;

/**
 * One ordered stage of the docs/38 §38.2 rendering pipeline.
 *
 * Every stage is idempotent: running it on its own output is a no-op
 * (no double-wrapped links, no second pixel, no second signature).
 */
interface RenderingStage
{
    /** Stored on the message at creation time (personalised web version). */
    public const PHASE_PERSONALISE = 'personalise';

    /** Applied inside SendEmailJob on the stored HTML, never persisted. */
    public const PHASE_DELIVERY = 'delivery';

    public function phase(): string;

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail;
}
