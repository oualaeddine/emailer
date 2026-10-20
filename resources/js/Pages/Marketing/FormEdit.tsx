import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Badge,
    Button,
    Caption1,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Spinner,
    Tab,
    TabList,
    Text,
    Title2,
    makeStyles,
    tokens,
    type BadgeProps,
} from '@fluentui/react-components';
import { ArrowLeftRegular, PauseRegular, PlayRegular, SaveRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { DesignPanel } from '@/Components/Forms/DesignPanel';
import { FieldsEditor } from '@/Components/Forms/FieldsEditor';
import { FormPreview } from '@/Components/Forms/FormPreview';
import { SettingsPanel } from '@/Components/Forms/SettingsPanel';
import { SharePanel } from '@/Components/Forms/SharePanel';
import { EMAIL_ATTRIBUTE, areValidEmails, draftFromForm, draftToInput, isHttpUrl, type FormDraft } from '@/Components/Forms/model';
import { formText } from '@/Components/Forms/strings';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    apiErrorStatus,
    apiValidationErrors,
    fetchDoiTemplates,
    fetchForm,
    fetchFormAttributes,
    fetchFormLists,
    publishForm,
    unpublishForm,
    updateForm,
} from '@/Lib/api/forms';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { Uuid } from '@/Lib/types/common';
import type { FormBuilderTab, SubscriptionForm, SubscriptionFormStatus } from '@/Lib/types/forms';
import type { ContactList } from '@/Lib/types/lists';
import type { TemplateV2 } from '@/Lib/types/templates';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    titleBlock: { display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: tokens.spacingHorizontalS, minWidth: 0 },
    actions: { display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: tokens.spacingHorizontalS },
    muted: { color: tokens.colorNeutralForeground3 },
    layout: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)',
        gap: tokens.spacingHorizontalL,
        alignItems: 'start',
        '@media (max-width: 1099px)': { gridTemplateColumns: 'minmax(0, 1fr)' },
    },
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalL,
        boxSizing: 'border-box',
        minWidth: 0,
    },
    previewCard: {
        position: 'sticky',
        top: tokens.spacingVerticalL,
        '@media (max-width: 1099px)': { position: 'static' },
    },
    tabs: { overflowX: 'auto' },
    notice: { marginBottom: tokens.spacingVerticalM },
});

const TABS: FormBuilderTab[] = ['fields', 'design', 'settings', 'share'];

const STATUS_COLOR: Record<SubscriptionFormStatus, BadgeProps['color']> = {
    draft: 'informative',
    active: 'success',
    inactive: 'subtle',
};

const LIST_URL = '/marketing/forms';

interface FormEditProps {
    uuid: Uuid;
}

interface Notice {
    intent: 'success' | 'error' | 'warning';
    text: string;
}

/** Tab holding the first error of a 422 (dot-path keys from the API). */
function tabForError(key: string): FormBuilderTab {
    if (key.startsWith('fields') || key.startsWith('gdpr')) return 'fields';
    if (key.startsWith('design')) return 'design';

    return 'settings';
}

