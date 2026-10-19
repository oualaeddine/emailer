import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Dropdown, Option, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import { HistoryRegular } from '@fluentui/react-icons';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { fetchTimeline } from '@/Lib/api/contactDetail';
import type { ContactCounters, TimelineEntry, TimelineEventType } from '@/Lib/types/contacts';
import { fill, formatDateTime } from './format';

const BAD: TimelineEventType[] = ['soft_bounce', 'hard_bounce', 'blocked', 'unsubscribed', 'complaint', 'blocklisted'];
const GOOD: TimelineEventType[] = ['delivered', 'opened', 'clicked', 'opt_in_confirmed'];

const FILTER_TYPES: TimelineEventType[] = [
    'sent', 'delivered', 'opened', 'clicked', 'soft_bounce', 'hard_bounce', 'unsubscribed', 'complaint', 'imported', 'list_added',
];

const useStyles = makeStyles({
    toolbar: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalM, marginBottom: tokens.spacingVerticalM },
    row: {
        display: 'flex',
        gap: tokens.spacingHorizontalM,
        padding: `${tokens.spacingVerticalS} 0`,
        borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
    when: { width: '160px', flex: '0 0 auto', color: tokens.colorNeutralForeground3 },
    body: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalXXS, minWidth: 0 },
    more: { marginTop: tokens.spacingVerticalM, alignSelf: 'flex-start' },
});

interface Props {
    contactId: string;
    onCounters?: (counters: ContactCounters) => void;
}

function describe(t: ReturnType<typeof useText>, entry: TimelineEntry): string[] {
    const parts: string[] = [];
    if (entry.campaign) parts.push(entry.campaign.name);
    else if (entry.subject) parts.push(entry.subject);
    if (entry.list) parts.push(entry.list.name);
    if (entry.url) parts.push(fill(t.contactDetail.clickedLink, entry.url));
    if (entry.device_type) parts.push(fill(t.contactDetail.viaDevice, entry.device_type));
    if (entry.actor) parts.push(entry.actor.name);

    return parts;
}

export function ActivityTimeline({ contactId, onCounters }: Props) {
    const t = useText();
    const styles = useStyles();
    const [entries, setEntries] = useState<TimelineEntry[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [types, setTypes] = useState<TimelineEventType[]>([]);

    const load = useCallback(
        async (from: string | null, replace: boolean) => {
            setLoading(true);
            setError(false);
            try {
                const page = await fetchTimeline(contactId, from, types);
                setEntries((current) => (replace ? page.data : [...current, ...page.data]));
                setCursor(page.nextCursor);
                if (page.counters && onCounters) onCounters(page.counters);
            } catch {
                setError(true);
            } finally {
                setLoading(false);
            }
        },
        [contactId, types, onCounters],
    );

    useEffect(() => {
        void load(null, true);
    }, [load]);

    return (
        <div>
            <div className={styles.toolbar}>
                <Text>{t.contactDetail.timelineFilter}</Text>
                <Dropdown
                    multiselect
                    placeholder={t.common.all}
                    selectedOptions={types}
                    value={types.map((type) => t.contactDetail.timelineTypes[type]).join(', ')}
                    onOptionSelect={(_, data) => setTypes(data.selectedOptions as TimelineEventType[])}
                >
                    {FILTER_TYPES.map((type) => (
                        <Option key={type} value={type}>
                            {t.contactDetail.timelineTypes[type]}
                        </Option>
                    ))}
                </Dropdown>
            </div>

            {error && (
                <Text role="alert">
                    {t.common.genericError}{' '}
                    <Button size="small" onClick={() => void load(null, true)}>
                        {t.common.retry}
                    </Button>
                </Text>
            )}

            {!loading && !error && entries.length === 0 && (
                <EmptyState icon={HistoryRegular} title={t.contactDetail.timelineEmpty} />
            )}

            {entries.map((entry) => (
                <div key={entry.id} className={styles.row}>
                    <Text className={styles.when}>{formatDateTime(entry.occurred_at)}</Text>
                    <div className={styles.body}>
                        <span>
                            <Badge
                                appearance="tint"
                                color={BAD.includes(entry.type) ? 'danger' : GOOD.includes(entry.type) ? 'success' : 'informative'}
                            >
                                {t.contactDetail.timelineTypes[entry.type] ?? entry.type}
                            </Badge>
                        </span>
                        {describe(t, entry).map((line, i) => (
                            <Text key={i} size={200}>
                                {line}
                            </Text>
                        ))}
                    </div>
                </div>
            ))}

            {loading && <Spinner size="small" label={t.common.loading} />}
            {cursor !== null && !loading && (
                <Button className={styles.more} onClick={() => void load(cursor, false)}>
                    {t.common.loadMore}
                </Button>
            )}
        </div>
    );
}
