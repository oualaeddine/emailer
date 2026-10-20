<?php

namespace App\Modules\DeliveryEngine\Rendering;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Composer\Models\Draft;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Closure;

/**
 * Per-message inputs shared by every stage of {@see RenderingPipeline}.
 *
 * `recipient` is the address the message is delivered to (unsubscribe /
 * preference links, To header); `mergeRecipient` supplies the merge-tag
 * values — they differ only for a campaign test send rendered with a real
 * contact's data (docs/45 §45.4.2 `test-send`).
 */
final class RenderContext
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public readonly Recipient $mergeRecipient;

    public function __construct(
        public readonly Recipient $recipient,
        public readonly ?Message $message = null,
        public readonly ?Campaign $campaign = null,
        public readonly ?Draft $draft = null,
        ?Recipient $mergeRecipient = null,
        public readonly bool $preview = false,
    ) {
        $this->mergeRecipient = $mergeRecipient ?? $recipient;
    }

    public function trackingEnabled(): bool
    {
        if ($this->preview || $this->message === null) {
            return false;
        }

        return $this->campaign === null || (bool) ($this->campaign->tracking_enabled ?? true);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolve();
        }

        return $this->memo[$key];
    }
}
