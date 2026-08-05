/**
 * docs/41-notification-center.md §41.5 — Notification Center bell panel.
 * Mirrors `App\Modules\Notifications\Http\Resources\NotificationResource`.
 * `type` is a short slug reduced server-side from the notification class's
 * FQCN (e.g. `RoleChangedNotification` -> `role_changed`) — see
 * `NotificationResource::shortType()`.
 */
export interface AppNotification {
    id: string;
    type: string;
    data: {
        title?: string;
        message?: string;
        category?: string;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string | null;
}

/**
 * docs/41-notification-center.md §41.2 — Notification Category taxonomy.
 * Mirrors `App\Modules\Notifications\Enums\NotificationCategory`.
 */
export type NotificationCategory =
    | 'delivery_infrastructure'
    | 'campaign_activity'
    | 'import_activity'
    | 'account_security';

/** Mirrors `App\Modules\Notifications\Enums\NotificationChannel`. */
export type NotificationChannel = 'in_app' | 'email' | 'slack';

/**
 * docs/41-notification-center.md §41.4 — per-user, per-category,
 * per-channel preference row. `locked: true` marks the one combination the
 * user cannot turn off (`account_security` / `in_app`).
 */
export interface NotificationPreference {
    category: NotificationCategory;
    channel: NotificationChannel;
    enabled: boolean;
    locked: boolean;
}
