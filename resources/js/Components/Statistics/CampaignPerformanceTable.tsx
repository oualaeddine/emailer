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
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { StatisticsCampaignRow, StatisticsCampaignSortField } from '@/Lib/types/statistics';
import { formatNumber, formatRate } from '@/Components/Statistics/KpiTiles';

const useStyles = makeStyles({
    scroll: { overflowX: 'auto', width: '100%' },
    num: { textAlign: 'right', justifyContent: 'flex-end' },
});

export type CampaignSort = StatisticsCampaignSortField | `-${StatisticsCampaignSortField}`;

interface Props {
    rows: StatisticsCampaignRow[];
    sort: CampaignSort;
    onSortChange: (sort: CampaignSort) => void;
}

export function CampaignPerformanceTable({ rows, sort, onSortChange }: Props) {
    const styles = useStyles();
    const t = useText();
    const field = sort.replace(/^-/, '') as StatisticsCampaignSortField;
    const desc = sort.startsWith('-');

    function toggle(f: StatisticsCampaignSortField) {
        onSortChange(f === field && desc ? f : (`-${f}` as CampaignSort));
    }

    const header = (f: StatisticsCampaignSortField, label: string, numeric = false) => (
        <TableHeaderCell
            className={numeric ? styles.num : undefined}
            sortDirection={f === field ? (desc ? 'descending' : 'ascending') : undefined}
            onClick={() => toggle(f)}
        >
            {label}
        </TableHeaderCell>
    );

    if (rows.length === 0) {
        return <Text>{t.statistics.noData}</Text>;
    }

    return (
        <div className={styles.scroll}>
            <Table aria-label={t.statistics.campaignPerformance} sortable style={{ minWidth: 640 }}>
                <TableHeader>
                    <TableRow>
                        {header('name', t.statistics.campaign)}
                        {header('sent_at', t.statistics.sentAt)}
                        {header('sent', t.statistics.kpis.sent, true)}
                        {header('open_rate', t.reporting.openRate, true)}
                        {header('click_rate', t.reporting.clickRate, true)}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.campaign_id}>
                            <TableCell>
                                <Link href={`/campaigns/${row.campaign_id}/report`}>{row.name}</Link>
                            </TableCell>
                            <TableCell>{new Date(row.sent_at).toLocaleDateString('fr-FR')}</TableCell>
                            <TableCell className={styles.num}>{formatNumber(row.globalStats.sent)}</TableCell>
                            <TableCell className={styles.num}>{formatRate(row.rates.open_rate)}</TableCell>
                            <TableCell className={styles.num}>{formatRate(row.rates.click_rate)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
