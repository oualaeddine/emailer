import { useState } from 'react';
import { Badge, Tab, TabList, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { DomainTable } from '@/Components/Reports/DomainTable';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { ReportSection, TabStack } from '@/Components/Reports/ReportSection';
import { ViewContactsButton } from '@/Components/Reports/ViewContactsButton';
import { ratio, reportMissingText } from '@/Components/Reports/reportUi';
import type { BounceType, ReportDeliverability } from '@/Lib/types/reports';

const useStyles = makeStyles({
    empty: { color: tokens.colorNeutralForeground3 },
    scroll: { maxHeight: '420px', overflowY: 'auto' },
});

export interface DeliverabilityTabProps {
    data: ReportDeliverability;
    campaignId: string;
    campaignName: string;
    onError: (message: string) => void;
}

/**
 * **Délivrabilité** — délivrés, rebonds hard/soft (reason table), bloqués par
 * la liste noire, différés, en file, per SMTP account (our extra) and per
 * recipient domain.
 */
export function DeliverabilityTab({ data, campaignId, campaignName, onError }: DeliverabilityTabProps) {
    const styles = useStyles();
    const t = useText();
    const k = t.campaignReport.kpis;
    const d = t.campaignReport.deliverability;
    const s = data.globalStats;
    const [bounceFilter, setBounceFilter] = useState<BounceType | 'all'>('all');
    const reasons = data.bounce_reasons
        .filter((row) => bounceFilter === 'all' || row.type === bounceFilter)
        .sort((a, b) => b.count - a.count);

    return (
        <TabStack>
            <KpiRow>
                <KpiTile
                    label={k.delivered}
                    value={formatCount(s.delivered)}
                    rateText={formatRate(data.rates.delivery_rate)}
                    rate={data.rates.delivery_rate}
                    rateKey="delivery_rate"
                    hint={`${k.sent} : ${formatCount(s.sent)}`}
                />
                <KpiTile label={k.hardBounces} value={formatCount(s.hardBounces)} rateText={formatRate(ratio(s.hardBounces, s.sent))} />
                <KpiTile label={k.softBounces} value={formatCount(s.softBounces)} rateText={formatRate(ratio(s.softBounces, s.sent))} />
                <KpiTile label={d.blockedBySuppression} value={formatCount(data.blocked_by_suppression)} />
                <KpiTile label={k.deferred} value={formatCount(s.deferred)} />
                <KpiTile label={k.queued} value={formatCount(data.queued)} />
                <KpiTile
                    label={k.complaints}
                    value={formatCount(s.complaints)}
                    rateText={formatRate(data.rates.complaint_rate)}
                />
            </KpiRow>

            <ReportSection
                title={d.bounceReasons}
                actions={
                    <ViewContactsButton
                        campaignId={campaignId}
                        campaignName={campaignName}
                        metrics={['hard_bounced', 'soft_bounced']}
                        onError={onError}
                    />
                }
            >
                <TabList size="small" selectedValue={bounceFilter} onTabSelect={(_, value) => setBounceFilter(value.value as BounceType | 'all')}>
                    <Tab value="all">{t.common.all}</Tab>
                    <Tab value="hard">{d.hard}</Tab>
                    <Tab value="soft">{d.soft}</Tab>
                </TabList>
                {reasons.length === 0 ? (
                    <Text className={styles.empty}>{reportMissingText.noData}</Text>
                ) : (
                    <div className={styles.scroll}>
                        <ChartDataTable
                            caption={d.bounceReasons}
                            columns={[{ label: d.bounceType }, { label: d.reason }, { label: d.count, numeric: true }]}
                            rows={reasons.map((row) => [
                                <Badge key="type" appearance="tint" color={row.type === 'hard' ? 'danger' : 'warning'}>
                                    {row.type === 'hard' ? d.hard : d.soft}
                                </Badge>,
                                row.reason,
                                formatCount(row.count),
                            ])}
                        />
                    </div>
                )}
            </ReportSection>

            <ReportSection title={d.bySmtpAccount}>
                {data.by_smtp_account.length === 0 ? (
                    <Text className={styles.empty}>{reportMissingText.noData}</Text>
                ) : (
                    <ChartDataTable
                        caption={d.bySmtpAccount}
                        columns={[
                            { label: t.common.name },
                            { label: k.sent, numeric: true },
                            { label: k.delivered, numeric: true },
                            { label: t.campaignReport.rates.delivery_rate, numeric: true },
                            { label: k.hardBounces, numeric: true },
                            { label: k.softBounces, numeric: true },
                            { label: k.deferred, numeric: true },
                        ]}
                        rows={data.by_smtp_account.map((row) => [
                            row.name,
                            formatCount(row.sent),
                            formatCount(row.delivered),
                            formatRate(ratio(row.delivered, row.sent)),
                            formatCount(row.hardBounces),
                            formatCount(row.softBounces),
                            formatCount(row.deferred),
                        ])}
                    />
                )}
            </ReportSection>

            <ReportSection title={d.byDomain}>
                <DomainTable stats={data.statsByDomain} variant="deliverability" />
            </ReportSection>
        </TabStack>
    );
}
