import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { AppNotification, NotificationPreference } from '@/Lib/types/notifications';

/**
 * docs/41-notification-center.md §41.5 — bell panel data source.
 * Mirrors `App\Modules\Notifications\Http\Controllers\NotificationController`
 * / `NotificationPreferencesController`.
 */
export async function fetchNotifications(page = 1): Promise<PaginatedResponse<AppNotification>> {
    const response = await axios.get<PaginatedResponse<AppNotification>>('/api/v1/notifications', {
        params: { page },
    });

    return response.data;
}

export async function fetchUnreadNotificationCount(): Promise<number> {
    const response = await axios.get<{ count: number }>('/api/v1/notifications/unread-count');

    return response.data.count;
}

export async function markNotificationAsRead(id: string): Promise<AppNotification> {
    const response = await axios.patch<{ data: AppNotification }>(`/api/v1/notifications/${id}/read`);

    return response.data.data;
}

export async function markAllNotificationsAsRead(): Promise<number> {
    const response = await axios.post<{ count: number }>('/api/v1/notifications/mark-all-read');

    return response.data.count;
}

export async function fetchNotificationPreferences(): Promise<NotificationPreference[]> {
    const response = await axios.get<{ data: NotificationPreference[] }>('/api/v1/notification-preferences');

    return response.data.data;
}

export async function updateNotificationPreferences(
    preferences: Array<Pick<NotificationPreference, 'category' | 'channel' | 'enabled'>>,
): Promise<NotificationPreference[]> {
    const response = await axios.patch<{ data: NotificationPreference[] }>('/api/v1/notification-preferences', {
        preferences,
    });

    return response.data.data;
}
