import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Badge,
    Button,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Spinner,
    Tab,
    TabList,
    Text,
    Textarea,
    Title2,
    Title3,
    Tooltip,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowLeftRegular, PauseRegular, PlayRegular, SaveRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { ActivityTab } from '@/Components/Automations/ActivityTab';
import { FlowCanvas } from '@/Components/Automations/FlowCanvas';
import {
    AUTOMATION_STATUS_BADGE,
    collectIssues,
    createDraftStep,
    findStep,
    fromServerSteps,
    insertStep,
    isTriggerConfigured,
    mapStepErrors,
    removeStep,
    replaceStep,
    toStepInputs,
    type BranchRef,
    type DraftStep,
    type MappedStepErrors,
} from '@/Components/Automations/graph';
import { AutomationLookupsProvider, useAutomationLookups } from '@/Components/Automations/lookups';
import { ReentrySettings } from '@/Components/Automations/ReentrySettings';
import { StepEditorDrawer } from '@/Components/Automations/StepEditorDrawer';
import { apiErrorMessage, apiStatus, apiValidationErrors } from '@/Components/Campaigns/campaignUi';
import { PageHelp } from '@/Components/Help/PageHelp';
import { useText } from '@/Hooks/useText';
import {
    activateAutomation,
    createAutomation,
    fetchAutomation,
    pauseAutomation,
    saveAutomationSteps,
    updateAutomation,
} from '@/Lib/api/automations';
import type { AutomationDetail, AutomationInput, AutomationStepType, AutomationTrigger } from '@/Lib/types/automations';
import type { Uuid } from '@/Lib/types/common';

type EditorTab = 'flow' | 'settings' | 'activity';
type DrawerState = { kind: 'trigger' } | { kind: 'step'; key: string } | null;
type Notice = { intent: 'success' | 'error' | 'warning' | 'info'; text: string };

const EMPTY_STEP_ERRORS: MappedStepErrors = { byStep: {}, general: [] };

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    titleBlock: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
    },
    actions: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    tabs: {
        overflowX: 'auto',
        flexWrap: 'nowrap',
        marginBottom: tokens.spacingVerticalM,
    },
    notice: {
        marginBottom: tokens.spacingVerticalM,
    },
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalL,
        marginBottom: tokens.spacingVerticalL,
        boxSizing: 'border-box',
        minWidth: 0,
        maxWidth: '760px',
    },
});

