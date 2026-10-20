import { useState } from 'react';
import { Badge, Button, MessageBar, MessageBarBody, Spinner } from '@fluentui/react-components';
import { TrophyRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { pickAbWinner } from '@/Lib/api/abTest';
import type { AbTestReport } from '@/Lib/types/campaigns';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { ReportSection } from '@/Components/Reports/ReportSection';
import { apiErrorMessage, formatCount, formatDateTime, formatRate } from '@/Components/Campaigns/campaignUi';

export interface AbTestReportCardProps {
    report: AbTestReport;
    /** Campaign uuid — enables the manual pick while the test is running. */
    campaignId?: string;
}

/**
 * Report card **Résultats du test A/B** (docs/43 §43.4.15, WP-80): phase,
 * winner criterion and pick details, per-version sent / unique opens /
 * unique clicks with their rates, and a manual *Choisir le gagnant* action
 * during the test phase (`POST /campaigns/{id}/ab-test/winner`).
 */
export function AbTestReportCard({ report: initial, campaignId }: AbTestReportCardProps) {
    const t = useText();
    const s = t.campaignSetup;
    const k = t.campaignReport.kpis;
    const [report, setReport] = useState<AbTestReport>(initial);
    const [picking, setPicking] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const canPick = report.phase === 'testing' && report.winner_variant_id === null && campaignId !== undefined;
    const picked =
        report.picked_by === 'auto' ? t.campaignReport.pickedAuto : report.picked_by === 'manual' ? t.campaignReport.pickedManual : null;

    async function pick(variantId: string) {
        if (!campaignId) {
            return;
        }
        setPicking(variantId);
        setError(null);
        try {
            const updated = await pickAbWinner(campaignId, variantId);
            setReport((current) => ({
                ...current,
                phase: updated.ab_test?.phase ?? 'winner_picked',
                winner_variant_id: updated.ab_test?.winner_variant_id ?? variantId,
                picked_by: 'manual',
                picked_at: new Date().toISOString(),
            }));
        } catch (err) {
            setError(apiErrorMessage(err));
        } finally {
            setPicking(null);
        }
    }

    return (
        <ReportSection
            title={t.campaignReport.abTestCard}
            subtitle={[
                s.abPhases[report.phase],
                `${s.abTest.winnerMetric} : ${s.abWinnerMetrics[report.config.winner_metric]}`,
                picked,
                report.picked_at ? formatDateTime(report.picked_at) : null,
            ]
                .filter(Boolean)
                .join(' · ')}
        >
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <ChartDataTable
                caption={t.campaignReport.abTestCard}
                columns={[
                    { label: s.previewPanel.variant },
                    { label: t.campaignReport.subject },
                    { label: k.sent, numeric: true },
                    { label: k.uniqueOpens, numeric: true },
                    { label: t.campaignReport.rates.open_rate, numeric: true },
                    { label: k.uniqueClicks, numeric: true },
                    { label: t.campaignReport.rates.click_rate, numeric: true },
                    { label: s.abTest.winner },
                ]}
                rows={report.variants.map((variant) => [
                    variant.label === 'A' ? s.abTest.variantA : s.abTest.variantB,
                    variant.subject,
                    formatCount(variant.globalStats.sent),
                    formatCount(variant.globalStats.uniqueViews),
                    formatRate(variant.rates.open_rate),
                    formatCount(variant.globalStats.uniqueClicks),
                    formatRate(variant.rates.click_rate),
                    variant.variant_id === report.winner_variant_id ? (
                        <Badge appearance="tint" color="success" icon={<TrophyRegular />}>
                            {s.abTest.winner}
                        </Badge>
                    ) : canPick ? (
                        <Button
                            size="small"
                            disabled={picking !== null}
                            icon={picking === variant.variant_id ? <Spinner size="tiny" /> : undefined}
                            onClick={() => void pick(variant.variant_id)}
                        >
                            {s.abTest.pickWinner}
                        </Button>
                    ) : (
                        t.common.emptyValue
                    ),
                ])}
            />
        </ReportSection>
    );
}
