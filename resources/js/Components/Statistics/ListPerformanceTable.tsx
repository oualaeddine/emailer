import { Table, TableBody, TableCell, TableHeader, TableHeaderCell, TableRow, Text, makeStyles } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { StatisticsListRow } from '@/Lib/types/statistics';
import { formatNumber, formatRate } from '@/Components/Statistics/KpiTiles';

const useStyles = makeStyles({
    scroll: { overflowX: 'auto', width: '100%' },
    num: { textAlign: 'right', justifyContent: 'flex-end' },
});

export function ListPerformanceTable({ rows }: { rows: StatisticsListRow[] }) {
    const styles = useStyles();
    const t = useText();

    if (rows.length === 0) {
        return <Text>{t.statistics.noData}</Text>;
    }

    return (
        <div className={styles.scroll}>
            <Table aria-label={t.statistics.listPerformance} style={{ minWidth: 560 }}>
                <TableHeader>
                    <TableRow>
                        <TableHeaderCell>{t.statistics.list}</TableHeaderCell>
                        <TableHeaderCell className={styles.num}>{t.statistics.kpis.sent}</TableHeaderCell>
                        <TableHeaderCell className={styles.num}>{t.reporting.openRate}</TableHeaderCell>
                        <TableHeaderCell className={styles.num}>{t.reporting.clickRate}</TableHeaderCell>
                        <TableHeaderCell className={styles.num}>{t.statistics.kpis.unsubscriptions}</TableHeaderCell>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.list_id}>
                            <TableCell>{row.name}</TableCell>
                            <TableCell className={styles.num}>{formatNumber(row.globalStats.sent)}</TableCell>
                            <TableCell className={styles.num}>{formatRate(row.rates.open_rate)}</TableCell>
                            <TableCell className={styles.num}>{formatRate(row.rates.click_rate)}</TableCell>
                            <TableCell className={styles.num}>{formatRate(row.rates.unsubscribe_rate)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