interface AutomationEditProps {
    /** Automation uuid; `null` on /automations/new. */
    uuid: Uuid | null;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.19 — Brevo-style linear workflow editor.
 * Tabs *Flux* (trigger + steps canvas, step drawer), *Paramètres* (name,
 * description, re-entry) and *Activité* (KPIs, per-e-mail rates, runs).
 * Saves through PATCH /automations/{id} (resource, trigger) and PUT
 * /automations/{id}/steps (whole tree); 422 `errors.steps.N.*` land on the
 * matching step card and drawer fields. Routes `automations.create` /
 * `automations.edit` (`automations.manage`).
 */
export default function AutomationEdit({ uuid }: AutomationEditProps) {
    return (
        <AppShell>
            <AutomationLookupsProvider>
                <AutomationEditor uuid={uuid} />
            </AutomationLookupsProvider>
        </AppShell>
    );
}

function AutomationEditor({ uuid }: AutomationEditProps) {
    const styles = useStyles();
    const t = useText();
    const { fieldCatalog, catalogLoading } = useAutomationLookups();

    const [automation, setAutomation] = useState<AutomationDetail | null>(null);
    const [loading, setLoading] = useState(uuid !== null);
    const [loadFailed, setLoadFailed] = useState<'not_found' | 'error' | null>(null);

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [trigger, setTrigger] = useState<AutomationTrigger | null>(null);
    const [reentry, setReentry] = useState<{ allow_reentry: boolean; reentry_after_days: number | null }>({
        allow_reentry: false,
        reentry_after_days: null,
    });
    const [steps, setSteps] = useState<DraftStep[]>([]);
    const [savedSteps, setSavedSteps] = useState<DraftStep[]>([]);
    const [metaDirty, setMetaDirty] = useState(false);
    const [stepsDirty, setStepsDirty] = useState(false);

    const [tab, setTab] = useState<EditorTab>('flow');
    const [drawer, setDrawer] = useState<DrawerState>(null);
    const [saving, setSaving] = useState(false);
    const [statusBusy, setStatusBusy] = useState(false);
    const [attempted, setAttempted] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [stepErrors, setStepErrors] = useState<MappedStepErrors>(EMPTY_STEP_ERRORS);
    const [notice, setNotice] = useState<Notice | null>(null);

    const applyMeta = useCallback((detail: AutomationDetail) => {
        setAutomation(detail);
        setName(detail.name);
        setDescription(detail.description ?? '');
        setTrigger(detail.trigger);
        setReentry({ allow_reentry: detail.allow_reentry, reentry_after_days: detail.reentry_after_days });
        setMetaDirty(false);
    }, []);

    const applySteps = useCallback((detail: AutomationDetail) => {
        const drafts = fromServerSteps(detail.steps ?? []);
        setSteps(drafts);
        setSavedSteps(drafts);
        setStepsDirty(false);
        setStepErrors(EMPTY_STEP_ERRORS);
        setDrawer((current) => (current?.kind === 'step' ? null : current));

        return drafts;
    }, []);

    useEffect(() => {
        if (!uuid) return;
        let cancelled = false;
        setLoading(true);
        fetchAutomation(uuid)
            .then((detail) => {
                if (cancelled) return;
                applyMeta(detail);
                applySteps(detail);
            })
            .catch((error) => {
                if (!cancelled) setLoadFailed(apiStatus(error) === 404 ? 'not_found' : 'error');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [uuid, applyMeta, applySteps]);

    const dirty = metaDirty || stepsDirty || automation === null;
    const hasEdits = metaDirty || stepsDirty;
    useEffect(() => {
        if (!hasEdits) return;
        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [hasEdits]);

    const status = automation?.status ?? 'draft';
    const isActive = status === 'active';
    const flowReadOnly = isActive;
    const issues = useMemo(() => collectIssues(steps, fieldCatalog), [steps, fieldCatalog]);
    const triggerReady = isTriggerConfigured(trigger, fieldCatalog);
    const canActivate = triggerReady && steps.length > 0;
    const nameMissing = name.trim() === '';
    const triggerErrorKeys = Object.keys(fieldErrors).filter((key) => key === 'trigger' || key.startsWith('trigger.'));
    const triggerInvalid = triggerErrorKeys.length > 0 || (attempted && !triggerReady);

    const drawerStep = drawer?.kind === 'step' ? findStep(steps, drawer.key) : null;
    const drawerTarget = drawer?.kind === 'trigger' ? drawer : drawerStep ? { kind: 'step' as const, step: drawerStep } : null;

    // -- editing ------------------------------------------------------------

    function changeMeta<T>(setter: (value: T) => void, errorKeys: string[] = []) {
        return (value: T) => {
            setter(value);
            setMetaDirty(true);
            if (errorKeys.length > 0) {
                setFieldErrors((current) => {
                    const next = { ...current };
                    for (const key of Object.keys(next)) {
                        if (errorKeys.some((prefix) => key === prefix || key.startsWith(`${prefix}.`))) delete next[key];
                    }

                    return next;
                });
            }
        };
    }

    const onTriggerChange = changeMeta<AutomationTrigger>(setTrigger, ['trigger']);

    function onInsert(ref: BranchRef, index: number, type: AutomationStepType) {
        const step = createDraftStep(type);
        setSteps((current) => insertStep(current, ref, index, step));
        setStepsDirty(true);
        if (type !== 'end') setDrawer({ kind: 'step', key: step.key });
    }

    function onRemoveStep(key: string) {
        setSteps((current) => removeStep(current, key));
        setStepsDirty(true);
        setDrawer((current) => (current?.kind === 'step' && current.key === key ? null : current));
    }

    function onStepChange(step: DraftStep) {
        setSteps((current) => replaceStep(current, step.key, step));
        setStepsDirty(true);
        setStepErrors((current) => {
            if (!current.byStep[step.key]) return current;
            const byStep = { ...current.byStep };
            delete byStep[step.key];

            return { ...current, byStep };
        });
    }

    // -- persistence --------------------------------------------------------

    function handleFailure(error: unknown, submitted: DraftStep[]) {
        const validation = apiValidationErrors(error);
        if (Object.keys(validation).length === 0) {
            setNotice({ intent: 'error', text: apiErrorMessage(error) });

            return;
        }
        const mapped = mapStepErrors(validation, submitted);
        const resourceErrors = Object.fromEntries(Object.entries(validation).filter(([key]) => key !== 'steps' && !key.startsWith('steps.')));
        setStepErrors(mapped);
        setFieldErrors(resourceErrors);
        const firstResource = Object.values(resourceErrors)[0];
        setNotice({
            intent: 'error',
            text: mapped.general[0] ?? firstResource ?? (Object.keys(mapped.byStep).length > 0 ? t.automations.editor.editor.fixBeforeSave : apiErrorMessage(error)),
        });
        if (Object.keys(mapped.byStep).length > 0 || mapped.general.length > 0) setTab('flow');
        else if (Object.keys(resourceErrors).some((key) => !key.startsWith('trigger'))) setTab('settings');
    }

    /** Saves what changed; returns the saved detail + the drafts now on screen, or null. */
    async function save(options: { navigate: boolean } = { navigate: true }): Promise<{ detail: AutomationDetail; drafts: DraftStep[] } | null> {
        setAttempted(true);
        if (nameMissing) {
            setNotice({ intent: 'error', text: t.automations.editor.editor.nameRequired });
            setTab('settings');

            return null;
        }
        if (!trigger || !triggerReady) {
            setNotice({ intent: 'error', text: t.automations.editor.trigger.notConfigured });
            setTab('flow');
            setDrawer({ kind: 'trigger' });

            return null;
        }
        if (issues.size > 0) {
            setNotice({ intent: 'error', text: t.automations.editor.editor.fixBeforeSave });
            setTab('flow');

            return null;
        }

        setSaving(true);
        setNotice(null);
        setFieldErrors({});
        setStepErrors(EMPTY_STEP_ERRORS);
        const submitted = steps;
        const meta: AutomationInput = {
            name: name.trim(),
            description: description.trim() === '' ? null : description.trim(),
            trigger,
            ...reentry,
        };
        const isNew = automation === null;
        let detail = automation;
        let drafts = steps;
        try {
            if (!detail) {
                detail = await createAutomation(meta);
                applyMeta(detail);
            } else if (metaDirty) {
                // §45.7.5: the trigger cannot change while active (409).
                const patch: Partial<AutomationInput> = { ...meta };
                if (isActive) delete patch.trigger;
                const patched = await updateAutomation(detail.id, patch);
                detail = { ...detail, ...patched, steps: patched.steps ?? detail.steps };
                applyMeta(detail);
            }
            if (stepsDirty || (isNew && submitted.length > 0)) {
                detail = await saveAutomationSteps(detail.id, toStepInputs(submitted));
                setAutomation((current) => (current ? { ...current, ...detail, steps: detail!.steps } : detail));
                drafts = applySteps(detail);
            }
            if (uuid === null && options.navigate) {
                setMetaDirty(false);
                setStepsDirty(false);
                router.visit(`/automations/${detail.id}`, { replace: true });
            } else {
                setNotice({ intent: 'success', text: t.common.saved });
            }

            return { detail, drafts };
        } catch (error) {
            handleFailure(error, submitted);

            return null;
        } finally {
            setSaving(false);
        }
    }

    async function activate() {
        setAttempted(true);
        if (!canActivate) return;
        if (issues.size > 0) {
            setNotice({ intent: 'error', text: t.automations.editor.editor.fixBeforeActivate });
            setTab('flow');

            return;
        }
        let target = automation;
        let drafts = steps;
        if (dirty) {
            const saved = await save({ navigate: false });
            if (!saved) return;
            target = saved.detail;
            drafts = saved.drafts;
        }
        if (!target) return;
        setStatusBusy(true);
        try {
            const activated = await activateAutomation(target.id);
            setAutomation((current) => (current ? { ...current, ...activated } : current));
            setNotice({ intent: 'success', text: t.automations.activated });
            setDrawer(null);
            if (uuid === null) router.visit(`/automations/${target.id}`, { replace: true });
        } catch (error) {
            handleFailure(error, drafts);
        } finally {
            setStatusBusy(false);
        }
    }

    async function pause() {
        if (!automation) return;
        setStatusBusy(true);
        try {
            const paused = await pauseAutomation(automation.id);
            setAutomation((current) => (current ? { ...current, ...paused } : current));
            setNotice({ intent: 'success', text: t.automations.paused });
        } catch (error) {
            setNotice({ intent: 'error', text: apiErrorMessage(error) });
        } finally {
            setStatusBusy(false);
        }
    }

    // -- render ---------------------------------------------------------------

    const pageTitle = automation ? automation.name : t.automations.createTitle;

    if (loading || catalogLoading) {
        return (
            <>
                <Head title={t.automations.title} />
                <Spinner label={t.common.loading} />
            </>
        );
    }

    if (loadFailed) {
        return (
            <>
                <Head title={t.automations.title} />
                <MessageBar intent="error" className={styles.notice}>
                    <MessageBarBody>{loadFailed === 'not_found' ? t.common.notFound : t.automations.editor.editor.loadFailed}</MessageBarBody>
                </MessageBar>
                <Button appearance="subtle" icon={<ArrowLeftRegular />} onClick={() => router.visit('/automations')}>
                    {t.common.back}
                </Button>
            </>
        );
    }

    const busy = saving || statusBusy;
    const activateButton = (
        <Button
            appearance={dirty ? 'secondary' : 'primary'}
            icon={statusBusy ? <Spinner size="tiny" /> : <PlayRegular />}
            disabled={canActivate && busy}
            disabledFocusable={!canActivate}
            onClick={() => void activate()}
        >
            {t.automations.activate}
        </Button>
    );

    return (
        <>
            <Head title={`${pageTitle} — ${t.automations.title}`} />
            <PageHelp topic="automation-edit" hideHeaderRow />
            <div className={styles.header}>
                <div className={styles.titleBlock}>
                    <Button appearance="subtle" icon={<ArrowLeftRegular />} aria-label={t.common.back} onClick={() => router.visit('/automations')} />
                    <Title2 as="h1">{name.trim() || pageTitle}</Title2>
                    <Badge appearance="tint" color={AUTOMATION_STATUS_BADGE[status]}>
                        {t.automations.statusLabels[status]}
                    </Badge>
                </div>
                <div className={styles.actions}>
                    {hasEdits && <Text className={styles.muted}>{t.common.unsavedChanges}</Text>}
                    <Button
                        appearance={dirty ? 'primary' : 'secondary'}
                        icon={saving ? <Spinner size="tiny" /> : <SaveRegular />}
                        disabled={busy || (!dirty && automation !== null)}
                        onClick={() => void save()}
                    >
                        {saving ? t.common.saving : t.common.save}
                    </Button>
                    {isActive ? (
                        <Button appearance="secondary" icon={statusBusy ? <Spinner size="tiny" /> : <PauseRegular />} disabled={busy} onClick={() => void pause()}>
                            {t.automations.pause}
                        </Button>
                    ) : canActivate ? (
                        activateButton
                    ) : (
                        <Tooltip content={t.automations.editor.editor.activateDisabled} relationship="description">
                            {activateButton}
                        </Tooltip>
                    )}
                </div>
            </div>

            {notice && (
                <MessageBar intent={notice.intent} className={styles.notice}>
                    <MessageBarBody>{notice.text}</MessageBarBody>
                </MessageBar>
            )}

            <TabList className={styles.tabs} selectedValue={tab} onTabSelect={(_, data) => setTab(data.value as EditorTab)}>
                <Tab value="flow">{t.automations.editor.tabs.flow}</Tab>
                <Tab value="settings">{t.automations.editor.tabs.settings}</Tab>
                <Tab value="activity" disabled={!automation}>
                    {t.automations.tabs.activity}
                </Tab>
            </TabList>

            {tab === 'flow' && (
                <>
                    {flowReadOnly && (
                        <MessageBar intent="info" className={styles.notice}>
                            <MessageBarBody>{t.automations.cannotEditActive}</MessageBarBody>
                        </MessageBar>
                    )}
                    {stepErrors.general.length > 0 && (
                        <MessageBar intent="error" className={styles.notice}>
                            <MessageBarBody>{stepErrors.general.join(' ')}</MessageBarBody>
                        </MessageBar>
                    )}
                    <FlowCanvas
                        ariaLabel={t.automations.editor.tabs.flow}
                        trigger={trigger}
                        triggerInvalid={triggerInvalid}
                        steps={steps}
                        selectedKey={drawer?.kind === 'trigger' ? 'trigger' : (drawerStep?.key ?? null)}
                        readOnly={flowReadOnly}
                        issues={issues}
                        serverErrors={stepErrors.byStep}
                        onOpenTrigger={() => setDrawer({ kind: 'trigger' })}
                        onOpenStep={(key) => setDrawer({ kind: 'step', key })}
                        onRemoveStep={onRemoveStep}
                        onInsert={onInsert}
                    />
                </>
            )}

            {tab === 'settings' && (
                <section className={styles.card} aria-labelledby="automation-settings-title">
                    <Title3 as="h2" id="automation-settings-title">
                        {t.automations.editor.settings.general}
                    </Title3>
                    <Field
                        label={t.automations.name}
                        required
                        validationMessage={fieldErrors.name ?? (attempted && nameMissing ? t.automations.editor.editor.nameRequired : undefined)}
                        validationState={fieldErrors.name || (attempted && nameMissing) ? 'error' : 'none'}
                    >
                        <Input value={name} maxLength={255} disabled={busy} onChange={(_, data) => changeMeta(setName, ['name'])(data.value)} />
                    </Field>
                    <Field
                        label={`${t.automations.description} (${t.common.optional})`}
                        validationMessage={fieldErrors.description}
                        validationState={fieldErrors.description ? 'error' : 'none'}
                    >
                        <Textarea
                            value={description}
                            resize="vertical"
                            disabled={busy}
                            onChange={(_, data) => changeMeta(setDescription, ['description'])(data.value)}
                        />
                    </Field>
                    <ReentrySettings
                        allowReentry={reentry.allow_reentry}
                        reentryAfterDays={reentry.reentry_after_days}
                        errors={fieldErrors}
                        disabled={busy}
                        onChange={changeMeta(setReentry, ['allow_reentry', 'reentry_after_days'])}
                    />
                </section>
            )}

            {tab === 'activity' &&
                (automation ? (
                    <ActivityTab automationId={automation.id} steps={savedSteps} />
                ) : (
                    <MessageBar intent="info">
                        <MessageBarBody>{t.automations.editor.editor.draftNotSaved}</MessageBarBody>
                    </MessageBar>
                ))}

            <StepEditorDrawer
                target={drawerTarget}
                onClose={() => setDrawer(null)}
                trigger={trigger}
                onTriggerChange={onTriggerChange}
                triggerErrors={fieldErrors}
                triggerLocked={isActive}
                onStepChange={onStepChange}
                onRemoveStep={onRemoveStep}
                stepErrors={drawerStep ? (stepErrors.byStep[drawerStep.key] ?? {}) : {}}
                readOnly={flowReadOnly}
                showRequired={attempted}
            />
        </>
    );
}
