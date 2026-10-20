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
import { AddRegular, DeleteRegular, DocumentEditRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { DesignFields } from '@/Components/Preferences/DesignFields';
import { useText } from '@/Hooks/useText';
import { fetchLists } from '@/Lib/api/lists';
import {
    apiErrorMessage,
    createUpdateProfileForm,
    deleteUpdateProfileForm,
    fetchUpdateProfileForms,
    setDefaultUpdateProfileForm,
    updateProfileFormPreviewUrl,
    updateUpdateProfileForm,
} from '@/Lib/api/preferences';
import type { PageDesign } from '@/Lib/types/common';
import type { ContactList } from '@/Lib/types/lists';
import type { UpdateProfileField, UpdateProfileForm, UpdateProfileFormContent } from '@/Lib/types/preferences';

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
    fieldRow: { display: 'flex', alignItems: 'flex-end', gap: tokens.spacingHorizontalS },
    lists: { display: 'flex', flexDirection: 'column', maxHeight: '160px', overflowY: 'auto' },
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

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

/** docs/43-brevo-parity-plan.md §43.4.18 — Settings/UpdateProfileForms. */
export default function UpdateProfileForms() {
    const styles = useStyles();
    const t = useText();
    const emptyContent: UpdateProfileFormContent = {
        title: t.preferences.public.title,
        intro: '',
        submit_label: t.preferences.public.save,
        success_message: t.preferences.public.saved,
    };

    const [forms, setForms] = useState<UpdateProfileForm[]>([]);
    const [allLists, setAllLists] = useState<ContactList[]>([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<UpdateProfileForm | null>(null);
    const [deleting, setDeleting] = useState<UpdateProfileForm | null>(null);
    const [name, setName] = useState('');
    const [fields, setFields] = useState<UpdateProfileField[]>([]);
    const [listIds, setListIds] = useState<string[]>([]);
    const [content, setContent] = useState<UpdateProfileFormContent>(emptyContent);
    const [design, setDesign] = useState<PageDesign>(DEFAULT_DESIGN);
    const [saving, setSaving] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            const [loaded, lists] = await Promise.all([fetchUpdateProfileForms(), fetchLists({ per_page: 100 })]);
            setForms(loaded);
            setAllLists(lists.data);
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        void load();
    }, [load]);

    function openForm(form: UpdateProfileForm | null) {
        setEditing(form);
        setName(form?.name ?? '');
        setFields(form?.fields ?? []);
        setListIds(form?.list_ids ?? []);
        setContent(form?.content ?? emptyContent);
        setDesign(form?.design ?? DEFAULT_DESIGN);
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
            const payload = { name, fields, list_ids: listIds, content, design };
            if (editing) {
                await updateUpdateProfileForm(editing.id, payload);
            } else {
                await createUpdateProfileForm(payload);
            }
            setFormOpen(false);
            await load();
        } catch (e) {
            setFormError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    const setText = (key: keyof UpdateProfileFormContent) => (_: unknown, d: { value: string }) =>
        setContent({ ...content, [key]: d.value });

    function patchField(index: number, patch: Partial<UpdateProfileField>) {
        setFields(fields.map((f, i) => (i === index ? { ...f, ...patch } : f)));
    }

    return (
        <AppShell>
            <Head title={t.preferences.title} />
            <div className={styles.header}>
                <Text as="h1" size={700} weight="semibold">
                    {t.preferences.title}
                </Text>
                <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                    {t.preferences.createForm}
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
                ) : forms.length === 0 ? (
                    <EmptyState
                        icon={DocumentEditRegular}
                        title={t.preferences.emptyTitle}
                        body=""
                        action={
                            <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                                {t.preferences.createForm}
                            </Button>
                        }
                    />
                ) : (
                    <Table aria-label={t.preferences.title}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell>{t.preferences.name}</TableHeaderCell>
                                <TableHeaderCell>{t.preferences.lists}</TableHeaderCell>
                                <TableHeaderCell>{t.preferences.default}</TableHeaderCell>
                                <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {forms.map((f) => (
                                <TableRow key={f.id}>
                                    <TableCell>{f.name}</TableCell>
                                    <TableCell>{f.list_ids.length}</TableCell>
                                    <TableCell>{f.is_default ? t.common.yes : '—'}</TableCell>
                                    <TableCell>
                                        <div className={styles.actions}>
                                            <Button size="small" onClick={() => openForm(f)}>
                                                {t.common.edit}
                                            </Button>
                                            <Button
                                                size="small"
                                                as="a"
                                                href={updateProfileFormPreviewUrl(f.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {t.preferences.preview}
                                            </Button>
                                            {!f.is_default && (
                                                <Button size="small" onClick={() => void run(() => setDefaultUpdateProfileForm(f.id))}>
                                                    {t.preferences.setDefault}
                                                </Button>
                                            )}
                                            <Button size="small" appearance="subtle" onClick={() => setDeleting(f)}>
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
                            <DialogTitle>{editing ? editing.name : t.preferences.createForm}</DialogTitle>
                            <DialogContent className={styles.form}>
                                {formError && (
                                    <MessageBar intent="error">
                                        <MessageBarBody>{formError}</MessageBarBody>
                                    </MessageBar>
                                )}
                                <Field label={t.preferences.name} required>
                                    <Input value={name} onChange={(_, d) => setName(d.value)} maxLength={100} />
                                </Field>

                                <Text weight="semibold">{t.preferences.fields}</Text>
                                {fields.map((f, i) => (
                                    <div key={i} className={styles.fieldRow}>
                                        <Field label={t.forms.fields.attribute}>
                                            <Input value={f.attribute} onChange={(_, d) => patchField(i, { attribute: d.value.toUpperCase() })} />
                                        </Field>
                                        <Field label={t.forms.fields.label}>
                                            <Input value={f.label} onChange={(_, d) => patchField(i, { label: d.value })} />
                                        </Field>
                                        <Checkbox
                                            label={t.forms.fields.required}
                                            checked={f.required}
                                            onChange={(_, d) => patchField(i, { required: d.checked === true })}
                                        />
                                        <Button
                                            type="button"
                                            appearance="subtle"
                                            icon={<DeleteRegular />}
                                            aria-label={t.common.remove}
                                            onClick={() => setFields(fields.filter((_, j) => j !== i))}
                                        />
                                    </div>
                                ))}
                                <div>
                                    <Button
                                        type="button"
                                        size="small"
                                        icon={<AddRegular />}
                                        onClick={() => setFields([...fields, { attribute: '', label: '', required: false }])}
                                    >
                                        {t.forms.fields.addField}
                                    </Button>
                                </div>

                                <Field label={t.preferences.lists} hint={t.preferences.listsHelp}>
                                    <div className={styles.lists}>
                                        {allLists.map((l) => (
                                            <Checkbox
                                                key={l.id}
                                                label={l.name}
                                                checked={listIds.includes(l.id)}
                                                onChange={(_, d) =>
                                                    setListIds(d.checked === true ? [...listIds, l.id] : listIds.filter((x) => x !== l.id))
                                                }
                                            />
                                        ))}
                                    </div>
                                </Field>

                                <Text weight="semibold">{t.preferences.content}</Text>
                                <Field label={t.preferences.formTitle}>
                                    <Input value={content.title} onChange={setText('title')} />
                                </Field>
                                <Field label={t.preferences.intro}>
                                    <Textarea value={content.intro} onChange={setText('intro')} />
                                </Field>
                                <Field label={t.preferences.submitLabel}>
                                    <Input value={content.submit_label} onChange={setText('submit_label')} />
                                </Field>
                                <Field label={t.preferences.successMessage}>
                                    <Textarea value={content.success_message} onChange={setText('success_message')} />
                                </Field>

                                <Text weight="semibold">{t.preferences.design}</Text>
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
                                        void run(() => deleteUpdateProfileForm(target.id));
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