/** Client-side checks mirroring the server rules that would otherwise answer 422. */
function localErrors(draft: FormDraft, t: ReturnType<typeof useText>): Record<string, string> {
    const errors: Record<string, string> = {};
    if (draft.name.trim() === '') errors.name = t.common.required;
    if (!draft.fields.some((f) => f.attribute === EMAIL_ATTRIBUTE)) errors.fields = t.forms.fields.emailRequired;
    draft.fields.forEach((f, i) => {
        if (f.label.trim() === '') errors[`fields.${i}.label`] = t.common.required;
    });
    if (draft.gdpr.enabled && draft.gdpr.text.trim() === '') errors['gdpr.text'] = t.common.required;
    if (draft.design.button_text.trim() === '') errors['design.button_text'] = t.common.required;
    if (draft.settings.redirect_url !== null) {
        if (draft.settings.redirect_url.trim() === '') errors['settings.redirect_url'] = t.common.required;
        else if (!isHttpUrl(draft.settings.redirect_url)) errors['settings.redirect_url'] = formText.settings.invalidUrl;
    } else if (draft.settings.success_message.trim() === '') {
        errors['settings.success_message'] = t.common.required;
    }
    if (!isHttpUrl(draft.settings.doi_redirect_url)) errors['settings.doi_redirect_url'] = formText.settings.invalidUrl;
    if (!areValidEmails(draft.settings.notify_emails)) errors['settings.notify_emails'] = formText.settings.invalidEmails;

    return errors;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.11 — subscription form builder
 * (`marketing.forms.edit`, `forms.manage`): tabs Champs / Design /
 * Paramètres / Partager next to a live preview built client-side from the
 * unsaved definition. Explicit save (PATCH /forms/{id}); publishing saves
 * pending changes first.
 */
export default function FormEdit({ uuid }: FormEditProps) {
    const styles = useStyles();
    const t = useText();

    const [form, setForm] = useState<SubscriptionForm | null>(null);
    const [draft, setDraft] = useState<FormDraft | null>(null);
    const [dirty, setDirty] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadFailed, setLoadFailed] = useState<'not_found' | 'forbidden' | 'error' | null>(null);
    const [tab, setTab] = useState<FormBuilderTab>('fields');

    const [attributes, setAttributes] = useState<ContactAttribute[]>([]);
    const [lists, setLists] = useState<ContactList[]>([]);
    const [templates, setTemplates] = useState<TemplateV2[]>([]);

    const [saving, setSaving] = useState(false);
    const [switching, setSwitching] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<Notice | null>(null);

    const applyForm = useCallback((f: SubscriptionForm) => {
        setForm(f);
        setDraft(draftFromForm(f));
        setDirty(false);
    }, []);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        fetchForm(uuid)
            .then((f) => {
                if (!cancelled) applyForm(f);
            })
            .catch((error) => {
                if (cancelled) return;
                const status = apiErrorStatus(error);
                setLoadFailed(status === 404 ? 'not_found' : status === 403 ? 'forbidden' : 'error');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [uuid, applyForm]);

    useEffect(() => {
        fetchFormAttributes()
            .then(setAttributes)
            .catch(() => setAttributes([]));
        fetchFormLists()
            .then(setLists)
            .catch(() => setLists([]));
        fetchDoiTemplates()
            .then(setTemplates)
            .catch(() => setTemplates([]));
    }, []);

    // Unsaved-changes guard: browser unload and Inertia navigation.
    useEffect(() => {
        if (!dirty) return;
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        const removeRouterGuard = router.on('before', (event) => {
            if (event.detail.visit.method === 'get' && !window.confirm(t.common.unsavedChanges)) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeRouterGuard();
        };
    }, [dirty, t]);

    const knownListNames = useMemo(() => Object.fromEntries((form?.lists ?? []).map((l) => [l.id, l.name])), [form]);

    function change(patch: Partial<FormDraft>) {
        setDraft((current) => (current ? { ...current, ...patch } : current));
        setDirty(true);
    }

    function reportErrors(found: Record<string, string>, fallback: string) {
        setErrors(found);
        const first = Object.keys(found)[0];
        if (first) setTab(tabForError(first));
        setNotice({ intent: 'error', text: Object.values(found)[0] ?? fallback });
    }

    /** Saves the draft; resolves to the saved form, or null when it failed. */
    async function save(): Promise<SubscriptionForm | null> {
        if (!form || !draft) return null;
        const local = localErrors(draft, t);
        if (Object.keys(local).length > 0) {
            reportErrors(local, t.common.genericError);

            return null;
        }
        setSaving(true);
        setNotice(null);
        setErrors({});
        try {
            const saved = await updateForm(form.id, draftToInput(draft));
            applyForm(saved);

            return saved;
        } catch (error) {
            const validation = apiValidationErrors(error);
            if (validation) reportErrors(validation, apiErrorMessage(error, t.common.genericError));
            else setNotice({ intent: 'error', text: apiErrorMessage(error, t.common.genericError) });

            return null;
        } finally {
            setSaving(false);
        }
    }

    async function onSubmit(event: FormEvent) {
        event.preventDefault();
        if (await save()) setNotice({ intent: 'success', text: t.common.saved });
    }

    async function togglePublished() {
        if (!form || !draft) return;
        const publishing = form.status !== 'active';
        if (publishing && draft.list_ids.length === 0) {
            reportErrors({ list_ids: formText.settings.listRequired }, formText.settings.listRequired);

            return;
        }
        setSwitching(true);
        try {
            const current = dirty ? await save() : form;
            if (!current) return;
            applyForm(publishing ? await publishForm(current.id) : await unpublishForm(current.id));
            setNotice({ intent: 'success', text: publishing ? t.forms.published : formText.list.unpublished });
            if (publishing) setTab('share');
        } catch (error) {
            const validation = apiValidationErrors(error);
            if (validation) reportErrors(validation, apiErrorMessage(error, t.common.genericError));
            else setNotice({ intent: 'error', text: apiErrorMessage(error, t.common.genericError) });
        } finally {
            setSwitching(false);
        }
    }

    if (loading) {
        return (
            <AppShell>
                <Head title={t.forms.title} />
                <Spinner label={t.common.loading} />
            </AppShell>
        );
    }

    if (loadFailed || !form || !draft) {
        const message = loadFailed === 'not_found' ? t.common.notFound : loadFailed === 'forbidden' ? t.common.forbidden : t.common.genericError;

        return (
            <AppShell>
                <Head title={t.forms.title} />
                <MessageBar intent="error" className={styles.notice}>
                    <MessageBarBody>{message}</MessageBarBody>
                </MessageBar>
                <Button appearance="subtle" icon={<ArrowLeftRegular />} onClick={() => router.visit(LIST_URL)}>
                    {t.common.back}
                </Button>
            </AppShell>
        );
    }

    const busy = saving || switching;

    return (
        <AppShell>
            <Head title={`${form.name} — ${t.forms.title}`} />
            <form onSubmit={onSubmit}>
                <div className={styles.header}>
                    <div className={styles.titleBlock}>
                        <Button appearance="subtle" icon={<ArrowLeftRegular />} aria-label={t.common.back} onClick={() => router.visit(LIST_URL)} />
                        <Title2 as="h1">{form.name}</Title2>
                        <Badge appearance="tint" color={STATUS_COLOR[form.status]}>
                            {t.forms.statusLabels[form.status]}
                        </Badge>
                        <Caption1 className={styles.muted}>
                            {`${t.forms.submissions} : ${form.submissions_count.toLocaleString('fr-FR')} · ${t.forms.confirmed} : ${form.confirmed_count.toLocaleString('fr-FR')}`}
                        </Caption1>
                    </div>
                    <div className={styles.actions}>
                        {dirty && <Text className={styles.muted}>{t.common.unsavedChanges}</Text>}
                        <Button
                            appearance="secondary"
                            type="submit"
                            icon={saving ? <Spinner size="tiny" /> : <SaveRegular />}
                            disabled={busy || !dirty}
                        >
                            {saving ? t.common.saving : t.common.save}
                        </Button>
                        <Button
                            appearance={form.status === 'active' ? 'secondary' : 'primary'}
                            icon={switching ? <Spinner size="tiny" /> : form.status === 'active' ? <PauseRegular /> : <PlayRegular />}
                            disabled={busy}
                            onClick={() => void togglePublished()}
                        >
                            {form.status === 'active' ? t.forms.unpublish : t.forms.publish}
                        </Button>
                    </div>
                </div>

                {notice && (
                    <MessageBar intent={notice.intent} className={styles.notice}>
                        <MessageBarBody>{notice.text}</MessageBarBody>
                    </MessageBar>
                )}

                <div className={styles.layout}>
                    <section className={styles.card} aria-label={t.forms.tabs[tab]}>
                        <TabList
                            className={styles.tabs}
                            selectedValue={tab}
                            onTabSelect={(_, d) => setTab(d.value as FormBuilderTab)}
                        >
                            {TABS.map((key) => (
                                <Tab key={key} value={key}>
                                    {t.forms.tabs[key]}
                                </Tab>
                            ))}
                        </TabList>

                        {tab === 'fields' && (
                            <FieldsEditor
                                fields={draft.fields}
                                gdpr={draft.gdpr}
                                attributes={attributes}
                                errors={errors}
                                onFieldsChange={(fields) => change({ fields })}
                                onGdprChange={(gdpr) => change({ gdpr })}
                            />
                        )}
                        {tab === 'design' && <DesignPanel design={draft.design} errors={errors} onChange={(design) => change({ design })} />}
                        {tab === 'settings' && (
                            <>
                                <Field
                                    label={t.forms.name}
                                    required
                                    validationMessage={errors.name}
                                    validationState={errors.name ? 'error' : 'none'}
                                >
                                    <Input value={draft.name} maxLength={255} onChange={(_, d) => change({ name: d.value })} />
                                </Field>
                                <SettingsPanel
                                    key={form.id}
                                    settings={draft.settings}
                                    listIds={draft.list_ids}
                                    lists={lists}
                                    knownListNames={knownListNames}
                                    templates={templates}
                                    errors={errors}
                                    onSettingsChange={(settings) => change({ settings })}
                                    onListIdsChange={(list_ids) => change({ list_ids })}
                                />
                            </>
                        )}
                        {tab === 'share' && <SharePanel share={form.share} status={form.status} />}
                    </section>

                    <div className={`${styles.card} ${styles.previewCard}`}>
                        <FormPreview draft={draft} />
                    </div>
                </div>
            </form>
        </AppShell>
    );
}
