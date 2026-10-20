import { useCallback, useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Badge,
    Button,
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
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AddRegular, LockClosedRegular, TagRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { AttributeFormDialog } from '@/Components/Attributes/AttributeFormDialog';
import { DeleteAttributeDialog } from '@/Components/Attributes/DeleteAttributeDialog';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage, createAttribute, fetchAttributes, updateAttribute } from '@/Lib/api/attributes';
import { hasPermission } from '@/Lib/permissions';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { AuthenticatedUser } from '@/Lib/types/identity';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    messages: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, marginBottom: tokens.spacingVerticalM },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXS },
    nameCell: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalS },
});

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

const formatDate = (iso: string) => new Date(iso).toLocaleDateString('fr-FR', { dateStyle: 'short' });

/** docs/43-brevo-parity-plan.md §43.4.10 — Contacts/Attributes (`recipients.view`; writes need `attributes.manage`). */
export default function Attributes() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();
    const canManage = hasPermission(props.auth.user.permissions, 'attributes.manage');

    const [attributes, setAttributes] = useState<ContactAttribute[]>([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ContactAttribute | null>(null);
    const [deleting, setDeleting] = useState<ContactAttribute | null>(null);

    const load = useCallback(async () => {
        try {
            setAttributes(await fetchAttributes(true));
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        void load();
    }, [load]);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const customCount = attributes.filter((a) => !a.is_system).length;

    return (
        <AppShell>
            <Head title={t.attributes.title} />
            <PageHelp
                topic="attributes"
                title={t.attributes.title}
                actions={
                    <>
                        {canManage && (
                            <Button appearance="primary" icon={<AddRegular />} onClick={openCreate}>
                                {t.attributes.createAttribute}
                            </Button>
                        )}
                    </>
                }
            />

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
                ) : (
                    <>
                        <Table aria-label={t.attributes.title}>
                            <TableHeader>
                                <TableRow>
                                    <TableHeaderCell>{t.common.name}</TableHeaderCell>
                                    <TableHeaderCell>{t.attributes.type}</TableHeaderCell>
                                    <TableHeaderCell>{t.attributes.usageColumn}</TableHeaderCell>
                                    <TableHeaderCell>{t.attributes.created}</TableHeaderCell>
                                    {canManage && <TableHeaderCell>{t.common.actions}</TableHeaderCell>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {attributes.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell>
                                            <div className={styles.nameCell}>
                                                <span>
                                                    <Text weight="semibold">{a.name}</Text>
                                                    {a.label !== a.name && <Text block size={200}>{a.label}</Text>}
                                                </span>
                                                {a.is_system && (
                                                    <Badge
                                                        appearance="tint"
                                                        color="informative"
                                                        icon={<LockClosedRegular />}
                                                        title={t.attributes.systemReadOnly}
                                                    >
                                                        {t.attributes.system}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>{t.attributes.types[a.type]}</TableCell>
                                        <TableCell>
                                            {a.usage_count === null
                                                ? t.common.emptyValue
                                                : t.attributes.usage.replace('%d', String(a.usage_count))}
                                        </TableCell>
                                        <TableCell>{formatDate(a.created_at)}</TableCell>
                                        {canManage && (
                                            <TableCell>
                                                {!a.is_system && (
                                                    <div className={styles.actions}>
                                                        <Button
                                                            size="small"
                                                            onClick={() => {
                                                                setEditing(a);
                                                                setFormOpen(true);
                                                            }}
                                                        >
                                                            {t.common.edit}
                                                        </Button>
                                                        <Button size="small" onClick={() => setDeleting(a)}>
                                                            {t.common.delete}
                                                        </Button>
                                                    </div>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {customCount === 0 && (
                            <EmptyState
                                icon={TagRegular}
                                title={t.attributes.emptyTitle}
                                action={
                                    canManage ? (
                                        <Button appearance="primary" icon={<AddRegular />} onClick={openCreate}>
                                            {t.attributes.createAttribute}
                                        </Button>
                                    ) : undefined
                                }
                            />
                        )}
                    </>
                )}
            </div>

            <AttributeFormDialog
                open={formOpen}
                attribute={editing}
                onOpenChange={setFormOpen}
                onCreate={async (payload) => {
                    await createAttribute(payload);
                    setNotice({ intent: 'success', text: t.common.saved });
                    await load();
                }}
                onUpdate={async (id, payload) => {
                    await updateAttribute(id, payload);
                    setNotice({ intent: 'success', text: t.common.saved });
                    await load();
                }}
            />
            <DeleteAttributeDialog
                attribute={deleting}
                onClose={() => setDeleting(null)}
                onDeleted={() => {
                    setDeleting(null);
                    void load();
                }}
            />
        </AppShell>
    );
}
