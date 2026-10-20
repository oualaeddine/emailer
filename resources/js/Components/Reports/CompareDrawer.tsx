import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Checkbox,
    DrawerBody,
    DrawerFooter,
    DrawerHeader,
    DrawerHeaderTitle,
    MessageBar,
    MessageBarBody,
    OverlayDrawer,
    SearchBox,
    Spinner,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { DismissRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { fetchCampaignList } from '@/Lib/api/campaigns';
import { fetchCampaignComparison } from '@/Lib/api/reports';
import { apiErrorMessage, formatCount, formatDateTime, formatRate } from '@/Components/Campaigns/campaignUi';
import { LOWER_IS_BETTER, headerSentAt, reportMissingText } from '@/Components/Reports/reportUi';
import type { CampaignV2 } from '@/Lib/types/campaigns';
import type { BrevoStats, CampaignComparisonRow, RateSet } from '@/Lib/types/reports';

const MAX_OTHERS = 4;

const useStyles = makeStyles({
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    picker: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        maxHeight: '300px',
        overflowY: 'auto',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalS,
    },
    pickerMeta: {
        color: tokens.colorNeutralForeground3,
        marginLeft: tokens.spacingHorizontalXS,
    },
    hint: {
        color: tokens.colorNeutralForeground3,
    },
    tableWrap: {
        overflowX: 'auto',
    },
    numeric: {
        textAlign: 'right',
        fontVariantNumeric: 'tabular-nums',
        whiteSpace: 'nowrap',
        '& .fui-TableHeaderCell__button': {
            justifyContent: 'flex-end',
            textAlign: 'right',
        },
    },
    best: {
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground1,
    },
    colHead: {
        minWidth: '140px',
        whiteSpace: 'normal',
    },
    current: {
        display: 'block',
        color: tokens.colorNeutralForeground3,
        fontWeight: tokens.fontWeightRegular,
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalL,
    },
});

interface MetricRow {
    label: string;
    value: (row: CampaignComparisonRow) => number;
    format: (value: number) => string;
    /** Highlight direction; null = no "best" highlight. */
    better: 'high' | 'low' | null;
}

export interface CompareDrawerProps {
    open: boolean;
    campaignId: string;
    onClose: () => void;
}

/**
 * **Comparer** — pick up to 4 other sent campaigns (`GET /campaigns?status_tab=sent`),
 * then `GET /campaigns/compare?ids=` and show KPIs side by side.
 */
