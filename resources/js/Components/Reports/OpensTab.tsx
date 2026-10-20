import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { AppleMppNote } from '@/Components/Reports/AppleMppNote';
import { BreakdownTable, CountryTable } from '@/Components/Reports/CountryTable';
import { DeviceSplit } from '@/Components/Reports/DeviceSplit';
import { DomainTable } from '@/Components/Reports/DomainTable';
import { HourOfDayChart } from '@/Components/Reports/HourOfDayChart';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { ReportSection, SectionGrid, TabStack } from '@/Components/Reports/ReportSection';
import { TimeSeriesChart } from '@/Components/Reports/TimeSeriesChart';
import { ViewContactsButton } from '@/Components/Reports/ViewContactsButton';
import { formatPercentValue, ratio, reportMissingText } from '@/Components/Reports/reportUi';
import type { ReportOpens } from '@/Lib/types/reports';

export interface OpensTabProps {
    data: ReportOpens;
    campaignId: string;
    campaignName: string;
    onError: (message: string) => void;
}

/**
 * **Ouvertures** — unique vs total opens over time, by hour of day, by domain,
 * by device / mail client (parsed UA), by country (GeoIP), Apple MPP note and
 * **Voir les contacts** (opened / not opened → segment).
 */
export function OpensTab({ data, campaignId, campaignName, onError }: OpensTabProps) {
    const t = useText();
    const k = t.campaignReport.kpis;
    const o = t.campaignReport.opens;
    const openRatio = data.opensRate / 100;

    return (
        <TabStack>
            <KpiRow>
                <KpiTile
                    label={k.uniqueOpens}
                    value={formatCount(data.uniqueViews)}
                    rateText={formatPercentValue(data.opensRate)}
                    rate={openRatio}
                    average={data.averages.open_rate}
                    rateKey="open_rate"
                />
                <KpiTile label={k.totalOpens} value={formatCount(data.viewed)} />
                <KpiTile
                    label={k.trackableOpens}
                    value={formatCount(data.trackableViews)}
                    rateText={formatRate(ratio(data.trackableViews, data.uniqueViews))}
                />
                <KpiTile
                    label={k.appleMppOpens}
                    value={formatCount(data.appleMppOpens)}
                    rateText={formatRate(ratio(data.appleMppOpens, data.uniqueViews))}
                />
            </KpiRow>

            {data.appleMppOpens > 0 && (
                <AppleMppNote appleMppOpens={data.appleMppOpens} uniqueViews={data.uniqueViews} trackableViews={data.trackableViews} />
            )}

            <ReportSection
                title={o.uniqueVsTotal}
                actions={
                    <ViewContactsButton
                        campaignId={campaignId}
                        campaignName={campaignName}
                        metrics={['opened', 'not_opened']}
                        onError={onError}
                    />
                }
            >
                <TimeSeriesChart
                    timeseries={data.timeseries}
                    caption={o.uniqueVsTotal}
                    series={[
                        { key: 'uniqueViews', label: k.uniqueOpens },
                        { key: 'viewed', label: k.totalOpens },
                    ]}
                />
            </ReportSection>

            <ReportSection title={o.byHour} subtitle={k.uniqueOpens}>
                <HourOfDayChart rows={data.by_hour_of_day} label={o.byHour} />
            </ReportSection>

            <SectionGrid>
                <ReportSection title={o.byDevice} subtitle={k.uniqueOpens}>
                    <DeviceSplit stats={data.statsByDevice} metric="uniqueViews" />
                </ReportSection>
                <ReportSection title={o.byClient}>
                    <BreakdownTable stats={data.statsByBrowser} dimension={reportMissingText.client} caption={o.byClient} />
                </ReportSection>
            </SectionGrid>

            <ReportSection title={o.byDomain}>
                <DomainTable stats={data.statsByDomain} variant="engagement" />
            </ReportSection>

            <ReportSection title={o.byCountry}>
                <CountryTable stats={data.statsByCountry} />
            </ReportSection>
        </TabStack>
    );
}
