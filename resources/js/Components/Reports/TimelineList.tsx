import { Text, makeStyles, tokens } from '@fluentui/react-components';
import {
    ArchiveRegular,
    ArrowUndoRegular,
    CalendarCancelRegular,
    CalendarLtrRegular,
    CheckmarkCircleRegular,
    DismissCircleRegular,
    MailArrowUpRegular,
    PauseRegular,
    PlayRegular,
    SendRegular,
    TrophyRegular,
    WarningRegular,
    AddCircleRegular,
} from '@fluentui/react-icons';
import type { ReactElement } from 'react';
import { useText } from '@/Hooks/useText';
import { formatDateTime } from '@/Components/Campaigns/campaignUi';
import { reportMissingText } from '@/Components/Reports/reportUi';
import type { ReportTimelineAction, ReportTimelineItem } from '@/Lib/types/reports';

const ICONS: Record<ReportTimelineAction, ReactElement> = {
    created: <AddCircleRegular />,
    scheduled: <CalendarLtrRegular />,
    unscheduled: <CalendarCancelRegular />,
    sent: <SendRegular />,
    paused: <PauseRegular />,
    resumed: <PlayRegular />,
    suspended: <WarningRegular />,
    cancelled: <DismissCircleRegular />,
    completed: <CheckmarkCircleRegular />,
    ab_winner_picked: <TrophyRegular />,
    archived: <ArchiveRegular />,
    unarchived: <ArrowUndoRegular />,
    test_sent: <MailArrowUpRegular />,
};

const useStyles = makeStyles({
    list: {
        listStyleType: 'none',
        margin: 0,
        padding: 0,
        display: 'flex',
        flexDirection: 'column',
    },
    item: {
        display: 'grid',
        gridTemplateColumns: '28px minmax(0, 1fr)',
        columnGap: tokens.spacingHorizontalS,
        paddingBottom: tokens.spacingVerticalM,
        position: 'relative',
    },
    icon: {
        width: '28px',
        height: '28px',
        borderRadius: tokens.borderRadiusCircular,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: tokens.colorNeutralBackground3,
        color: tokens.colorNeutralForeground2,
        fontSize: '16px',
        zIndex: 1,
    },
    rail: {
        position: 'absolute',
        left: '13px',
        top: '28px',
        bottom: 0,
        width: '2px',
        backgroundColor: tokens.colorNeutralStroke2,
    },
    body: {
        display: 'flex',
        flexDirection: 'column',
        paddingTop: tokens.spacingVerticalXXS,
    },
    meta: {
        color: tokens.colorNeutralForeground3,
    },
    empty: {
        color: tokens.colorNeutralForeground3,
    },
});

/** **Chronologie** — campaign actions from the audit log, oldest first. */
export function TimelineList({ items }: { items: ReportTimelineItem[] }) {
    const styles = useStyles();
    const t = useText();
    const sorted = [...items].sort((a, b) => Date.parse(a.occurred_at) - Date.parse(b.occurred_at));

    if (sorted.length === 0) {
        return <Text className={styles.empty}>{reportMissingText.noData}</Text>;
    }

    return (
        <ol className={styles.list}>
            {sorted.map((item, index) => (
                <li key={`${item.action}-${item.occurred_at}-${index}`} className={styles.item}>
                    {index < sorted.length - 1 && <span className={styles.rail} aria-hidden="true" />}
                    <span className={styles.icon} aria-hidden="true">
                        {ICONS[item.action] ?? <CheckmarkCircleRegular />}
                    </span>
                    <span className={styles.body}>
                        <Text weight="semibold">{t.campaignReport.timelineActions[item.action] ?? item.action}</Text>
                        <Text size={200} className={styles.meta}>
                            {formatDateTime(item.occurred_at)}
                            {item.actor ? ` · ${item.actor.name}` : ''}
                        </Text>
                    </span>
                </li>
            ))}
        </ol>
    );
}