export function CompareDrawer({ open, campaignId, onClose }: CompareDrawerProps) {
    const styles = useStyles();
    const t = useText();
    const k = t.campaignReport.kpis;
    const r = t.campaignReport.rates;
    const [query, setQuery] = useState('');
    const [candidates, setCandidates] = useState<CampaignV2[]>([]);
    const [loadingList, setLoadingList] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const [rows, setRows] = useState<CampaignComparisonRow[] | null>(null);
    const [comparing, setComparing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        let cancelled = false;
        const handle = window.setTimeout(async () => {
            setLoadingList(true);
            try {
                const response = await fetchCampaignList({ status_tab: 'sent', q: query || undefined, sort: '-sent_at', per_page: 50 });
                if (!cancelled) {
                    setCandidates(response.data.filter((campaign) => campaign.id !== campaignId));
                }
            } catch (err) {
                if (!cancelled) {
                    setError(apiErrorMessage(err));
                }
            } finally {
                if (!cancelled) {
                    setLoadingList(false);
                }
            }
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(handle);
        };
    }, [open, query, campaignId]);

    function toggle(id: string, checked: boolean) {
        setSelected((previous) => (checked ? (previous.length >= MAX_OTHERS ? previous : [...previous, id]) : previous.filter((x) => x !== id)));
    }

    async function compare() {
        setComparing(true);
        setError(null);
        try {
            const result = await fetchCampaignComparison([campaignId, ...selected]);
            // Keep the current campaign first, then the picking order.
            const order = [campaignId, ...selected];
            setRows([...result].sort((a, b) => order.indexOf(a.campaign.id) - order.indexOf(b.campaign.id)));
        } catch (err) {
            setError(apiErrorMessage(err));
        } finally {
            setComparing(false);
        }
    }

    const metrics: MetricRow[] = useMemo(() => {
        const stat = (key: keyof BrevoStats) => (row: CampaignComparisonRow) => row.globalStats[key];
        const rate = (key: keyof RateSet) => (row: CampaignComparisonRow) => row.rates[key];
        const rateBetter = (key: keyof RateSet): 'high' | 'low' => (LOWER_IS_BETTER.has(key) ? 'low' : 'high');

        return [
            { label: k.sent, value: stat('sent'), format: formatCount, better: null },
            { label: k.delivered, value: stat('delivered'), format: formatCount, better: null },
            { label: r.delivery_rate, value: rate('delivery_rate'), format: formatRate, better: rateBetter('delivery_rate') },
            { label: k.uniqueOpens, value: stat('uniqueViews'), format: formatCount, better: null },
            { label: r.open_rate, value: rate('open_rate'), format: formatRate, better: rateBetter('open_rate') },
            { label: k.uniqueClicks, value: stat('uniqueClicks'), format: formatCount, better: null },
            { label: r.click_rate, value: rate('click_rate'), format: formatRate, better: rateBetter('click_rate') },
            { label: r.click_to_open_rate, value: rate('click_to_open_rate'), format: formatRate, better: rateBetter('click_to_open_rate') },
            { label: k.unsubscriptions, value: stat('unsubscriptions'), format: formatCount, better: null },
            { label: r.unsubscribe_rate, value: rate('unsubscribe_rate'), format: formatRate, better: rateBetter('unsubscribe_rate') },
            { label: k.complaints, value: stat('complaints'), format: formatCount, better: null },
            { label: r.bounce_rate, value: rate('bounce_rate'), format: formatRate, better: rateBetter('bounce_rate') },
        ];
    }, [k, r]);

    return (
        <OverlayDrawer open={open} position="end" size="large" onOpenChange={(_, data) => !data.open && onClose()}>
            <DrawerHeader>
                <DrawerHeaderTitle
                    action={<Button appearance="subtle" aria-label={t.common.close} icon={<DismissRegular />} onClick={onClose} />}
                >
                    {t.campaignReport.compareTitle}
                </DrawerHeaderTitle>
            </DrawerHeader>
            <DrawerBody>
                <div className={styles.body}>
                    {error && (
                        <MessageBar intent="error">
                            <MessageBarBody>{error}</MessageBarBody>
                        </MessageBar>
                    )}
                    <Text className={styles.hint}>{t.campaignReport.compareSelect}</Text>
                    <SearchBox
                        value={query}
                        placeholder={t.campaigns.searchPlaceholder}
                        onChange={(_, data) => setQuery(data.value)}
                        aria-label={t.common.search}
                    />
                    <div className={styles.picker} role="group" aria-label={t.campaignReport.compareSelect}>
                        {loadingList ? (
                            <Spinner size="small" label={t.common.loading} />
                        ) : candidates.length === 0 ? (
                            <Text className={styles.hint}>{t.common.noResults}</Text>
                        ) : (
                            candidates.map((campaign) => {
                                const checked = selected.includes(campaign.id);

                                return (
                                    <Checkbox
                                        key={campaign.id}
                                        checked={checked}
                                        disabled={!checked && selected.length >= MAX_OTHERS}
                                        onChange={(_, data) => toggle(campaign.id, data.checked === true)}
                                        label={
                                            <>
                                                {campaign.name}
                                                <span className={styles.pickerMeta}>· {formatDateTime(campaign.sent_at)}</span>
                                            </>
                                        }
                                    />
                                );
                            })
                        )}
                    </div>
                    {selected.length >= MAX_OTHERS && <Text className={styles.hint}>{reportMissingText.compareMax}</Text>}

                    {comparing && (
                        <div className={styles.center}>
                            <Spinner label={t.common.loading} />
                        </div>
                    )}
                    {!comparing && rows && rows.length > 0 && (
                        <div className={styles.tableWrap}>
                            <Table size="small" aria-label={t.campaignReport.compareTitle}>
                                <TableHeader>
                                    <TableRow>
                                        <TableHeaderCell>{reportMissingText.metric}</TableHeaderCell>
                                        {rows.map((row) => (
                                            <TableHeaderCell key={row.campaign.id} className={styles.colHead}>
                                                <span>
                                                    {row.campaign.name}
                                                    <span className={styles.current}>
                                                        {row.campaign.id === campaignId
                                                            ? reportMissingText.compareCurrent
                                                            : formatDateTime(headerSentAt(row.campaign))}
                                                    </span>
                                                </span>
                                            </TableHeaderCell>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {metrics.map((metric) => {
                                        const values = rows.map(metric.value);
                                        const best =
                                            metric.better === null || rows.length < 2
                                                ? null
                                                : metric.better === 'high'
                                                  ? Math.max(...values)
                                                  : Math.min(...values);

                                        return (
                                            <TableRow key={metric.label}>
                                                <TableCell>{metric.label}</TableCell>
                                                {rows.map((row, index) => (
                                                    <TableCell
                                                        key={row.campaign.id}
                                                        className={mergeClasses(styles.numeric, best !== null && values[index] === best && styles.best)}
                                                    >
                                                        {metric.format(values[index])}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </DrawerBody>
            <DrawerFooter>
                <Button appearance="primary" disabled={selected.length === 0 || comparing} onClick={() => void compare()}>
                    {reportMissingText.compareRun}
                </Button>
                <Button onClick={onClose}>{t.common.close}</Button>
            </DrawerFooter>
        </OverlayDrawer>
    );
}
