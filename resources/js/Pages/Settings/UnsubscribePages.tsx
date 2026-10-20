import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Spinner,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    Textarea,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AddRegular, DocumentRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { DesignFields } from '@/Components/Preferences/DesignFields';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    createUnsubscribePage,
    deleteUnsubscribePage,
    fetchUnsubscribePages,
    setDefaultUnsubscribePage,
    unsubscribePagePreviewUrl,
    updateUnsubscribePage,
} from '@/Lib/api/preferences';
import type { PageDesign } from '@/Lib/types/common';
import type { UnsubscribePage, UnsubscribePageContent } from '@/Lib/types/preferences';

const useStyles = makeStyles({
    header: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: tokens.spacingVerticalL },
    messages: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, marginBottom: tokens.spacingVerticalM },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXS },
    form: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
});

const DEFAULT_DESIGN: PageDesign = {
    logo_url: null,
    primary_color: '#0F6CBD',
    background_color: '#F5F5F5',
    text_color: '#242424',
    font_family: 'Segoe UI, sans-serif',
    button_text_color: '#FFFFFF',
    border_radius: 4,
};

const emptyContent = (t: ReturnType<typeof useText>): UnsubscribePageContent => ({
    title: t.unsubscribePages.public.title,
    body: '',
    button_label: t.unsubscribePages.public.confirm,
    confirmation_title: t.unsubscribePages.public.done,
    confirmation_message: t.unsubscribePages.public.doneBody,
    show_reason_survey: true,
    reasons: Object.values(t.unsubscribePages.defaultReasons),
    show_preferences_link: true,
    resubscribe_enabled: false,
});

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

