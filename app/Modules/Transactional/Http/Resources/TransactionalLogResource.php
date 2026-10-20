<?php

namespace App\Modules\Transactional\Http\Resources;

use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Transactional\Services\TransactionalLogService;
use App\Modules\Transactional\Services\TransactionalSendService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class TransactionalLogResource extends JsonResource
{
    public bool $withEvents = false;

    public function withEvents(): static
    {
        $this->withEvents = true;

        return $this;
    }

    /** message_events.event_type => BrevoEventLabel */
    private static function label(string $type): ?string
    {
        foreach (TransactionalLogService::EVENT_TYPES as $label => $types) {
            if (in_array($type, $types, true)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $events = $this->events;
        $queued = $events->firstWhere('event_type', TransactionalSendService::QUEUED_EVENT);
        $meta = $queued?->metadata ?? [];
        $timeline = $events->filter(fn (MessageEvent $e): bool => self::label($e->event_type) !== null)->values();
        $last = $timeline->last();

        $source = $meta['source'] ?? ($this->draft_id !== null ? 'composer' : (($this->getAttribute('automation_step_id') ?? null) !== null ? 'automation' : 'composer'));

        $data = [
            'id' => $this->uuid,
            'message_id' => '<'.$this->uuid.'@pagesjaunes-mailer>',
            'recipient_email' => $this->recipient?->email ?? '',
            'subject' => $this->subject,
            'tags' => array_values($meta['tags'] ?? []),
            'sender_email' => $meta['sender_email'] ?? '',
            'template_id' => $meta['template_id'] ?? null,
            'source' => $source,
            'status' => $this->status,
            'last_event' => $last ? self::label($last->event_type) : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'last_event_at' => $last?->occurred_at?->toIso8601ZuluString(),
        ];

        if ($this->withEvents) {
            $data['events'] = $timeline->map(fn (MessageEvent $e): array => [
                'event' => self::label($e->event_type),
                'occurred_at' => $e->occurred_at?->toIso8601ZuluString(),
                'reason' => $e->metadata['reason'] ?? null,
                'link' => $e->url,
                'ip' => $e->ip,
                'user_agent' => $e->user_agent,
                'device_type' => $e->device_type,
            ])->all();
        }

        return $data;
    }
}
