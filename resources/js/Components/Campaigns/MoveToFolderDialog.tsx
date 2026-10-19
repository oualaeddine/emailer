import { useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Field,
    Option,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { Folder } from '@/Lib/types/common';
import { apiErrorMessage } from '@/Components/Campaigns/campaignUi';

export interface MoveToFolderDialogProps {
    open: boolean;
    folders: Folder[];
    initialFolderId?: string | null;
    onClose: () => void;
    onSubmit: (folderId: string | null) => Promise<void>;
}

const NO_FOLDER = '__none__';

/** "Déplacer dans un dossier" for one row (PATCH folder_id) or the bulk bar (bulk move). */
export function MoveToFolderDialog({ open, folders, initialFolderId = null, onClose, onSubmit }: MoveToFolderDialogProps) {
    const t = useText();
    const [value, setValue] = useState<string>(initialFolderId ?? NO_FOLDER);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setValue(initialFolderId ?? NO_FOLDER);
            setError(null);
        }
    }, [open, initialFolderId]);

    const selectedLabel = value === NO_FOLDER ? t.common.noFolder : (folders.find((f) => f.id === value)?.name ?? '');

    async function handleSubmit() {
        setBusy(true);
        setError(null);
        try {
            await onSubmit(value === NO_FOLDER ? null : value);
        } catch (err) {
            setError(apiErrorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface style={{ maxWidth: '420px' }}>
                <DialogBody>
                    <DialogTitle>{t.common.moveToFolder}</DialogTitle>
                    <DialogContent>
                        <Field label={t.common.folder} validationMessage={error ?? undefined}>
                            <Dropdown
                                value={selectedLabel}
                                selectedOptions={[value]}
                                onOptionSelect={(_, data) => setValue(data.optionValue ?? NO_FOLDER)}
                            >
                                <Option value={NO_FOLDER}>{t.common.noFolder}</Option>
                                {folders.map((folder) => (
                                    <Option key={folder.id} value={folder.id}>
                                        {folder.name}
                                    </Option>
                                ))}
                            </Dropdown>
                        </Field>
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onClose} disabled={busy}>
                            {t.common.cancel}
                        </Button>
                        <Button appearance="primary" onClick={() => void handleSubmit()} disabled={busy}>
                            {t.common.move}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
