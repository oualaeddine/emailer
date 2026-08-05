<?php

namespace App\Modules\Notifications\Enums;

/**
 * docs/41-notification-center.md §41.3/§41.4 — the delivery channels a
 * notification can fan out to ("cloche in-app, e-mail, Slack") and the
 * second axis of the per-user preference matrix in
 * `notification_preferences`.
 */
enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Slack = 'slack';
}
