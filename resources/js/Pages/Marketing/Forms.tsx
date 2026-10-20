import { useCallback, useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Badge,
    Button,
    Caption1,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    makeStyles,
    tokens,
    type BadgeProps,
} from '@fluentui/react-components';
import {
    AddRegular,
    DeleteRegular,
    DocumentCopyRegular,
    EditRegular,
    FormRegular,
    MoreHorizontalRegular,
    PauseRegular,
    PlayRegular,
    SearchRegular,
} from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { CreateFormDialog } from '@/Components/Forms/CreateFormDialog';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage, apiErrorStatus, deleteForm, duplicateForm, fetchForms, publishForm, unpublishForm } from '@/Lib/api/forms';
import { hasPermission } from '@/Lib/permissions';
import type { SubscriptionForm, SubscriptionFormStatus } from '@/Lib/types/forms';
import type { PaginatedResponse } from '@/Lib/types/identity';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    toolbar: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        marginBottom: tokens.spacingVerticalM,
    },
    search: { flex: '1 1 240px', maxWidth: '360px' },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    table: { minWidth: '720px' },
    nameCell: { display: 'flex', flexDirection: 'column', minWidth: 0 },
    link: { color: tokens.colorBrandForeground1, fontWeight: tokens.fontWeightSemibold, textDecorationLine: 'none' },
    muted: { color: tokens.colorNeutralForeground3 },
    pager: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'flex-end',
        gap: tokens.spacingHorizontalS,
        marginTop: tokens.spacingVerticalM,
    },
    messages: { marginBottom: tokens.spacingVerticalM },
});

const STATUSES: SubscriptionFormStatus[] = ['draft', 'active', 'inactive'];
const ALL = '__all';
const PER_PAGE = 25;
const SEARCH_DEBOUNCE_MS = 300;

const STATUS_COLOR: Record<SubscriptionFormStatus, BadgeProps['color']> = {
    draft: 'informative',
    active: 'success',
    inactive: 'subtle',
};

const editUrl = (id: string) => `/marketing/forms/${id}`;

function format(template: string, ...args: (string | number)[]): string {
    let i = 0;

    return template.replace(/%[sd]/g, () => String(args[i++] ?? ''));
}

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.11 — *Marketing › Formulaires*: forms
 * table (name, lists, submissions, status) with create → name dialog →
 * builder, and row actions edit / duplicate / publish–deactivate / delete.
 * Route `marketing.forms` (`recipients.view`); data and actions need
 * `forms.manage` (§45.7.4).
 */
