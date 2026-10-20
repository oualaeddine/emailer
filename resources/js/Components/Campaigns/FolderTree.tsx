import { useEffect, useState } from 'react';
import {
    Button,
    CounterBadge,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    AddRegular,
    DeleteRegular,
    FolderOpenRegular,
    FolderRegular,
    MoreHorizontalRegular,
    RenameRegular,
} from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { Folder } from '@/Lib/types/common';
import { ConfirmActionDialog } from '@/Components/Campaigns/ConfirmActionDialog';
import { apiErrorMessage, apiValidationErrors } from '@/Components/Campaigns/campaignUi';

/** `all` = every folder, `none` = campaigns without folder, otherwise a folder uuid. */
export type FolderSelection = 'all' | 'none' | string;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    heading: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingBottom: tokens.spacingVerticalXS,
    },
    row: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXXS,
        borderRadius: tokens.borderRadiusMedium,
    },
    rowSelected: {
        backgroundColor: tokens.colorNeutralBackground1Selected,
    },
    item: {
        flexGrow: 1,
        justifyContent: 'flex-start',
        minWidth: 0,
        fontWeight: tokens.fontWeightRegular,
    },
    itemSelected: {
        fontWeight: tokens.fontWeightSemibold,
    },
    label: {
        flexGrow: 1,
        textAlign: 'left',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
});

export interface FolderTreeProps {
    folders: Folder[];
    selected: FolderSelection;
    onSelect: (selection: FolderSelection) => void;
    onCreate: (name: string) => Promise<void>;
    onRename: (folder: Folder, name: string) => Promise<void>;
    onDelete: (folder: Folder) => Promise<void>;
    canManage?: boolean;
}

/** Campaign folders sidebar of Campaigns/Index (`campaign_folders`, docs/45 §45.3). */
export function FolderTree({ folders, selected, onSelect, onCreate, onRename, onDelete, canManage = true }: FolderTreeProps) {
    const styles = useStyles();
    const t = useText();
    const [nameDialog, setNameDialog] = useState<{ mode: 'create' } | { mode: 'rename'; folder: Folder } | null>(null);
    const [deleting, setDeleting] = useState<Folder | null>(null);

    function renderRow(key: FolderSelection, label: string, count: number | null, folder?: Folder) {
        const isSelected = selected === key;

        return (
            <div key={key} className={mergeClasses(styles.row, isSelected && styles.rowSelected)}>
                <Button
                    appearance="subtle"
                    className={mergeClasses(styles.item, isSelected && styles.itemSelected)}
                    icon={isSelected ? <FolderOpenRegular /> : <FolderRegular />}
                    aria-current={isSelected ? 'true' : undefined}
                    onClick={() => onSelect(key)}
                >
                    <span className={styles.label}>{label}</span>
                    {count !== null && <CounterBadge count={count} showZero appearance="ghost" color="informative" size="small" />}
                </Button>
                {folder && canManage && (
                    <Menu positioning="below-end">
                        <MenuTrigger disableButtonEnhancement>
                            <Button appearance="transparent" size="small" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                        </MenuTrigger>
                        <MenuPopover>
                            <MenuList>
                                <MenuItem icon={<RenameRegular />} onClick={() => setNameDialog({ mode: 'rename', folder })}>
                                    {t.common.renameFolder}
                                </MenuItem>
                                <MenuItem icon={<DeleteRegular />} onClick={() => setDeleting(folder)}>
                                    {t.common.deleteFolder}
                                </MenuItem>
                            </MenuList>
                        </MenuPopover>
                    </Menu>
                )}
            </div>
        );
    }

    return (
        <nav className={styles.root} aria-label={t.common.folders}>
            <div className={styles.heading}>
                <Text weight="semibold">{t.common.folders}</Text>
                {canManage && (
                    <Button
                        appearance="subtle"
                        size="small"
                        icon={<AddRegular />}
                        aria-label={t.common.newFolder}
                        title={t.common.newFolder}
                        onClick={() => setNameDialog({ mode: 'create' })}
                    />
                )}
            </div>
            {renderRow('all', t.campaigns.filters.allFolders, null)}
            {renderRow('none', t.common.noFolder, null)}
            {folders.map((folder) => renderRow(folder.id, folder.name, folder.items_count, folder))}

            <FolderNameDialog
                open={nameDialog !== null}
                title={nameDialog?.mode === 'rename' ? t.common.renameFolder : t.common.newFolder}
                initialName={nameDialog?.mode === 'rename' ? nameDialog.folder.name : ''}
                onClose={() => setNameDialog(null)}
                onSubmit={async (name) => {
                    if (nameDialog?.mode === 'rename') {
                        await onRename(nameDialog.folder, name);
                    } else {
                        await onCreate(name);
                    }
                    setNameDialog(null);
                }}
            />
            <ConfirmActionDialog
                open={deleting !== null}
                title={t.common.deleteFolder}
                body={t.common.deleteConfirmBody}
                confirmLabel={t.common.delete}
                danger
                onClose={() => setDeleting(null)}
                onConfirm={async () => {
                    if (deleting) {
                        await onDelete(deleting);
                    }
                    setDeleting(null);
                }}
            />
        </nav>
    );
}

interface FolderNameDialogProps {
    open: boolean;
    title: string;
    initialName: string;
    onClose: () => void;
    onSubmit: (name: string) => Promise<void>;
}

function FolderNameDialog({ open, title, initialName, onClose, onSubmit }: FolderNameDialogProps) {
    const t = useText();
    const [name, setName] = useState(initialName);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (open) {
            setName(initialName);
            setError(null);
        }
    }, [open, initialName]);

    async function handleSubmit() {
        const trimmed = name.trim();
        if (trimmed.length === 0 || trimmed.length > 100) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            await onSubmit(trimmed);
        } catch (err) {
            setError(apiValidationErrors(err).name ?? apiErrorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface style={{ maxWidth: '420px' }}>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void handleSubmit();
                    }}
                >
                    <DialogBody>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogContent>
                            <Field label={t.common.folderName} required validationMessage={error ?? undefined}>
                                <Input value={name} maxLength={100} autoFocus onChange={(_, data) => setName(data.value)} />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={onClose} disabled={busy}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={busy || name.trim().length === 0}>
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
