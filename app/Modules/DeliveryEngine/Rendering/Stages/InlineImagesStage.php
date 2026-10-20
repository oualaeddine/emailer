<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\InlineImage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use Illuminate\Support\Facades\Storage;

/**
 * docs/43 §43.4.2 item 5 / docs/45 §45.6.3 — "images intégrées": when the
 * campaign sets `inline_images`, every `<img>` served from this app's public
 * storage (`/storage/…`, relative or absolute) is embedded as a related
 * `cid:` part and collected for the mailer. Remote images and the tracking
 * pixel are left as links. Already-embedded (`cid:`) images are skipped.
 */
class InlineImagesStage implements RenderingStage
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function phase(): string
    {
        return self::PHASE_DELIVERY;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if ($context->campaign === null || ! (bool) $context->campaign->inline_images) {
            return $email;
        }

        /** @var array<string, InlineImage> $images */
        $images = [];

        foreach ($email->inlineImages as $image) {
            $images[$image->cid] = $image;
        }

        $html = preg_replace_callback(
            '/(<img\b[^>]*?(?<![\w-])src\s*=\s*)(["\'])(.*?)\2/i',
            function (array $m) use (&$images): string {
                $path = $this->storagePath(trim(html_entity_decode($m[3], ENT_QUOTES)));

                if ($path === null) {
                    return $m[0];
                }

                $cid = 'img-'.substr(sha1($path), 0, 12).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');

                if (! isset($images[$cid])) {
                    $disk = Storage::disk('public');
                    $images[$cid] = new InlineImage($cid, (string) $disk->get($path), (string) ($disk->mimeType($path) ?: 'application/octet-stream'));
                }

                return $m[1].$m[2].'cid:'.$cid.$m[2];
            },
            $email->html,
        ) ?? $email->html;

        return $email->withHtml($html)->withInlineImages(array_values($images));
    }

    private function storagePath(string $src): ?string
    {
        $base = url('storage').'/';

        $relative = match (true) {
            str_starts_with($src, $base) => substr($src, strlen($base)),
            str_starts_with($src, '/storage/') => substr($src, strlen('/storage/')),
            default => null,
        };

        if ($relative === null) {
            return null;
        }

        $relative = rawurldecode((string) strtok($relative, '?#'));

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relative) || (int) $disk->size($relative) > self::MAX_BYTES) {
            return null;
        }

        return $relative;
    }
}
