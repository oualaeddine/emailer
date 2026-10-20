import { useCallback, useEffect, useId, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    CardPreview,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    Field,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    MessageBar,
    MessageBarBody,
    OverlayDrawer,
    Select,
    Tab,
    TabList,
    Text,
    Toast,
    ToastTitle,
    Toaster,
    makeStyles,
    tokens,
    useToastController,
} from '@fluentui/react-components';
import {
    AddRegular,
    ArchiveRegular,
    ArrowCounterclockwiseRegular,
    ArrowUndoRegular,
    CopyRegular,
    DeleteRegular,
    DismissRegular,
    DocumentRegular,
    EditRegular,
    EyeRegular,
    FolderAddRegular,
    FolderArrowRightRegular,
    FolderRegular,
    HistoryRegular,
    MailRegular,
    MoreHorizontalRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { PageHelp } from '@/Components/Help/PageHelp';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { HtmlPreview } from '@/Components/Common/HtmlPreview';
import { useText } from '@/Hooks/useText';
import {
    archiveTemplate,
    createTemplate,
    createTemplateFolder,
    deleteTemplate,
    deleteTemplateFolder,
    duplicateTemplate,
    fetchTemplate,
    fetchTemplateFolders,
    fetchTemplateGallery,
    fetchTemplateVersions,
    fetchTemplatesV2,
    moveTemplate,
    regenerateTemplateThumbnail,
    renameTemplateFolder,
    restoreTemplateVersion,
    testSendTemplate,
    unarchiveTemplate,
} from '@/Lib/api/templates';
import type { Folder, Uuid } from '@/Lib/types/common';
import type { TemplateGalleryCategoryCount, TemplateV2, TemplateVersion } from '@/Lib/types/templates';
import { TemplateFormDialog } from '@/Pages/Templates/TemplateFormDialog';

const useStyles = makeStyles({
    layout: {
        display: 'grid',
        gridTemplateColumns: 'minmax(180px, 240px) 1fr',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 720px)': { gridTemplateColumns: '1fr' },
    },
    tree: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalXS },
    treeRow: { display: 'flex', alignItems: 'center', justifyContent: 'space-between' },
    treeButton: { flexGrow: 1, justifyContent: 'flex-start' },
    toolbar: { display: 'flex', gap: tokens.spacingHorizontalS, marginBottom: tokens.spacingVerticalM, flexWrap: 'wrap' },
    search: { flexGrow: 1, maxWidth: '360px' },
    tabs: { marginBottom: tokens.spacingVerticalM },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    card: {
        transitionProperty: 'box-shadow, transform',
        transitionDuration: tokens.durationNormal,
        transitionTimingFunction: tokens.curveEasyEase,
        ':hover': { boxShadow: tokens.shadow8, transform: 'translateY(-2px)' },
    },
    cardTop: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' },
    thumb: {
        height: '220px',
        overflow: 'hidden',
        backgroundColor: tokens.colorNeutralBackground3,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        color: tokens.colorNeutralForeground3,
        textAlign: 'center',
        padding: tokens.spacingHorizontalS,
    },
    thumbImg: { width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'top' },
    fields: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    previewSurface: { maxWidth: '720px', width: '95vw' },
    previewFrame: { height: '70vh', backgroundColor: tokens.colorNeutralBackground1 },
    version: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
        paddingBlock: tokens.spacingVerticalS,
        borderBottom: `1px solid ${tokens.colorNeutralStroke2}`,
    },
});

type MainTab = 'mine' | 'gallery';
type FolderDialog = { mode: 'create' | 'rename'; folder?: Folder } | null;

function errorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string } | undefined;
        if (data?.message) return data.message;
    }

    return fallback;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
}

/**
 * docs/43 §43.4.5 — Templates index (WP-62): *Mes modèles* / *Galerie* tabs,
 * folders, thumbnail grid, kebab actions and the versions drawer.
 */
