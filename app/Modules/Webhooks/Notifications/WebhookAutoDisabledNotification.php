<?php

namespace App\Modules\Webhooks\Notifications;

use Illuminate\Notifications\Notification;

/** docs/45 §45.7.7 — category `delivery_infrastructure`. French-only. */
class WebhookAutoDisabledNotification extends Notification
{
    public function __construct(private readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Webhook désactivé',
            'message' => sprintf('Le webhook « %s » a été désactivé après 50 échecs consécutifs.', $this->url),
            'category' => 'delivery_infrastructure',
        ];
    }
}