export default function Forms() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { permissions: string[] } }>();
    const canManage = hasPermission(props.auth?.permissions ?? [], 'forms.manage');

    const [result, setResult] = useState<PaginatedResponse<SubscriptionForm> | null>(null);
    const [loading, setLoading] = useState(true);
    const [forbidden, setForbidden] = useState(false);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [q, setQ] = useState('');
    const [debouncedQ, setDebouncedQ] = useState('');
    const [status, setStatus] = useState<SubscriptionFormStatus | null>(null);
    const [page, setPage] = useState(1);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<SubscriptionForm | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedQ(q.trim());
            setPage(1);
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [q]);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            setResult(
                await fetchForms({
                    ...(debouncedQ ? { q: debouncedQ } : {}),
                    ...(status ? { status } : {}),
                    page,
                    per_page: PER_PAGE,
                }),
            );
            setForbidden(false);
        } catch (error) {
            if (apiErrorStatus(error) === 403) {
                setForbidden(true);
            } else {
                setNotice({ intent: 'error', text: apiErrorMessage(error, t.common.genericError) });
            }
        } finally {
            setLoading(false);
        }
    }, [debouncedQ, status, page, t]);

    useEffect(() => {
        void load();
    }, [load]);

    async function run(form: SubscriptionForm, action: () => Promise<unknown>, success: string) {
        setBusyId(form.id);
        setNotice(null);
        try {
            await action();
            setNotice({ intent: 'success', text: success });
            await load();
        } catch (error) {
            setNotice({ intent: 'error', text: apiErrorMessage(error, t.common.genericError) });
        } finally {
            setBusyId(null);
        }
    }

    const forms = result?.data ?? [];
    const filtered = debouncedQ !== '' || status !== null;
    const createButton = canManage && (
        <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
            {t.forms.createForm}
        </Button>
    );

    return (
        <AppShell>
            <Head title={t.forms.title} />
            <PageHelp
                topic="forms"
                title={t.forms.title}
                actions={
                    <>
                        {createButton}
                    </>
                }
            />

            {notice && (
                <MessageBar intent={notice.intent} className={styles.messages}>
                    <MessageBarBody>{notice.text}</MessageBarBody>
                </MessageBar>
            )}

            {forbidden ? (
                <MessageBar intent="warning">
                    <MessageBarBody>{t.common.forbidden}</MessageBarBody>
                </MessageBar>
            ) : (
                <>
                    <div className={styles.toolbar}>
                        <Input
                            className={styles.search}
                            contentBefore={<SearchRegular />}
                            placeholder={t.common.searchPlaceholder}
                            aria-label={t.common.search}
                            value={q}
                            onChange={(_, d) => setQ(d.value)}
                        />
                        <Dropdown
                            aria-label={t.forms.status}
                            value={status ? t.forms.statusLabels[status] : `${t.forms.status} : ${t.common.all}`}
                            selectedOptions={[status ?? ALL]}
                            onOptionSelect={(_, d) => {
                                setStatus(!d.optionValue || d.optionValue === ALL ? null : (d.optionValue as SubscriptionFormStatus));
                                setPage(1);
                            }}
                        >
                            <Option value={ALL}>{t.common.all}</Option>
                            {STATUSES.map((s) => (
                                <Option key={s} value={s}>
                                    {t.forms.statusLabels[s]}
                                </Option>
                            ))}
                        </Dropdown>
                    </div>

                    <div className={styles.card}>
                        {loading && !result ? (
                            <Spinner label={t.common.loading} />
                        ) : forms.length === 0 ? (
                            filtered ? (
                                <Text>{t.common.noResults}</Text>
                            ) : (
                                <EmptyState icon={FormRegular} title={t.forms.emptyTitle} body={t.forms.emptyBody} action={createButton || undefined} />
                            )
                        ) : (
                            <Table aria-label={t.forms.title} className={styles.table}>
                                <TableHeader>
                                    <TableRow>
                                        <TableHeaderCell>{t.forms.name}</TableHeaderCell>
                                        <TableHeaderCell>{t.forms.lists}</TableHeaderCell>
                                        <TableHeaderCell>{t.forms.submissions}</TableHeaderCell>
                                        <TableHeaderCell>{t.forms.status}</TableHeaderCell>
                                        <TableHeaderCell>{t.common.lastModified}</TableHeaderCell>
                                        <TableHeaderCell aria-label={t.common.actions} />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {forms.map((form) => (
                                        <TableRow key={form.id}>
                                            <TableCell>
                                                <div className={styles.nameCell}>
                                                    {canManage ? (
                                                        <Link href={editUrl(form.id)} className={styles.link}>
                                                            {form.name}
                                                        </Link>
                                                    ) : (
                                                        <Text weight="semibold">{form.name}</Text>
                                                    )}
                                                    {form.created_by && <Caption1 className={styles.muted}>{form.created_by.name}</Caption1>}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {form.lists.length > 0 ? form.lists.map((l) => l.name).join(', ') : t.common.emptyValue}
                                            </TableCell>
                                            <TableCell>
                                                <div className={styles.nameCell}>
                                                    <Text>{form.submissions_count.toLocaleString('fr-FR')}</Text>
                                                    <Caption1 className={styles.muted}>
                                                        {`${t.forms.confirmed} : ${form.confirmed_count.toLocaleString('fr-FR')}`}
                                                    </Caption1>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge appearance="tint" color={STATUS_COLOR[form.status]}>
                                                    {t.forms.statusLabels[form.status]}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {new Date(form.updated_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}
                                            </TableCell>
                                            <TableCell>
                                                {canManage &&
                                                    (busyId === form.id ? (
                                                        <Spinner size="tiny" />
                                                    ) : (
                                                        <Menu>
                                                            <MenuTrigger disableButtonEnhancement>
                                                                <Button
                                                                    appearance="subtle"
                                                                    icon={<MoreHorizontalRegular />}
                                                                    aria-label={`${t.common.moreActions} — ${form.name}`}
                                                                />
                                                            </MenuTrigger>
                                                            <MenuPopover>
                                                                <MenuList>
                                                                    <MenuItem icon={<EditRegular />} onClick={() => router.visit(editUrl(form.id))}>
                                                                        {t.common.edit}
                                                                    </MenuItem>
                                                                    <MenuItem
                                                                        icon={<DocumentCopyRegular />}
                                                                        onClick={() =>
                                                                            void run(form, () => duplicateForm(form.id), t.forms.editor.list.duplicated)
                                                                        }
                                                                    >
                                                                        {t.common.duplicate}
                                                                    </MenuItem>
                                                                    {form.status === 'active' ? (
                                                                        <MenuItem
                                                                            icon={<PauseRegular />}
                                                                            onClick={() =>
                                                                                void run(form, () => unpublishForm(form.id), t.forms.editor.list.unpublished)
                                                                            }
                                                                        >
                                                                            {t.forms.unpublish}
                                                                        </MenuItem>
                                                                    ) : (
                                                                        <MenuItem
                                                                            icon={<PlayRegular />}
                                                                            onClick={() => void run(form, () => publishForm(form.id), t.forms.published)}
                                                                        >
                                                                            {t.forms.publish}
                                                                        </MenuItem>
                                                                    )}
                                                                    <MenuItem icon={<DeleteRegular />} onClick={() => setDeleting(form)}>
                                                                        {t.common.delete}
                                                                    </MenuItem>
                                                                </MenuList>
                                                            </MenuPopover>
                                                        </Menu>
                                                    ))}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        {result && result.meta.last_page > 1 && (
                            <div className={styles.pager}>
                                <Button size="small" disabled={page <= 1 || loading} onClick={() => setPage(page - 1)}>
                                    {t.common.previous}
                                </Button>
                                <Text>{format(t.common.page, result.meta.current_page, result.meta.last_page)}</Text>
                                <Button size="small" disabled={page >= result.meta.last_page || loading} onClick={() => setPage(page + 1)}>
                                    {t.common.next}
                                </Button>
                            </div>
                        )}
                    </div>
                </>
            )}

            <CreateFormDialog open={createOpen} onOpenChange={setCreateOpen} onCreated={(form) => router.visit(editUrl(form.id))} />

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
                                    if (target) void run(target, () => deleteForm(target.id), t.forms.editor.list.deleted);
                                }}
                            >
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