export default function TemplatesIndex() {
    const styles = useStyles();
    const t = useText();
    const toasterId = useId();
    const { dispatchToast } = useToastController(toasterId);

    const [tab, setTab] = useState<MainTab>('mine');
    const [error, setError] = useState<string | null>(null);

    const [folders, setFolders] = useState<Folder[]>([]);
    const [folderFilter, setFolderFilter] = useState<Uuid | 'none' | null>(null);
    const [q, setQ] = useState('');
    const [templates, setTemplates] = useState<TemplateV2[]>([]);
    const [gallery, setGallery] = useState<TemplateV2[]>([]);
    const [categories, setCategories] = useState<TemplateGalleryCategoryCount[]>([]);
    const [category, setCategory] = useState<string>('all');

    const [createOpen, setCreateOpen] = useState(false);
    const [folderDialog, setFolderDialog] = useState<FolderDialog>(null);
    const [folderName, setFolderName] = useState('');
    const [moving, setMoving] = useState<TemplateV2 | null>(null);
    const [moveTarget, setMoveTarget] = useState('');
    const [testing, setTesting] = useState<TemplateV2 | null>(null);
    const [testEmail, setTestEmail] = useState('');
    const [deleting, setDeleting] = useState<TemplateV2 | null>(null);
    const [previewing, setPreviewing] = useState<{ name: string; html: string } | null>(null);
    const [versionsFor, setVersionsFor] = useState<TemplateV2 | null>(null);
    const [versions, setVersions] = useState<TemplateVersion[]>([]);

    const notify = useCallback(
        (text: string) => {
            dispatchToast(
                <Toast>
                    <ToastTitle>{text}</ToastTitle>
                </Toast>,
                { intent: 'success' },
            );
        },
        [dispatchToast],
    );

    const loadFolders = useCallback(async () => {
        setFolders(await fetchTemplateFolders());
    }, []);

    const loadTemplates = useCallback(async () => {
        try {
            const result = await fetchTemplatesV2({
                is_gallery: false,
                folder_id: folderFilter ?? undefined,
                q: q.trim() || undefined,
                sort: '-updated_at',
            });
            setTemplates(result.data);
        } catch (e) {
            setError(errorMessage(e, t.common.genericError));
        }
    }, [folderFilter, q, t.common.genericError]);

    const loadGallery = useCallback(async () => {
        try {
            const result = await fetchTemplateGallery(category === 'all' ? undefined : category);
            setGallery(result.data);
            setCategories(result.meta.categories);
        } catch (e) {
            setError(errorMessage(e, t.common.genericError));
        }
    }, [category, t.common.genericError]);

    useEffect(() => {
        void loadFolders();
    }, [loadFolders]);

    useEffect(() => {
        if (tab !== 'mine') return;
        const timer = window.setTimeout(() => void loadTemplates(), q ? 250 : 0);

        return () => window.clearTimeout(timer);
    }, [tab, loadTemplates, q]);

    useEffect(() => {
        if (tab === 'gallery') void loadGallery();
    }, [tab, loadGallery]);

    async function run(action: () => Promise<void>) {
        setError(null);
        try {
            await action();
        } catch (e) {
            setError(errorMessage(e, t.common.genericError));
        }
    }

    async function handleCreate(values: { name: string; category?: string; html_content: string }) {
        await createTemplate({ ...values });
        setCreateOpen(false);
        await Promise.all([loadTemplates(), loadFolders()]);
    }

    async function submitFolder() {
        await run(async () => {
            if (folderDialog?.mode === 'rename' && folderDialog.folder) {
                await renameTemplateFolder(folderDialog.folder.id, { name: folderName });
            } else {
                await createTemplateFolder({ name: folderName });
            }
            setFolderDialog(null);
            await loadFolders();
        });
    }

    async function removeFolder(folder: Folder) {
        if (!window.confirm(`${t.common.deleteConfirmTitle} — ${folder.name}\n${t.common.deleteConfirmBody}`)) return;
        await run(async () => {
            await deleteTemplateFolder(folder.id);
            if (folderFilter === folder.id) setFolderFilter(null);
            await Promise.all([loadFolders(), loadTemplates()]);
        });
    }

    async function duplicate(template: TemplateV2) {
        await run(async () => {
            await duplicateTemplate(template.id, template.is_gallery ? {} : { folder_id: template.folder_id });
            notify(template.is_gallery ? t.templates.copiedToMine : t.templates.duplicated);
            await Promise.all([loadTemplates(), loadFolders()]);
        });
    }

    async function submitMove() {
        if (!moving) return;
        await run(async () => {
            await moveTemplate(moving.id, moveTarget || null);
            setMoving(null);
            await Promise.all([loadTemplates(), loadFolders()]);
        });
    }

    async function submitTest() {
        if (!testing) return;
        await run(async () => {
            await testSendTemplate(testing.id, { emails: [testEmail.trim()] });
            setTesting(null);
            setTestEmail('');
            notify(t.templates.testSent);
        });
    }

    async function toggleArchive(template: TemplateV2) {
        await run(async () => {
            await (template.is_archived ? unarchiveTemplate(template.id) : archiveTemplate(template.id));
            await Promise.all([loadTemplates(), loadFolders()]);
        });
    }

    async function confirmDelete() {
        if (!deleting) return;
        const target = deleting;
        setDeleting(null);
        await run(async () => {
            try {
                await deleteTemplate(target.id);
            } catch (e) {
                if (axios.isAxiosError(e) && e.response?.status === 409) {
                    setError(t.templates.cannotDelete);
                    return;
                }
                throw e;
            }
            await Promise.all([loadTemplates(), loadFolders()]);
        });
    }

    async function openPreview(template: TemplateV2) {
        await run(async () => {
            const detail = await fetchTemplate(template.id);
            setPreviewing({ name: detail.name, html: detail.html_content });
        });
    }

    async function openVersions(template: TemplateV2) {
        setVersionsFor(template);
        setVersions([]);
        await run(async () => setVersions(await fetchTemplateVersions(template.id)));
    }

    async function restore(version: TemplateVersion) {
        if (!versionsFor) return;
        if (!window.confirm(t.templates.restoreConfirm.replace('%d', String(version.version_number)))) return;
        await run(async () => {
            await restoreTemplateVersion(versionsFor.id, version.version_number);
            notify(t.templates.restored);
            setVersions(await fetchTemplateVersions(versionsFor.id));
            await loadTemplates();
        });
    }

    async function regenerate(template: TemplateV2) {
        await run(async () => {
            await regenerateTemplateThumbnail(template.id);
            notify(t.templates.thumbnailPending);
        });
    }

    function renderThumb(template: TemplateV2) {
        return (
            <CardPreview className={styles.thumb}>
                {template.thumbnail_url ? (
                    <img className={styles.thumbImg} src={template.thumbnail_url} alt={template.name} />
                ) : (
                    <div>
                        <DocumentRegular fontSize={32} />
                        <Text as="p" size={200}>
                            {t.templates.thumbnailPending}
                        </Text>
                    </div>
                )}
            </CardPreview>
        );
    }

    function renderMineCard(template: TemplateV2) {
        return (
            <Card key={template.id} className={styles.card}>
                <div className={styles.cardTop}>
                    <CardHeader
                        header={<Text weight="semibold">{template.name}</Text>}
                        description={template.category ?? undefined}
                    />
                    <Menu>
                        <MenuTrigger disableButtonEnhancement>
                            <Button appearance="transparent" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                        </MenuTrigger>
                        <MenuPopover>
                            <MenuList>
                                <MenuItem icon={<EditRegular />} onClick={() => router.visit(`/templates/${template.id}/edit`)}>
                                    {t.templates.actions.edit}
                                </MenuItem>
                                <MenuItem icon={<CopyRegular />} onClick={() => void duplicate(template)}>
                                    {t.templates.actions.duplicate}
                                </MenuItem>
                                <MenuItem icon={<MailRegular />} onClick={() => setTesting(template)}>
                                    {t.templates.actions.sendTest}
                                </MenuItem>
                                <MenuItem
                                    icon={<FolderArrowRightRegular />}
                                    onClick={() => {
                                        setMoveTarget(template.folder_id ?? '');
                                        setMoving(template);
                                    }}
                                >
                                    {t.templates.actions.move}
                                </MenuItem>
                                <MenuItem icon={<EyeRegular />} onClick={() => void openPreview(template)}>
                                    {t.templates.actions.preview}
                                </MenuItem>
                                <MenuItem icon={<HistoryRegular />} onClick={() => void openVersions(template)}>
                                    {t.templates.versions}
                                </MenuItem>
                                <MenuItem icon={<ArrowCounterclockwiseRegular />} onClick={() => void regenerate(template)}>
                                    {t.templates.actions.regenerateThumbnail}
                                </MenuItem>
                                <MenuItem icon={<ArchiveRegular />} onClick={() => void toggleArchive(template)}>
                                    {template.is_archived ? t.templates.actions.unarchive : t.templates.actions.archive}
                                </MenuItem>
                                <MenuItem icon={<DeleteRegular />} onClick={() => setDeleting(template)}>
                                    {t.templates.actions.delete}
                                </MenuItem>
                            </MenuList>
                        </MenuPopover>
                    </Menu>
                </div>
                {renderThumb(template)}
                <Text size={200}>{t.templates.usage.replace('%d', String(template.usage_count))}</Text>
                <Text size={200}>{t.templates.lastModified.replace('%s', formatDate(template.updated_at))}</Text>
                {template.is_archived && (
                    <Badge appearance="tint" color="subtle">
                        {t.templates.archived}
                    </Badge>
                )}
            </Card>
        );
    }

    function renderGalleryCard(template: TemplateV2) {
        return (
            <Card key={template.id} className={styles.card}>
                <div className={styles.cardTop}>
                    <CardHeader
                        header={<Text weight="semibold">{template.name}</Text>}
                        description={template.gallery_category ? t.templates.galleryCategories[template.gallery_category] : undefined}
                    />
                    <Menu>
                        <MenuTrigger disableButtonEnhancement>
                            <Button appearance="transparent" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                        </MenuTrigger>
                        <MenuPopover>
                            <MenuList>
                                <MenuItem icon={<EyeRegular />} onClick={() => void openPreview(template)}>
                                    {t.templates.actions.preview}
                                </MenuItem>
                                <MenuItem icon={<CopyRegular />} onClick={() => void duplicate(template)}>
                                    {t.templates.actions.duplicate}
                                </MenuItem>
                            </MenuList>
                        </MenuPopover>
                    </Menu>
                </div>
                {renderThumb(template)}
                <Button appearance="primary" onClick={() => void duplicate(template)}>
                    {t.templates.actions.useTemplate}
                </Button>
            </Card>
        );
    }

    return (
        <AppShell>
            <Head title={t.templates.title} />
            <Toaster toasterId={toasterId} />
            <PageHelp
                topic="templates"
                title={t.templates.title}
                actions={
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
                        {t.templates.createTemplate}
                    </Button>
                }
            />
            {error && (
                <MessageBar intent="error" style={{ marginBottom: tokens.spacingVerticalM }}>
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <TabList className={styles.tabs} selectedValue={tab} onTabSelect={(_, data) => setTab(data.value as MainTab)}>
                <Tab value="mine">{t.templates.tabs.mine}</Tab>
                <Tab value="gallery">{t.templates.tabs.gallery}</Tab>
            </TabList>

            {tab === 'mine' ? (
                <div className={styles.layout}>
                    <nav className={styles.tree} aria-label={t.common.folders}>
                        <Button
                            appearance={folderFilter === null ? 'primary' : 'subtle'}
                            className={styles.treeButton}
                            onClick={() => setFolderFilter(null)}
                        >
                            {t.common.all}
                        </Button>
                        <Button
                            appearance={folderFilter === 'none' ? 'primary' : 'subtle'}
                            className={styles.treeButton}
                            onClick={() => setFolderFilter('none')}
                        >
                            {t.common.noFolder}
                        </Button>
                        {folders.map((folder) => (
                            <div key={folder.id} className={styles.treeRow}>
                                <Button
                                    appearance={folderFilter === folder.id ? 'primary' : 'subtle'}
                                    className={styles.treeButton}
                                    icon={<FolderRegular />}
                                    onClick={() => setFolderFilter(folder.id)}
                                >
                                    {folder.name} ({folder.items_count})
                                </Button>
                                <Menu>
                                    <MenuTrigger disableButtonEnhancement>
                                        <Button appearance="transparent" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                                    </MenuTrigger>
                                    <MenuPopover>
                                        <MenuList>
                                            <MenuItem
                                                icon={<EditRegular />}
                                                onClick={() => {
                                                    setFolderName(folder.name);
                                                    setFolderDialog({ mode: 'rename', folder });
                                                }}
                                            >
                                                {t.common.renameFolder}
                                            </MenuItem>
                                            <MenuItem icon={<DeleteRegular />} onClick={() => void removeFolder(folder)}>
                                                {t.common.deleteFolder}
                                            </MenuItem>
                                        </MenuList>
                                    </MenuPopover>
                                </Menu>
                            </div>
                        ))}
                        <Button
                            appearance="subtle"
                            icon={<FolderAddRegular />}
                            onClick={() => {
                                setFolderName('');
                                setFolderDialog({ mode: 'create' });
                            }}
                        >
                            {t.common.newFolder}
                        </Button>
                    </nav>
                    <div>
                        <div className={styles.toolbar}>
                            <Input
                                className={styles.search}
                                value={q}
                                placeholder={t.templates.searchPlaceholder}
                                aria-label={t.common.search}
                                onChange={(_, data) => setQ(data.value)}
                            />
                        </div>
                        {templates.length === 0 ? (
                            <EmptyState icon={DocumentRegular} title={t.templates.emptyTitle} body={t.templates.emptyBody} />
                        ) : (
                            <div className={styles.grid}>{templates.map(renderMineCard)}</div>
                        )}
                    </div>
                </div>
            ) : (
                <div>
                    <TabList
                        className={styles.tabs}
                        size="small"
                        selectedValue={category}
                        onTabSelect={(_, data) => setCategory(String(data.value))}
                    >
                        <Tab value="all">{t.templates.allCategories}</Tab>
                        {categories.map((c) => (
                            <Tab key={c.key} value={c.key}>
                                {t.templates.galleryCategories[c.key]} ({c.count})
                            </Tab>
                        ))}
                    </TabList>
                    {gallery.length === 0 ? (
                        <EmptyState icon={DocumentRegular} title={t.templates.galleryEmpty} body="" />
                    ) : (
                        <div className={styles.grid}>{gallery.map(renderGalleryCard)}</div>
                    )}
                </div>
            )}

            <TemplateFormDialog open={createOpen} onOpenChange={setCreateOpen} onSubmit={handleCreate} />

            <Dialog open={folderDialog !== null} onOpenChange={(_, data) => !data.open && setFolderDialog(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{folderDialog?.mode === 'rename' ? t.common.renameFolder : t.common.newFolder}</DialogTitle>
                        <DialogContent>
                            <Field label={t.common.folderName} required>
                                <Input value={folderName} onChange={(_, data) => setFolderName(data.value)} maxLength={100} />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setFolderDialog(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={folderName.trim() === ''} onClick={() => void submitFolder()}>
                                {folderDialog?.mode === 'rename' ? t.common.save : t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={moving !== null} onOpenChange={(_, data) => !data.open && setMoving(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.moveToFolder}</DialogTitle>
                        <DialogContent>
                            <Field label={t.common.folder}>
                                <Select value={moveTarget} onChange={(_, data) => setMoveTarget(data.value)}>
                                    <option value="">{t.common.noFolder}</option>
                                    {folders.map((folder) => (
                                        <option key={folder.id} value={folder.id}>
                                            {folder.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setMoving(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void submitMove()}>
                                {t.common.move}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={testing !== null} onOpenChange={(_, data) => !data.open && setTesting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.templates.actions.sendTest}</DialogTitle>
                        <DialogContent className={styles.fields}>
                            <Field label={t.auth.email} required>
                                <Input type="email" value={testEmail} onChange={(_, data) => setTestEmail(data.value)} />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setTesting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={testEmail.trim() === ''} onClick={() => void submitTest()}>
                                {t.templates.actions.sendTest}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, data) => !data.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.deleteConfirmTitle}</DialogTitle>
                        <DialogContent>
                            <Text>
                                {deleting?.name} — {t.common.deleteConfirmBody}
                            </Text>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setDeleting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void confirmDelete()}>
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={previewing !== null} onOpenChange={(_, data) => !data.open && setPreviewing(null)}>
                <DialogSurface className={styles.previewSurface}>
                    <DialogBody>
                        <DialogTitle>{previewing?.name}</DialogTitle>
                        <DialogContent>
                            {previewing && <HtmlPreview html={previewing.html} title={previewing.name} className={styles.previewFrame} />}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setPreviewing(null)}>
                                {t.common.close}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <OverlayDrawer open={versionsFor !== null} onOpenChange={(_, data) => !data.open && setVersionsFor(null)} position="end">
                <DrawerHeader>
                    <DrawerHeaderTitle
                        action={
                            <Button
                                appearance="subtle"
                                aria-label={t.common.close}
                                icon={<DismissRegular />}
                                onClick={() => setVersionsFor(null)}
                            />
                        }
                    >
                        {t.templates.versions}
                    </DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody>
                    {versions.map((version) => (
                        <div key={version.id} className={styles.version}>
                            <div>
                                <Text weight="semibold" block>
                                    {t.templates.versionNumber.replace('%d', String(version.version_number))}
                                </Text>
                                <Text size={200}>
                                    {formatDate(version.created_at)}
                                    {version.author ? ` · ${version.author.name}` : ''}
                                </Text>
                            </div>
                            {version.is_current ? (
                                <Badge appearance="tint" color="brand">
                                    {t.templates.currentVersion}
                                </Badge>
                            ) : (
                                <Button size="small" icon={<ArrowUndoRegular />} onClick={() => void restore(version)}>
                                    {t.templates.restoreVersion}
                                </Button>
                            )}
                        </div>
                    ))}
                </DrawerBody>
            </OverlayDrawer>
        </AppShell>
    );
}
