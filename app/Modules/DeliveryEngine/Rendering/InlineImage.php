<?php

namespace App\Modules\DeliveryEngine\Rendering;

/**
 * An image embedded as a `cid:` related part (campaign `inline_images`).
 */
final class InlineImage
{
    public function __construct(
        public readonly string $cid,
        public readonly string $contents,
        public readonly string $mimeType,
    ) {}
}
