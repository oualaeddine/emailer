import { useCallback, useEffect, useState } from 'react';
import {
    Badge,
    Button,
    Divider,
    Popover,
    PopoverSurface,
    PopoverTrigger,
    Spinner,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AlertRegular, CheckmarkRegular } from '@fluentui/react-icons';
import {
    fetchNotifications,
    fetchUnreadNotificationCount,
    markAllNotificationsAsRead,
    markNotificationAsRead,
} from '@/Lib/api/notifications';
import type { AppNotification } from '@/Lib/types/notifications';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    wrapper: {
        position: 'relative',
        display: 'inline-flex',
    },
    badge: {
        position: 'absolute',
        top: '-2px',
        right: '-2px',
        pointerEvents: 'none',
    },
    surface: {
        width: '360px',
        maxWidth: '90vw',
        maxHeight: '480px',
        display: 'flex',
        flexDirection: 'column',
        padding: 0,
    },
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        gap: tokens.spacingHorizontalS,
    },
    list: {
        overflowY: 'auto',
        display: 'flex',
        flexDirection: 'column',
    },
    item: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
        cursor: 'pointer',
        backgroundColor: 'transparent',
        textAlign: 'left',
        border: 'none',
        width: '100%',
        font: 'inherit',
        color: 'inherit',
    },
    itemUnread: {
        backgroundColor: tokens.colorNeutralBackground2,
    },
    itemTitle: {
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground1,
    },
    itemMessage: {
        color: tokens.colorNeutralForeground2,
        fontSize: tokens.fontSizeBase200,
    },
    itemTimestamp: {
        color: tokens.colorNeutralForeground3,
        fontSize: tokens.fontSizeBase100,
    },
    statusRow: {
        padding: tokens.spacingVerticalXL,
        textAlign: 'center',
        color: tokens.colorNeutralForeground3,
    },
});

/**
 * docs/41-notification-center.md §41.5 — Top Bar bell icon with unread
 * `CounterBadge`-style count, opening a `Popover` listing recent
 * notifications (reverse-chronological, per `NotificationController::index()`).
 * Mark-as-read fires on item click; "tout marquer comme lu" clears all.
 * Scoped entirely to the authenticated user via
 * `resources/js/Lib/api/notifications.ts` (which hits the
 * `$request->user()`-scoped backend endpoints — no user id is ever passed
 * from the client).
 */
export function NotificationBell() {
    const styles = useStyles();
    const t = useText();
    const [open, setOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);

    const refreshUnreadCount = useCallback(() => {
        fetchUnreadNotificationCount()
            .then(setUnreadCount)
            .catch(() => undefined);
    }, []);

    useEffect(() => {
        refreshUnreadCount();
    }, [refreshUnreadCount]);

    const loadNotifications = useCallback(() => {
        setLoading(true);
        setLoadError(false);
        fetchNotifications()
            .then((response) => setNotifications(response.data))
            .catch(() => setLoadError(true))
            .finally(() => setLoading(false));
    }, []);

    function handleOpenChange(_event: unknown, data: { open: boolean }) {
        setOpen(data.open);

        if (data.open) {
            loadNotifications();
            refreshUnreadCount();
        }
    }

    function handleItemClick(notification: AppNotification) {
        if (notification.read_at) {
            return;
        }

        markNotificationAsRead(notification.id)
            .then((updated) => {
                setNotifications((current) => current.map((item) => (item.id === updated.id ? updated : item)));
                refreshUnreadCount();
            })
            .catch(() => undefined);
    }

    function handleMarkAllRead() {
        markAllNotificationsAsRead()
            .then((count) => {
                const now = new Date().toISOString();
                setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at ?? now })));
                setUnreadCount(count);
            })
            .catch(() => undefined);
    }

    return (
        <span className={styles.wrapper}>
            <Popover open={open} onOpenChange={handleOpenChange} positioning="below-end">
                <PopoverTrigger disableButtonEnhancement>
                    <Button
                        appearance="subtle"
                        icon={<AlertRegular />}
                        aria-label={`${t.notifications.title}${unreadCount > 0 ? ` – ${unreadCount} ${t.notifications.unreadAriaLabel}` : ''}`}
                    />
                </PopoverTrigger>
                <PopoverSurface className={styles.surface}>
                    <div className={styles.header}>
                        <Text weight="semibold">{t.notifications.title}</Text>
                        <Button
                            appearance="transparent"
                            size="small"
                            icon={<CheckmarkRegular />}
                            onClick={handleMarkAllRead}
                            disabled={unreadCount === 0}
                        >
                            {t.notifications.markAllRead}
                        </Button>
                    </div>
                    <Divider />
                    <div className={styles.list}>
                        {loading && (
                            <div className={styles.statusRow}>
                                <Spinner size="small" label={t.notifications.loading} />
                            </div>
                        )}
                        {!loading && loadError && <div className={styles.statusRow}>{t.notifications.loadError}</div>}
                        {!loading && !loadError && notifications.length === 0 && (
                            <div className={styles.statusRow}>{t.notifications.noNotifications}</div>
                        )}
                        {!loading &&
                            !loadError &&
                            notifications.map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    className={`${styles.item} ${notification.read_at ? '' : styles.itemUnread}`}
                                    onClick={() => handleItemClick(notification)}
                                >
                                    <span className={styles.itemTitle}>{notification.data.title ?? notification.type}</span>
                                    {notification.data.message && (
                                        <span className={styles.itemMessage}>{notification.data.message}</span>
                                    )}
                                    <span className={styles.itemTimestamp}>
                                        {notification.created_at ? new Date(notification.created_at).toLocaleString('fr-FR') : ''}
                                    </span>
                                </button>
                            ))}
                    </div>
                </PopoverSurface>
            </Popover>
            {unreadCount > 0 && (
                <Badge className={styles.badge} size="small" color="danger" shape="circular">
                    {unreadCount > 99 ? '99+' : unreadCount}
                </Badge>
            )}
        </span>
    );
}
