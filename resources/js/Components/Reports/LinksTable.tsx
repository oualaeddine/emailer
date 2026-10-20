import { useRef, type ReactNode } from 'react';
import {
    Link,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { reportMissingText, useChartPalette } from '@/Components/Reports/reportUi';
import type { ReportLinkRow } from '@/Lib/types/reports';

const useStyles = makeStyles({
    urlCell: {
        display: 'flex',
        flexDirection: 'column',
        minWidth: 0,
        maxWidth: '460px',
        paddingTop: tokens.spacingVerticalXS,
        paddingBottom: tokens.spacingVerticalXS,
    },
    url: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    label: {
        color: tokens.colorNeutralForeground3,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
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
    share: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: '150px',
    },
    track: {
        position: 'relative',
        flexGrow: 1,
        height: '8px',
        borderRadius: '4px',
        overflow: 'hidden',
    },
    fill: {
        position: 'absolute',
        left: 0,
        top: 0,
        bottom: 0,
        borderRadius: '0 4px 4px 0',
    },
    shareText: {
        minWidth: '52px',
        textAlign: 'right',
        fontVariantNumeric: 'tabular-nums',
    },
    empty: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface LinksTableProps {
    links: ReportLinkRow[];
    /** Extra cell per row (e.g. per-link "Voir les contacts"). */
    renderActions?: (link: ReportLinkRow) => ReactNode;
    caption: string;
}

/** Per-URL click table: clics uniques, totaux, part des clics (docs/43 §43.4.15 Clics). */
export function LinksTable({ links, renderActions, caption }: LinksTableProps) {
    const styles = useStyles();
    const t = useText();
    const ref = useRef<HTMLDivElement>(null);
    const palette = useChartPalette(ref);
    const c = t.campaignReport.clicks;
    const sorted = [...links].sort((a, b) => b.unique_clicks - a.unique_clicks);

    if (sorted.length === 0) {
        return <Text className={styles.empty}>{reportMissingText.noData}</Text>;
    }

    return (
        <div ref={ref}>
            <Table size="small" aria-label={caption}>
                <TableHeader>
                    <TableRow>
                        <TableHeaderCell>{c.url}</TableHeaderCell>
                        <TableHeaderCell className={styles.numeric}>{c.uniqueClicks}</TableHeaderCell>
                        <TableHeaderCell className={styles.numeric}>{c.totalClicks}</TableHeaderCell>
                        <TableHeaderCell>{c.share}</TableHeaderCell>
                        {renderActions && <TableHeaderCell>{t.common.actions}</TableHeaderCell>}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sorted.map((link) => (
                        <TableRow key={link.id}>
                            <TableCell>
                                <div className={styles.urlCell}>
                                    <Link href={link.url} target="_blank" rel="noopener noreferrer" className={styles.url} title={link.url}>
                                        {link.url}
                                    </Link>
                                    {link.label && (
                                        <Text size={200} className={styles.label}>
                                            {link.label}
                                        </Text>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className={styles.numeric}>{formatCount(link.unique_clicks)}</TableCell>
                            <TableCell className={styles.numeric}>{formatCount(link.total_clicks)}</TableCell>
                            <TableCell>
                                <span className={styles.share}>
                                    <span className={styles.track} style={{ backgroundColor: `${palette.series[0]}26` }} aria-hidden="true">
                                        <span
                                            className={styles.fill}
                                            style={{ width: `${Math.min(100, Math.max(0, link.share * 100))}%`, backgroundColor: palette.series[0] }}
                                        />
                                    </span>
                                    <span className={styles.shareText}>{formatRate(link.share)}</span>
                                </span>
                            </TableCell>
                            {renderActions && <TableCell>{renderActions(link)}</TableCell>}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

/** Overview "Liens les plus cliqués" — top 10 by unique clicks. */
export function TopLinksTable({ links }: { links: ReportLinkRow[] }) {
    const t = useText();

    return <LinksTable links={links.slice(0, 10)} caption={t.campaignReport.topLinks} />;
}
