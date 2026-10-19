import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Text,
    Textarea,
    Title2,
    Title3,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { ArrowClockwiseRegular, ArrowLeftRegular, MailRegular, SaveRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import ConditionBuilder, {
    countRuleTree,
    defaultConditionOptionSources,
    isRuleTreeComplete,
    ruleTreeErrors,
    useFieldCatalog,
    validationErrors,
} from '@/Components/Contacts/ConditionBuilder';
import { format } from '@/Components/Contacts/ConditionBuilder/editors/shared';
import { errorMessage, errorStatus } from '@/Components/Contacts/ConditionBuilder/errors';
import { useText } from '@/Hooks/useText';
import {
    createSegment,
    fetchSegment,
    fetchSegmentFolders,
    fetchSegmentMembers,
    refreshSegmentCount,
    updateSegment,
} from '@/Lib/api/segments';
import { hasPermission } from '@/Lib/permissions';
import type { Folder, Uuid } from '@/Lib/types/common';
import type { Contact } from '@/Lib/types/contacts';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import type { RuleTree, Segment, SegmentInput } from '@/Lib/types/segments';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    titleBlock: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    actions: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
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
    },
    builderSurface: {
        backgroundColor: tokens.colorNeutralBackground2,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
    detailsGrid: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 767px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
    fullWidth: {
        gridColumn: '1 / -1',
    },
    sectionHeader: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    gridScroll: {
        overflowX: 'auto',
    },
    grid: {
        minWidth: '680px',
    },
    footer: {
        display: 'flex',
        justifyContent: 'center',
    },
});

const NO_FOLDER = '__none';
const MEMBERS_PAGE_SIZE = 25;
const USE_IN_CAMPAIGN_URL = '/campaigns?create=1';

interface SegmentEditProps {
    /** Segment uuid; `null` on /contacts/segments/new. */
    uuid: Uuid | null;
}

const formatDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

/**
 * docs/43-brevo-parity-plan.md §43.4.9 — create / edit a segment: name,
 * description, folder, the shared ConditionBuilder with live count, the
 * members preview of the saved segment and *Utiliser dans une campagne*.
 * Routes `contacts.segments.create` / `contacts.segments.edit` (`segments.manage`).
 */
