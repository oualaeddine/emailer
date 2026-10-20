import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { ClickMap } from '@/Components/Reports/ClickMap';
import { DeviceSplit } from '@/Components/Reports/DeviceSplit';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { LinksTable } from '@/Components/Reports/LinksTable';
import { ReportSection, TabStack } from '@/Components/Reports/ReportSection';
import { ViewContactsButton } from '@/Components/Reports/ViewContactsButton';
import type { ReportClicks } from '@/Lib/types/reports';

export interface ClicksTabProps {
    data: ReportClicks;
    campaignId: string;
    campaignName: string;
    html: string | null;
    htmlLoading: boolean;
    onError: (message: string) => void;
}

/**
 * **Clics** — per-URL table (unique, total, share), **Carte des clics**, by
 * device, **Voir les contacts** (clicked / not clicked, or per link).
 */
export function ClicksTab({ data, campaignId, campaignName, html, htmlLoading, onError }: ClicksTabProps) {
    const t = useText();
    const k = t.campaignReport.kpis;
    const c = t.campaignReport.clicks;

    return (
        <TabStack>
            <KpiRow>
                <KpiTile
                    label={k.uniqueClicks}
                    value={formatCount(data.uniqueClicks)}
                    rateText={formatRate(data.click_rate)}
                    rate={data.click_rate}
                    average={data.averages.click_rate}
                    rateKey="click_rate"
                />
                <KpiTile label={k.totalClicks} value={formatCount(data.total_clicks)} />
                <KpiTile label={k.clickers} value={formatCount(data.clickers)} />
                <KpiTile
                    label={t.campaignReport.rates.click_to_open_rate}
                    value={formatRate(data.click_to_open_rate)}
                    rate={data.click_to_open_rate}
                    average={data.averages.click_to_open_rate}
                    rateKey="click_to_open_rate"
                />
            </KpiRow>

            <ReportSection
                title={c.perLink}
                actions={
                    <ViewContactsButton
                        campaignId={campaignId}
                        campaignName={campaignName}
                        metrics={['clicked', 'not_clicked']}
                        onError={onError}
                    />
                }
            >
                <LinksTable
                    links={data.links}
                    caption={c.perLink}
                    renderActions={(link) => (
                        <ViewContactsButton
                            campaignId={campaignId}
                            campaignName={campaignName}
                            metrics={['clicked']}
                            linkId={link.link_id}
                            linkLabel={link.label || link.url}
                            appearance="subtle"
                            onError={onError}
                        />
                    )}
                />
            </ReportSection>

            <ReportSection title={c.clickMap}>
                <ClickMap html={html} links={data.links} loading={htmlLoading} />
            </ReportSection>

            <ReportSection title={c.byDevice} subtitle={k.uniqueClicks}>
                <DeviceSplit stats={data.statsByDevice} metric="uniqueClicks" />
            </ReportSection>
        </TabStack>
    );
}
