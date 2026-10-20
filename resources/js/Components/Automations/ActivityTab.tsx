import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Text,
    Title3,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { ArrowExitRegular } from '@fluentui/react-icons';
import { apiErrorMessage, formatCount, formatDateTime, formatRate, formatText } from '@/Components/Campaigns/campaignUi';
import { KpiRow, KpiTile } from '@/Components/Reports/KpiTile';
import { useText } from '@/Hooks/useText';
import { exitAutomationRun, fetchAutomationActivity, fetchAutomationRuns, type AutomationActivityQuery } from '@/Lib/api/automations';
import type { AutomationActivity, AutomationEmailActivity, AutomationRun, AutomationRunStatus } from '@/Lib/types/automations';
import type { Uuid } from '@/Lib/types/common';
import { numberSteps, type DraftStep } from './graph';

const PERIODS = [0, 7, 30, 90] as const;
type Period = (typeof PERIODS)[number];
const RUN_STATUSES: AutomationRunStatus[] = ['active', 'waiting', 'completed', 'exited', 'failed'];
const ALL = '__all__';
const RUNS_PAGE_SIZE = 25;

const RUN_STATUS_COLOR: Record<AutomationRunStatus, 'brand' | 'informative' | 'success' | 'subtle' | 'danger'> = {
    active: 'brand',
    waiting: 'informative',
    completed: 'success',
    exited: 'subtle',
    failed: 'danger',
};

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalL,
    },
    toolbar: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
    },
    section: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalL,
        minWidth: 0,
    },
    scroll: {
        overflowX: 'auto',
    },
    emailsGrid: {
        minWidth: '760px',
    },
    runsGrid: {
        minWidth: '900px',
    },
    numeric: {
        fontVariantNumeric: 'tabular-nums',
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    error: {
        color: tokens.colorPaletteRedForeground1,
    },
    stepCell: {
        display: 'flex',
        flexDirection: 'column',
        minWidth: 0,
    },
    footer: {
        display: 'flex',
        justifyContent: 'center',
    },
    dropdown: {
        minWidth: '200px',
    },
});

