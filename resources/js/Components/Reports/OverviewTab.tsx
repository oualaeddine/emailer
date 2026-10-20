import { Badge } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatDateTime, formatRate } from '@/Components/Campaigns/campaignUi';
import { AppleMppNote } from '@/Components/Reports/AppleMppNote';
import { AudienceSection } from '@/Components/Reports/AudienceSection';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { DeviceSplit } from '@/Components/Reports/DeviceSplit';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { TopLinksTable } from '@/Components/Reports/LinksTable';
import { ReportSection, SectionGrid, TabStack } from '@/Components/Reports/ReportSection';
import { TimeSeriesChart } from '@/Components/Reports/TimeSeriesChart';
import { TimelineList } from '@/Components/Reports/TimelineList';
import type { AbTestReport } from '@/Lib/types/campaigns';
import type { ReportOverview } from '@/Lib/types/reports';

function AbTestCard({ report }: { report: AbTestReport }) {
    const t = useText();
    const s = t.campaignSetup;
    const picked = report.picked_by === 'auto' ? t.campaignReport.pickedAuto : report.picked_by === 'manual' ? t.campaignReport.pickedManual : null;

    return (
        <ReportSection
            title={t.campaignReport.abTestCard}
            subtitle={[s.abPhases[report.phase], picked, report.picked_at ? formatDateTime(report.picked_at) : null].filter(Boolean).join(' · ')}
        >
            <ChartDataTable
                caption={t.campaignReport.abTestCard}
                columns={[
                    { label: s.previewPanel.variant },
                    { label: t.campaignReport.subject },
                    { label: t.campaignReport.kpis.sent, numeric: true },
                    { label: t.campaignReport.rates.open_rate, numeric: true },
                    { label: t.campaignReport.rates.click_rate, numeric: true },
                    { label: s.abTest.winner },
                ]}
                rows={report.variants.map((variant) => [
                    variant.label === 'A' ? s.abTest.variantA : s.abTest.variantB,
                    variant.subject,
                    formatCount(variant.globalStats.sent),
                    formatRate(variant.rates.open_rate),
                    formatRate(variant.rates.click_rate),
                    variant.variant_id === report.winner_variant_id ? (
                        <Badge appearance="tint" color="success">
                            {s.abTest.winner}
                        </Badge>
                    ) : (
                        t.common.emptyValue
                    ),
                ])}
            />
        </ReportSection>
    );
}

/**
 * **Vue d'ensemble** — KPI tiles (Envoyés, Délivrés, Ouvertures uniques, Clics
 * uniques, Désinscriptions, Plaintes, Rebonds) each vs the account average,
 * Apple MPP note, opens/clicks over time, campaign audience, timeline, top
 * links and device split (docs/43 §43.4.15).
 */
export function OverviewTab({ data }: { data: ReportOverview }) {
    const t = useText();
    const k = t.campaignReport.kpis;
    const s = data.globalStats;
    const bounces = s.hardBounces + s.softBounces;

    return (
        <TabStack>
            <KpiRow>
                <KpiTile
                    label={k.sent}
                    value={formatCount(s.sent)}
                    hint={data.remaining > 0 ? `${k.remaining} : ${formatCount(data.remaining)}` : undefined}
                />
                <KpiTile
                    label={k.delivered}
                    value={formatCount(s.delivered)}
                    rateText={formatRate(data.rates.delivery_rate)}
                    rate={data.rates.delivery_rate}
                    average={data.averages.delivery_rate}
                    rateKey="delivery_rate"
                />
                <KpiTile
                    label={k.uniqueOpens}
                    value={formatCount(s.uniqueViews)}
                    rateText={formatRate(data.rates.open_rate)}
                    rate={data.rates.open_rate}
                    average={data.averages.open_rate}
                    rateKey="open_rate"
                />
                <KpiTile
                    label={k.uniqueClicks}
                    value={formatCount(s.uniqueClicks)}
                    rateText={formatRate(data.rates.click_rate)}
                    rate={data.rates.click_rate}
                    average={data.averages.click_rate}
                    rateKey="click_rate"
                    hint={data.mirrorClick > 0 ? `${k.mirrorClicks} : ${formatCount(data.mirrorClick)}` : undefined}
                />
                <KpiTile
                    label={k.unsubscriptions}
                    value={formatCount(s.unsubscriptions)}
                    rateText={formatRate(data.rates.unsubscribe_rate)}
                    rate={data.rates.unsubscribe_rate}
                    average={data.averages.unsubscribe_rate}
                    rateKey="unsubscribe_rate"
                />
                <KpiTile
                    label={k.complaints}
                    value={formatCount(s.complaints)}
                    rateText={formatRate(data.rates.complaint_rate)}
                    rate={data.rates.complaint_rate}
                    average={data.averages.complaint_rate}
                    rateKey="complaint_rate"
                />
                <KpiTile
                    label={k.bounces}
                    value={formatCount(bounces)}
                    rateText={formatRate(data.rates.bounce_rate)}
                    rate={data.rates.bounce_rate}
                    average={data.averages.bounce_rate}
                    rateKey="bounce_rate"
                    hint={`${k.hardBounces} : ${formatCount(s.hardBounces)} · ${k.softBounces} : ${formatCount(s.softBounces)}`}
                />
            </KpiRow>

            {data.apple_mpp_note && (
                <AppleMppNote appleMppOpens={s.appleMppOpens} uniqueViews={s.uniqueViews} trackableViews={s.trackableViews} />
            )}

            {data.ab_test && <AbTestCard report={data.ab_test} />}

            <ReportSection title={t.campaignReport.performanceOverTime}>
                <TimeSeriesChart
                    timeseries={data.timeseries}
                    caption={t.campaignReport.performanceOverTime}
                    series={[
                        { key: 'uniqueViews', label: k.uniqueOpens },
                        { key: 'uniqueClicks', label: k.uniqueClicks },
                    ]}
                />
            </ReportSection>

            <ReportSection title={t.campaignReport.audience}>
                <AudienceSection audience={data.audience} campaignStats={data.campaignStats} />
            </ReportSection>

            <SectionGrid>
                <ReportSection title={t.campaignReport.timeline}>
                    <TimelineList items={data.timeline} />
                </ReportSection>
                <ReportSection title={t.campaignReport.devices.title} subtitle={k.uniqueOpens}>
                    <DeviceSplit stats={data.statsByDevice} metric="uniqueViews" />
                </ReportSection>
            </SectionGrid>

            <ReportSection title={t.campaignReport.topLinks}>
                <TopLinksTable links={data.links} />
            </ReportSection>
        </TabStack>
    );
}