export default function SegmentEdit({ uuid }: SegmentEditProps) {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();
    const canUseInCampaign = hasPermission(props.auth.user.permissions, 'campaigns.create');
    const { fieldCatalog, loading: catalogLoading, failed: catalogFailed } = useFieldCatalog();

    const [segment, setSegment] = useState<Segment | null>(null);
    const [loading, setLoading] = useState(uuid !== null);
    const [loadFailed, setLoadFailed] = useState<'not_found' | 'error' | null>(null);
    const [folders, setFolders] = useState<Folder[]>([]);

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [folderId, setFolderId] = useState<Uuid | null>(null);
    const [rules, setRules] = useState<RuleTree | null>(null);
    const [dirty, setDirty] = useState(false);

    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState<{ intent: 'success' | 'error' | 'warning'; text: string } | null>(null);
    const [attempted, setAttempted] = useState(false);

    const [members, setMembers] = useState<Contact[]>([]);
    const [membersCursor, setMembersCursor] = useState<string | null>(null);
    const [membersLoading, setMembersLoading] = useState(false);
    const [refreshingCount, setRefreshingCount] = useState(false);

    const applySegment = useCallback((s: Segment) => {
        setSegment(s);
        setName(s.name);
        setDescription(s.description ?? '');
        setFolderId(s.folder_id);
        setRules(s.rules);
        setDirty(false);
    }, []);

    useEffect(() => {
        fetchSegmentFolders()
            .then(setFolders)
            .catch(() => setFolders([]));
    }, []);

    useEffect(() => {
        if (!uuid) return;
        let cancelled = false;
        setLoading(true);
        fetchSegment(uuid)
            .then((s) => {
                if (!cancelled) applySegment(s);
            })
            .catch((error) => {
                if (!cancelled) setLoadFailed(errorStatus(error) === 404 ? 'not_found' : 'error');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [uuid, applySegment]);

    const loadMembers = useCallback(async (id: Uuid, cursor: string | null) => {
        setMembersLoading(true);
        try {
            const page = await fetchSegmentMembers(id, { cursor, per_page: MEMBERS_PAGE_SIZE });
            setMembers((current) => (cursor ? [...current, ...page.data] : page.data));
            setMembersCursor(page.meta.next_cursor);
        } catch {
            if (!cursor) setMembers([]);
            setMembersCursor(null);
        } finally {
            setMembersLoading(false);
        }
    }, []);

    const segmentId = segment?.id ?? null;
    const segmentUpdatedAt = segment?.updated_at ?? null;
    useEffect(() => {
        if (segmentId) void loadMembers(segmentId, null);
    }, [segmentId, segmentUpdatedAt, loadMembers]);

    const rulesComplete = isRuleTreeComplete(rules, fieldCatalog);
    const nameMissing = name.trim() === '';
    const folderLabel = folders.find((f) => f.id === folderId)?.name ?? t.common.noFolder;

    function edit<T>(setter: (value: T) => void) {
        return (value: T) => {
            setter(value);
            setDirty(true);
        };
    }

    async function save(event?: FormEvent) {
        event?.preventDefault();
        setAttempted(true);
        if (nameMissing || !rulesComplete || saving) return;
        setSaving(true);
        setNotice(null);
        setFieldErrors({});
        const input: SegmentInput = {
            name: name.trim(),
            description: description.trim() === '' ? null : description.trim(),
            folder_id: folderId,
            rules: rules ?? { version: 2, type: 'group', operator: 'and', children: [] },
        };
        try {
            if (segment) {
                applySegment(await updateSegment(segment.id, input));
                setNotice({ intent: 'success', text: t.segments.saved });
            } else {
                const created = await createSegment(input);
                setDirty(false);
                router.visit(`/contacts/segments/${created.id}`, { replace: true });
            }
        } catch (error) {
            const errors = validationErrors(error);
            if (errors) {
                setFieldErrors(errors);
                setNotice({ intent: 'error', text: errorMessage(error) ?? t.conditionBuilder.incomplete });
            } else {
                setNotice({ intent: 'error', text: errorMessage(error) ?? t.common.genericError });
            }
        } finally {
            setSaving(false);
        }
    }

    async function refreshCount() {
        if (!segment) return;
        setRefreshingCount(true);
        try {
            const result = await refreshSegmentCount(segment.id);
            setSegment((s) => (s ? { ...s, contact_count: result.count, count_computed_at: result.computed_at } : s));
        } catch (error) {
            setNotice({ intent: 'error', text: errorMessage(error) ?? t.common.genericError });
        } finally {
            setRefreshingCount(false);
        }
    }

    const memberColumns: TableColumnDefinition<Contact>[] = [
        createTableColumn<Contact>({ columnId: 'email', renderHeaderCell: () => t.contacts.columns.email, renderCell: (c) => c.email }),
        createTableColumn<Contact>({
            columnId: 'first_name',
            renderHeaderCell: () => t.contacts.columns.first_name,
            renderCell: (c) => c.first_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'last_name',
            renderHeaderCell: () => t.contacts.columns.last_name,
            renderCell: (c) => c.last_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'company_name',
            renderHeaderCell: () => t.contacts.columns.company_name,
            renderCell: (c) => c.company_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'status',
            renderHeaderCell: () => t.contacts.columns.status,
            renderCell: (c) => t.contacts.statusLabels[c.status as keyof typeof t.contacts.statusLabels] ?? c.status,
        }),
        createTableColumn<Contact>({
            columnId: 'created_at',
            renderHeaderCell: () => t.contacts.columns.created_at,
            renderCell: (c) => formatDateTime(c.created_at),
        }),
    ];

    const title = uuid ? t.segments.editTitle : t.segments.createSegment;

    if (loading || catalogLoading) {
        return (
            <AppShell>
                <Head title={title} />
                <Spinner label={t.common.loading} />
            </AppShell>
        );
    }

    if (loadFailed) {
        return (
            <AppShell>
                <Head title={title} />
                <MessageBar intent="error">
                    <MessageBarBody>{loadFailed === 'not_found' ? t.common.notFound : t.common.genericError}</MessageBarBody>
                </MessageBar>
                <Button appearance="subtle" icon={<ArrowLeftRegular />} onClick={() => router.visit('/contacts/segments')}>
                    {t.common.back}
                </Button>
            </AppShell>
        );
    }

    return (
        <AppShell>
            <Head title={segment ? `${segment.name} — ${t.segments.title}` : title} />
            <form onSubmit={save}>
                <div className={styles.header}>
                    <div className={styles.titleBlock}>
                        <Button
                            appearance="subtle"
                            icon={<ArrowLeftRegular />}
                            aria-label={t.common.back}
                            onClick={() => router.visit('/contacts/segments')}
                        />
                        <Title2 as="h1">{segment ? segment.name : title}</Title2>
                        {segment?.recipient_list_id && <Badge appearance="tint">{t.segments.legacy}</Badge>}
                    </div>
                    <div className={styles.actions}>
                        {dirty && <Text className={styles.muted}>{t.common.unsavedChanges}</Text>}
                        {segment && canUseInCampaign && (
                            <Button appearance="secondary" icon={<MailRegular />} onClick={() => router.visit(USE_IN_CAMPAIGN_URL)}>
                                {t.segments.useInCampaign}
                            </Button>
                        )}
                        <Button
                            appearance="primary"
                            type="submit"
                            icon={saving ? <Spinner size="tiny" /> : <SaveRegular />}
                            disabled={saving || nameMissing || !rulesComplete}
                        >
                            {saving ? t.common.saving : t.common.save}
                        </Button>
                    </div>
                </div>

                {notice && (
                    <MessageBar intent={notice.intent} style={{ marginBottom: tokens.spacingVerticalM }}>
                        <MessageBarBody>{notice.text}</MessageBarBody>
                    </MessageBar>
                )}
                {catalogFailed && (
                    <MessageBar intent="warning" style={{ marginBottom: tokens.spacingVerticalM }}>
                        <MessageBarBody>{t.common.genericError}</MessageBarBody>
                    </MessageBar>
                )}

                <section className={styles.card} aria-label={title}>
                    <div className={styles.detailsGrid}>
                        <Field
                            label={t.segments.name}
                            required
                            validationMessage={fieldErrors.name ?? (attempted && nameMissing ? t.common.required : undefined)}
                            validationState={fieldErrors.name || (attempted && nameMissing) ? 'error' : 'none'}
                        >
                            <Input value={name} maxLength={255} onChange={(_, data) => edit(setName)(data.value)} />
                        </Field>
                        <Field
                            label={t.segments.folder}
                            validationMessage={fieldErrors.folder_id}
                            validationState={fieldErrors.folder_id ? 'error' : 'none'}
                        >
                            <Dropdown
                                selectedOptions={[folderId ?? NO_FOLDER]}
                                value={folderLabel}
                                onOptionSelect={(_, data) =>
                                    edit(setFolderId)(!data.optionValue || data.optionValue === NO_FOLDER ? null : data.optionValue)
                                }
                            >
                                <Option value={NO_FOLDER}>{t.common.noFolder}</Option>
                                {folders.map((folder) => (
                                    <Option key={folder.id} value={folder.id}>
                                        {folder.name}
                                    </Option>
                                ))}
                            </Dropdown>
                        </Field>
                        <Field
                            className={styles.fullWidth}
                            label={`${t.segments.description} (${t.common.optional})`}
                            validationMessage={fieldErrors.description}
                            validationState={fieldErrors.description ? 'error' : 'none'}
                        >
                            <Textarea value={description} resize="vertical" onChange={(_, data) => edit(setDescription)(data.value)} />
                        </Field>
                    </div>
                    {segment && (segment.used_in.campaigns > 0 || segment.used_in.automations > 0) && (
                        <Text className={styles.muted}>
                            {format(t.segments.usedIn, segment.used_in.campaigns, segment.used_in.automations)}
                        </Text>
                    )}
                </section>

                <section className={styles.card} aria-labelledby="segment-conditions-title">
                    <Title3 as="h2" id="segment-conditions-title">
                        {t.segments.conditions}
                    </Title3>
                    <div className={styles.builderSurface}>
                        <ConditionBuilder
                            value={rules}
                            onChange={edit(setRules)}
                            fieldCatalog={fieldCatalog}
                            onCount={countRuleTree}
                            optionSources={defaultConditionOptionSources}
                            errors={ruleTreeErrors(fieldErrors)}
                            disabled={saving}
                            variant="full"
                        />
                    </div>
                </section>
            </form>

            {segment && (
                <section className={styles.card} aria-labelledby="segment-members-title">
                    <div className={styles.sectionHeader}>
                        <div>
                            <Title3 as="h2" id="segment-members-title">
                                {t.segments.members}
                            </Title3>
                            <Text block className={styles.muted}>
                                {segment.contact_count !== null
                                    ? `${segment.contact_count.toLocaleString('fr-FR')} ${t.segments.contacts.toLowerCase()}`
                                    : t.common.emptyValue}
                                {segment.count_computed_at
                                    ? ` · ${format(t.segments.countComputedAt, formatDateTime(segment.count_computed_at))}`
                                    : ''}
                            </Text>
                        </div>
                        <Button
                            appearance="secondary"
                            icon={refreshingCount ? <Spinner size="tiny" /> : <ArrowClockwiseRegular />}
                            disabled={refreshingCount}
                            onClick={() => void refreshCount()}
                        >
                            {t.segments.refreshCount}
                        </Button>
                    </div>
                    {dirty && <Text className={styles.muted}>{t.common.unsavedChanges}</Text>}
                    {membersLoading && members.length === 0 ? (
                        <Spinner label={t.common.loading} />
                    ) : members.length === 0 ? (
                        <Text>{t.contacts.noMatch}</Text>
                    ) : (
                        <div className={styles.gridScroll}>
                            <DataGrid items={members} columns={memberColumns} getRowId={(c: Contact) => c.id} className={styles.grid}>
                                <DataGridHeader>
                                    <DataGridRow>
                                        {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                                    </DataGridRow>
                                </DataGridHeader>
                                <DataGridBody<Contact>>
                                    {({ item, rowId }) => (
                                        <DataGridRow<Contact> key={rowId}>
                                            {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                        </DataGridRow>
                                    )}
                                </DataGridBody>
                            </DataGrid>
                        </div>
                    )}
                    {membersCursor && (
                        <div className={styles.footer}>
                            <Button
                                appearance="secondary"
                                disabled={membersLoading}
                                onClick={() => void loadMembers(segment.id, membersCursor)}
                            >
                                {membersLoading ? t.common.loading : t.common.loadMore}
                            </Button>
                        </div>
                    )}
                </section>
            )}
        </AppShell>
    );
}