function isoDate(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function periodQuery(period: Period): AutomationActivityQuery {
    if (period === 0) return {};
    const to = new Date();
    const from = new Date(to.getTime() - (period - 1) * 86_400_000);

    return { date_from: isoDate(from), date_to: isoDate(to) };
}

export interface ActivityTabProps {
    automationId: Uuid;
    /** Saved workflow, to name the current step of each run. */
    steps: DraftStep[];
}

/**
 * "Activité" tab (§45.7.5 GET …/activity and …/runs): started / in progress /
 * finished / exited KPIs, per-email open & click rates, and the runs list
 * with status and current step (a contact can be made to exit).
 */
export function ActivityTab({ automationId, steps }: ActivityTabProps) {
    const styles = useStyles();
    const t = useText();
    const [period, setPeriod] = useState<Period>(0);
    const [activity, setActivity] = useState<AutomationActivity | null>(null);
    const [activityLoading, setActivityLoading] = useState(true);
    const [activityError, setActivityError] = useState<string | null>(null);

    const [runStatus, setRunStatus] = useState<AutomationRunStatus | null>(null);
    const [runs, setRuns] = useState<AutomationRun[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [runsLoading, setRunsLoading] = useState(true);
    const [runsError, setRunsError] = useState<string | null>(null);
    const [exitTarget, setExitTarget] = useState<AutomationRun | null>(null);
    const [exiting, setExiting] = useState(false);
    const runsRequest = useRef(0);

    const stepsById = useMemo(() => {
        const map = new Map<string, { number: number; step: DraftStep }>();
        numberSteps(steps).forEach((entry) => {
            if (entry.step.id) map.set(entry.step.id, entry);
        });

        return map;
    }, [steps]);

    useEffect(() => {
        let cancelled = false;
        setActivityLoading(true);
        setActivityError(null);
        fetchAutomationActivity(automationId, periodQuery(period))
            .then((result) => {
                if (!cancelled) setActivity(result);
            })
            .catch((error) => {
                if (!cancelled) setActivityError(apiErrorMessage(error));
            })
            .finally(() => {
                if (!cancelled) setActivityLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [automationId, period]);

    const loadRuns = useCallback(
        async (next: string | null) => {
            const requestId = ++runsRequest.current;
            setRunsLoading(true);
            setRunsError(null);
            try {
                const page = await fetchAutomationRuns(automationId, {
                    ...(runStatus ? { status: runStatus } : {}),
                    ...(next ? { cursor: next } : {}),
                    per_page: RUNS_PAGE_SIZE,
                });
                if (requestId !== runsRequest.current) return;
                setRuns((current) => (next ? [...current, ...page.data] : page.data));
                setCursor(page.meta.next_cursor ?? null);
            } catch (error) {
                if (requestId !== runsRequest.current) return;
                setRunsError(apiErrorMessage(error));
                if (!next) setRuns([]);
                setCursor(null);
            } finally {
                if (requestId === runsRequest.current) setRunsLoading(false);
            }
        },
        [automationId, runStatus],
    );

    useEffect(() => {
        void loadRuns(null);
    }, [loadRuns]);

    async function confirmExit() {
        if (!exitTarget) return;
        setExiting(true);
        try {
            const updated = await exitAutomationRun(automationId, exitTarget.id);
            setRuns((current) => current.map((run) => (run.id === updated.id ? updated : run)));
            setExitTarget(null);
        } catch (error) {
            setRunsError(apiErrorMessage(error));
            setExitTarget(null);
        } finally {
            setExiting(false);
        }
    }

    const periodLabel = (value: Period) => (value === 0 ? t.automations.editor.activity.periodAll : formatText(t.automations.editor.activity.periodDays, value));

    const emailColumns: TableColumnDefinition<AutomationEmailActivity>[] = [
        createTableColumn({
            columnId: 'template',
            renderHeaderCell: () => t.automations.template,
            renderCell: (row) => {
                const entry = stepsById.get(row.step_id);

                return (
                    <div className={styles.stepCell}>
                        <Text weight="semibold">{row.template_name}</Text>
                        {entry && (
                            <Text size={200} className={styles.muted}>
                                {formatText(t.automations.editor.step.number, entry.number)}
                            </Text>
                        )}
                    </div>
                );
            },
        }),
        createTableColumn({
            columnId: 'sent',
            renderHeaderCell: () => t.automations.editor.activity.sent,
            renderCell: (row) => <span className={styles.numeric}>{formatCount(row.globalStats.sent)}</span>,
        }),
        createTableColumn({
            columnId: 'delivered',
            renderHeaderCell: () => t.automations.editor.activity.delivered,
            renderCell: (row) => <span className={styles.numeric}>{formatCount(row.globalStats.delivered)}</span>,
        }),
        createTableColumn({
            columnId: 'opens',
            renderHeaderCell: () => t.automations.editor.activity.openRate,
            renderCell: (row) => (
                <span className={styles.numeric}>
                    {formatRate(row.rates.open_rate)} <span className={styles.muted}>({formatCount(row.globalStats.uniqueViews)})</span>
                </span>
            ),
        }),
        createTableColumn({
            columnId: 'clicks',
            renderHeaderCell: () => t.automations.editor.activity.clickRate,
            renderCell: (row) => (
                <span className={styles.numeric}>
                    {formatRate(row.rates.click_rate)} <span className={styles.muted}>({formatCount(row.globalStats.uniqueClicks)})</span>
                </span>
            ),
        }),
        createTableColumn({
            columnId: 'unsubscribes',
            renderHeaderCell: () => t.automations.editor.activity.unsubscribeRate,
            renderCell: (row) => <span className={styles.numeric}>{formatRate(row.rates.unsubscribe_rate)}</span>,
        }),
    ];

    const runColumns: TableColumnDefinition<AutomationRun>[] = [
        createTableColumn({
            columnId: 'contact',
            renderHeaderCell: () => t.automations.editor.activity.contact,
            renderCell: (run) => <Text truncate>{run.recipient.email}</Text>,
        }),
        createTableColumn({
            columnId: 'status',
            renderHeaderCell: () => t.common.status,
            renderCell: (run) => (
                <div className={styles.stepCell}>
                    <Badge appearance="tint" color={RUN_STATUS_COLOR[run.status]}>
                        {t.automations.runStatus[run.status]}
                    </Badge>
                    {run.error && (
                        <Text size={200} className={styles.error}>
                            {run.error}
                        </Text>
                    )}
                </div>
            ),
        }),
        createTableColumn({
            columnId: 'step',
            renderHeaderCell: () => t.automations.editor.activity.currentStep,
            renderCell: (run) => {
                if (!run.current_step_id) return t.common.emptyValue;
                const entry = stepsById.get(run.current_step_id);

                return entry ? `${formatText(t.automations.editor.step.number, entry.number)} · ${t.automations.steps[entry.step.type]}` : t.automations.editor.activity.unknownStep;
            },
        }),
        createTableColumn({
            columnId: 'started',
            renderHeaderCell: () => t.automations.editor.activity.startedAt,
            renderCell: (run) => formatDateTime(run.started_at),
        }),
        createTableColumn({
            columnId: 'next',
            renderHeaderCell: () => t.automations.editor.activity.nextRunAt,
            renderCell: (run) => formatDateTime(run.next_run_at),
        }),
        createTableColumn({
            columnId: 'finished',
            renderHeaderCell: () => t.automations.editor.activity.finishedAt,
            renderCell: (run) => formatDateTime(run.finished_at),
        }),
        createTableColumn({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (run) =>
                run.status === 'active' || run.status === 'waiting' ? (
                    <Button size="small" appearance="subtle" icon={<ArrowExitRegular />} onClick={() => setExitTarget(run)}>
                        {t.automations.exitRun}
                    </Button>
                ) : null,
        }),
    ];

    const completionRate = activity && activity.started > 0 ? activity.completed / activity.started : null;

    return (
        <div className={styles.root}>
            <div className={styles.toolbar}>
                <Title3 as="h2">{t.automations.tabs.activity}</Title3>
                <Dropdown
                    className={styles.dropdown}
                    aria-label={t.common.period}
                    selectedOptions={[String(period)]}
                    value={periodLabel(period)}
                    onOptionSelect={(_, data) => setPeriod(Number(data.optionValue) as Period)}
                >
                    {PERIODS.map((value) => (
                        <Option key={value} value={String(value)}>
                            {periodLabel(value)}
                        </Option>
                    ))}
                </Dropdown>
            </div>

            {activityError && (
                <MessageBar intent="error">
                    <MessageBarBody>{activityError}</MessageBarBody>
                </MessageBar>
            )}
            {activityLoading && !activity ? (
                <Spinner label={t.common.loading} />
            ) : activity ? (
                <KpiRow>
                    <KpiTile label={t.automations.activity.started} value={formatCount(activity.started)} />
                    <KpiTile label={t.automations.activity.inProgress} value={formatCount(activity.in_progress)} />
                    <KpiTile
                        label={t.automations.activity.completed}
                        value={formatCount(activity.completed)}
                        rateText={completionRate !== null ? formatRate(completionRate) : undefined}
                    />
                    <KpiTile label={t.automations.activity.exited} value={formatCount(activity.exited)} />
                </KpiRow>
            ) : null}

            <section className={styles.section} aria-labelledby="automation-emails-title">
                <Title3 as="h3" id="automation-emails-title">
                    {t.automations.activity.emails}
                </Title3>
                {activity && activity.emails.length > 0 ? (
                    <div className={styles.scroll}>
                        <DataGrid items={activity.emails} columns={emailColumns} getRowId={(row: AutomationEmailActivity) => row.step_id} className={styles.emailsGrid}>
                            <DataGridHeader>
                                <DataGridRow>{({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}</DataGridRow>
                            </DataGridHeader>
                            <DataGridBody<AutomationEmailActivity>>
                                {({ item, rowId }) => (
                                    <DataGridRow<AutomationEmailActivity> key={rowId}>
                                        {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                    </DataGridRow>
                                )}
                            </DataGridBody>
                        </DataGrid>
                    </div>
                ) : (
                    !activityLoading && <Text className={styles.muted}>{t.automations.editor.activity.emailsEmpty}</Text>
                )}
            </section>

            <section className={styles.section} aria-labelledby="automation-runs-title">
                <div className={styles.toolbar}>
                    <Title3 as="h3" id="automation-runs-title">
                        {t.automations.editor.activity.runs}
                    </Title3>
                    <Dropdown
                        className={styles.dropdown}
                        aria-label={t.common.status}
                        selectedOptions={[runStatus ?? ALL]}
                        value={runStatus ? t.automations.runStatus[runStatus] : t.common.all}
                        onOptionSelect={(_, data) =>
                            setRunStatus(!data.optionValue || data.optionValue === ALL ? null : (data.optionValue as AutomationRunStatus))
                        }
                    >
                        <Option value={ALL}>{t.common.all}</Option>
                        {RUN_STATUSES.map((status) => (
                            <Option key={status} value={status}>
                                {t.automations.runStatus[status]}
                            </Option>
                        ))}
                    </Dropdown>
                </div>
                {runsError && (
                    <MessageBar intent="error">
                        <MessageBarBody>{runsError}</MessageBarBody>
                    </MessageBar>
                )}
                {runsLoading && runs.length === 0 ? (
                    <Spinner label={t.common.loading} />
                ) : runs.length === 0 ? (
                    <Text className={styles.muted}>{runStatus ? t.common.noResults : t.automations.editor.activity.runsEmpty}</Text>
                ) : (
                    <div className={styles.scroll}>
                        <DataGrid items={runs} columns={runColumns} getRowId={(run: AutomationRun) => run.id} className={styles.runsGrid}>
                            <DataGridHeader>
                                <DataGridRow>{({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}</DataGridRow>
                            </DataGridHeader>
                            <DataGridBody<AutomationRun>>
                                {({ item, rowId }) => (
                                    <DataGridRow<AutomationRun> key={rowId}>
                                        {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                    </DataGridRow>
                                )}
                            </DataGridBody>
                        </DataGrid>
                    </div>
                )}
                {cursor && (
                    <div className={styles.footer}>
                        <Button appearance="secondary" disabled={runsLoading} onClick={() => void loadRuns(cursor)}>
                            {runsLoading ? t.common.loading : t.common.loadMore}
                        </Button>
                    </div>
                )}
            </section>

            <Dialog open={exitTarget !== null} onOpenChange={(_, data) => !data.open && setExitTarget(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.automations.exitRun}</DialogTitle>
                        <DialogContent>{exitTarget ? formatText(t.automations.editor.activity.exitConfirm, exitTarget.recipient.email) : ''}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setExitTarget(null)} disabled={exiting}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void confirmExit()} disabled={exiting}>
                                {exiting ? t.common.loading : t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </div>
    );
}