/** docs/43-brevo-parity-plan.md §43.4.18 — Settings/UnsubscribePages. */
export default function UnsubscribePages() {
    const styles = useStyles();
    const t = useText();
    const [pages, setPages] = useState<UnsubscribePage[]>([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<UnsubscribePage | null>(null);
    const [deleting, setDeleting] = useState<UnsubscribePage | null>(null);
    const [name, setName] = useState('');
    const [content, setContent] = useState<UnsubscribePageContent>(emptyContent(t));
    const [design, setDesign] = useState<PageDesign>(DEFAULT_DESIGN);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            setPages(await fetchUnsubscribePages());
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        void load();
    }, [load]);

    function openForm(page: UnsubscribePage | null) {
        setEditing(page);
        setName(page?.name ?? '');
        setContent(page?.content ?? emptyContent(t));
        setDesign(page?.design ?? DEFAULT_DESIGN);
        setFormError(null);
        setFormOpen(true);
    }

    async function run(action: () => Promise<unknown>) {
        try {
            await action();
            await load();
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        }
    }

    async function submit(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setFormError(null);
        try {
            const payload = { name, content, design };
            if (editing) {
                await updateUnsubscribePage(editing.id, payload);
            } else {
                await createUnsubscribePage(payload);
            }
            setFormOpen(false);
            await load();
        } catch (e) {
            setFormError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    const setText = (key: keyof UnsubscribePageContent) => (_: unknown, d: { value: string }) =>
        setContent({ ...content, [key]: d.value });

    return (
        <AppShell>
            <Head title={t.unsubscribePages.title} />
            <div className={styles.header}>
                <Text as="h1" size={700} weight="semibold">
                    {t.unsubscribePages.title}
                </Text>
                <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                    {t.unsubscribePages.createPage}
                </Button>
            </div>

            {notice && (
                <div className={styles.messages}>
                    <MessageBar intent={notice.intent}>
                        <MessageBarBody>{notice.text}</MessageBarBody>
                    </MessageBar>
                </div>
            )}

            <div className={styles.card}>
                {loading ? (
                    <Spinner label={t.common.loading} />
                ) : pages.length === 0 ? (
                    <EmptyState
                        icon={DocumentRegular}
                        title={t.unsubscribePages.emptyTitle}
                        body=""
                        action={
                            <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                                {t.unsubscribePages.createPage}
                            </Button>
                        }
                    />
                ) : (
                    <Table aria-label={t.unsubscribePages.title}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell>{t.unsubscribePages.name}</TableHeaderCell>
                                <TableHeaderCell>{t.unsubscribePages.default}</TableHeaderCell>
                                <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pages.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>{p.name}</TableCell>
                                    <TableCell>{p.is_default ? t.common.yes : '—'}</TableCell>
                                    <TableCell>
                                        <div className={styles.actions}>
                                            <Button size="small" onClick={() => openForm(p)}>
                                                {t.common.edit}
                                            </Button>
                                            <Button
                                                size="small"
                                                as="a"
                                                href={unsubscribePagePreviewUrl(p.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {t.unsubscribePages.preview}
                                            </Button>
                                            {!p.is_default && (
                                                <Button size="small" onClick={() => void run(() => setDefaultUnsubscribePage(p.id))}>
                                                    {t.unsubscribePages.setDefault}
                                                </Button>
                                            )}
                                            <Button size="small" appearance="subtle" onClick={() => setDeleting(p)}>
                                                {t.common.delete}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            <Dialog open={formOpen} onOpenChange={(_, d) => setFormOpen(d.open)}>
                <DialogSurface>
                    <form onSubmit={submit}>
                        <DialogBody>
                            <DialogTitle>{editing ? editing.name : t.unsubscribePages.createPage}</DialogTitle>
                            <DialogContent className={styles.form}>
                                {formError && (
                                    <MessageBar intent="error">
                                        <MessageBarBody>{formError}</MessageBarBody>
                                    </MessageBar>
                                )}
                                <Field label={t.unsubscribePages.name} required>
                                    <Input value={name} onChange={(_, d) => setName(d.value)} maxLength={100} />
                                </Field>
                                <Text weight="semibold">{t.unsubscribePages.content}</Text>
                                <Field label={t.unsubscribePages.pageTitle}>
                                    <Input value={content.title} onChange={setText('title')} />
                                </Field>
                                <Field label={t.unsubscribePages.body}>
                                    <Textarea value={content.body} onChange={setText('body')} />
                                </Field>
                                <Field label={t.unsubscribePages.buttonLabel}>
                                    <Input value={content.button_label} onChange={setText('button_label')} />
                                </Field>
                                <Field label={t.unsubscribePages.confirmationTitle}>
                                    <Input value={content.confirmation_title} onChange={setText('confirmation_title')} />
                                </Field>
                                <Field label={t.unsubscribePages.confirmationMessage}>
                                    <Textarea value={content.confirmation_message} onChange={setText('confirmation_message')} />
                                </Field>
                                <Checkbox
                                    label={t.unsubscribePages.reasonSurvey}
                                    checked={content.show_reason_survey}
                                    onChange={(_, d) => setContent({ ...content, show_reason_survey: d.checked === true })}
                                />
                                {content.show_reason_survey && (
                                    <Field label={t.unsubscribePages.reasons}>
                                        <Textarea
                                            value={content.reasons.join('\n')}
                                            onChange={(_, d) =>
                                                setContent({ ...content, reasons: d.value.split('\n').filter((r) => r.trim() !== '') })
                                            }
                                        />
                                    </Field>
                                )}
                                <Checkbox
                                    label={t.unsubscribePages.showPreferencesLink}
                                    checked={content.show_preferences_link}
                                    onChange={(_, d) => setContent({ ...content, show_preferences_link: d.checked === true })}
                                />
                                <Checkbox
                                    label={t.unsubscribePages.resubscribe}
                                    checked={content.resubscribe_enabled}
                                    onChange={(_, d) => setContent({ ...content, resubscribe_enabled: d.checked === true })}
                                />
                                <Text weight="semibold">{t.unsubscribePages.design}</Text>
                                <DesignFields design={design} onChange={setDesign} />
                            </DialogContent>
                            <DialogActions>
                                <Button appearance="secondary" type="button" onClick={() => setFormOpen(false)}>
                                    {t.common.cancel}
                                </Button>
                                <Button appearance="primary" type="submit" disabled={saving || name.trim() === ''}>
                                    {saving ? <Spinner size="tiny" /> : t.common.save}
                                </Button>
                            </DialogActions>
                        </DialogBody>
                    </form>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, d) => !d.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.deleteConfirmTitle}</DialogTitle>
                        <DialogContent>{deleting ? `${deleting.name} — ${t.common.deleteConfirmBody}` : ''}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setDeleting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                onClick={() => {
                                    const target = deleting;
                                    setDeleting(null);
                                    if (target) {
                                        void run(() => deleteUnsubscribePage(target.id));
                                    }
                                }}
                            >
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
