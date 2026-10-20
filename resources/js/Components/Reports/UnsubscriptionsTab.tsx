import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Button, Link, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import { fetchReportContacts } from '@/Lib/api/reports';
import { apiErrorMessage, formatCount, formatDateTime, formatRate } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { HorizontalBars } from '@/Components/Reports/HorizontalBars';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { ReportSection, SectionGrid, TabStack } from '@/Components/Reports/ReportSection';
import { ViewContactsButton } from '@/Components/Reports/ViewContactsButton';
import { reportMissingText } from '@/Components/Reports/reportUi';
import type { ReportContactRow, ReportUnsubscriptions } from '@/Lib/types/reports';

const PAGE_SIZE = 50;
const RECENT_SIZE = 20;

const useStyles = makeStyles({
    empty: { color: tokens.colorNeutralForeground3 },
    more: {
        display: 'flex',
        justifyContent: 'center',
        paddingTop: tokens.spacingVerticalS,
    },
});

export interface UnsubscriptionsTabProps {
    data: ReportUnsubscriptions;
    campaignId: string;
    campaignName: string;
    onError: (message: string) => void;
}

/**
 * **Désinscriptions** — count and rate, spam complaints, by list, survey
 * reasons and the unsubscribed contacts (first 20, then `report/contacts`).
 */
export function UnsubscriptionsTab({ data, campaignId, campaignName, onError }: UnsubscriptionsTabProps) {
    const styles = useStyles();
    const t = useText();
    const k = t.campaignReport.kpis;
    const u = t.campaignReport.unsubscriptions;
    const { props } = usePage<{ auth: { permissions: string[] } }>();
    const canBrowse = hasPermission(props.auth?.permissions ?? [], 'recipients.view');

    const [contacts, setContacts] = useState<ReportContactRow[]>(data.recent);
    // null cursor + fetched=false → the first page has not been requested yet.
    const [cursor, setCursor] = useState<string | null>(null);
    const [fetched, setFetched] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const canLoadMore = canBrowse && (fetched ? cursor !== null : data.recent.length >= RECENT_SIZE);

    async function loadMore() {
        setLoadingMore(true);
        try {
            const page = await fetchReportContacts(campaignId, {
                metric: 'unsubscribed',
                per_page: PAGE_SIZE,
                cursor: cursor ?? undefined,
            });
            setContacts((previous) => (fetched ? [...previous, ...page.data] : page.data));
            setCursor(page.meta.next_cursor);
            setFetched(true);
        } catch (error) {
            onError(apiErrorMessage(error));
        } finally {
            setLoadingMore(false);
        }
    }

    const fullName = (row: ReportContactRow) => [row.first_name, row.last_name].filter(Boolean).join(' ') || t.common.emptyValue;

    return (
        <TabStack>
            <KpiRow>
                <KpiTile
                    label={k.unsubscriptions}
                    value={formatCount(data.unsubscriptions)}
                    rateText={formatRate(data.unsubscribe_rate)}
                />
                <KpiTile label={k.complaints} value={formatCount(data.complaints)} rateText={formatRate(data.complaint_rate)} />
            </KpiRow>

            <SectionGrid>
                <ReportSection title={u.byList}>
                    {data.by_list.length === 0 ? (
                        <Text className={styles.empty}>{reportMissingText.noData}</Text>
                    ) : (
                        <ChartDataTable
                            caption={u.byList}
                            columns={[
                                { label: t.common.name },
                                { label: k.unsubscriptions, numeric: true },
                                { label: k.complaints, numeric: true },
                            ]}
                            rows={[...data.by_list]
                                .sort((a, b) => b.unsubscriptions - a.unsubscriptions)
                                .map((row) => [row.name, formatCount(row.unsubscriptions), formatCount(row.complaints)])}
                        />
                    )}
                </ReportSection>
                <ReportSection title={u.reasons}>
                    <HorizontalBars
                        ariaLabel={u.reasons}
                        items={[...data.reasons]
                            .sort((a, b) => b.count - a.count)
                            .map((row) => ({ key: row.reason, label: row.reason, value: row.count }))}
                    />
                </ReportSection>
            </SectionGrid>

            <ReportSection
                title={u.contacts}
                actions={
                    <ViewContactsButton campaignId={campaignId} campaignName={campaignName} metrics={['unsubscribed']} onError={onError} />
                }
            >
                {contacts.length === 0 ? (
                    <Text className={styles.empty}>{reportMissingText.noData}</Text>
                ) : (
                    <>
                        <ChartDataTable
                            caption={u.contacts}
                            columns={[
                                { label: reportMissingText.email },
                                { label: t.common.name },
                                { label: t.common.date },
                                { label: t.campaignReport.deliverability.reason },
                            ]}
                            rows={contacts.map((row) => [
                                row.recipient_id && canBrowse ? (
                                    <Link key="email" href={`/contacts/${row.recipient_id}`}>
                                        {row.email}
                                    </Link>
                                ) : (
                                    row.email
                                ),
                                fullName(row),
                                formatDateTime(row.occurred_at),
                                row.reason ?? t.common.emptyValue,
                            ])}
                        />
                        {canLoadMore && (
                            <div className={styles.more}>
                                <Button disabled={loadingMore} onClick={() => void loadMore()}>
                                    {loadingMore ? t.common.loading : t.common.loadMore}
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </ReportSection>
        </TabStack>
    );
}
