import { Badge, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate, formatText } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { ratio } from '@/Components/Reports/reportUi';
import type { AudienceTargetRef, BrevoCampaignStatsEntry, ReportAudience } from '@/Lib/types/reports';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    groups: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
        gap: tokens.spacingHorizontalL,
    },
    group: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
    },
    chips: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalXS,
    },
    caption: {
        color: tokens.colorNeutralForeground3,
    },
});

function TargetChips({ targets, kind }: { targets: AudienceTargetRef[]; kind: 'list' | 'segment' }) {
    const t = useText();

    return (
        <>
            {targets.map((target) => (
                <Badge key={`${kind}-${target.id}`} appearance="tint" color={kind === 'list' ? 'brand' : 'informative'} shape="rounded">
                    {target.name} · {formatText(t.campaignSetup.recipients.contactsCount, target.contact_count)}
                </Badge>
            ))}
        </>
    );
}

export interface AudienceSectionProps {
    audience: ReportAudience;
    campaignStats: BrevoCampaignStatsEntry[];
}

/**
 * **Audience de la campagne** — targeted / excluded lists and segments, then
 * Brevo's per-list `campaignStats` (sent, delivered, opens, clicks…).
 */
export function AudienceSection({ audience, campaignStats }: AudienceSectionProps) {
    const styles = useStyles();
    const t = useText();
    const k = t.campaignReport.kpis;
    const included = audience.lists.length + audience.segments.length;
    const excluded = audience.exclusion_lists.length + audience.excluded_segments.length;

    return (
        <div className={styles.root}>
            <Text>
                {t.campaigns.audienceSize} : <strong>{formatCount(audience.recipients)}</strong>
            </Text>
            <div className={styles.groups}>
                <div className={styles.group}>
                    <Text weight="semibold">{t.campaignReport.audienceIncluded}</Text>
                    <div className={styles.chips}>
                        {included === 0 ? (
                            <Text className={styles.caption}>{t.common.none}</Text>
                        ) : (
                            <>
                                <TargetChips targets={audience.lists} kind="list" />
                                <TargetChips targets={audience.segments} kind="segment" />
                            </>
                        )}
                    </div>
                </div>
                <div className={styles.group}>
                    <Text weight="semibold">{t.campaignReport.audienceExcluded}</Text>
                    <div className={styles.chips}>
                        {excluded === 0 ? (
                            <Text className={styles.caption}>{t.common.none}</Text>
                        ) : (
                            <>
                                <TargetChips targets={audience.exclusion_lists} kind="list" />
                                <TargetChips targets={audience.excluded_segments} kind="segment" />
                            </>
                        )}
                    </div>
                </div>
            </div>
            {campaignStats.length > 0 && (
                <ChartDataTable
                    caption={t.campaignReport.audience}
                    columns={[
                        { label: t.common.name },
                        { label: t.common.type },
                        { label: k.sent, numeric: true },
                        { label: k.delivered, numeric: true },
                        { label: k.uniqueOpens, numeric: true },
                        { label: t.campaignReport.rates.open_rate, numeric: true },
                        { label: k.uniqueClicks, numeric: true },
                        { label: t.campaignReport.rates.click_rate, numeric: true },
                        { label: k.unsubscriptions, numeric: true },
                        { label: k.bounces, numeric: true },
                    ]}
                    rows={campaignStats.map((entry) => [
                        entry.name,
                        entry.segmentId ? t.campaigns.targetTypeSegment : t.campaigns.targetTypeList,
                        formatCount(entry.sent),
                        formatCount(entry.delivered),
                        formatCount(entry.uniqueViews),
                        formatRate(ratio(entry.uniqueViews, entry.delivered)),
                        formatCount(entry.uniqueClicks),
                        formatRate(ratio(entry.uniqueClicks, entry.delivered)),
                        formatCount(entry.unsubscriptions),
                        formatCount(entry.hardBounces + entry.softBounces),
                    ])}
                />
            )}
        </div>
    );
}
