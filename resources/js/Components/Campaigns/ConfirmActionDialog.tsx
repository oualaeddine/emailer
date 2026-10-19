import { useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Spinner,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    surface: { maxWidth: '480px' },
    danger: {
        backgroundColor: tokens.colorPaletteRedBackground3,
        ':hover': { backgroundColor: tokens.colorPaletteRedForeground1 },
    },
});

export interface ConfirmActionDialogProps {
    open: boolean;
    title: string;
    body: string;
    confirmLabel: string;
    danger?: boolean;
    onConfirm: () => Promise<void> | void;
    onClose: () => void;
}

/** Fluent replacement for `window.confirm` used by the campaign row / bulk actions. */
export function ConfirmActionDialog({ open, title, body, confirmLabel, danger, onConfirm, onClose }: ConfirmActionDialogProps) {
    const t = useText();
    const styles = useStyles();
    const [busy, setBusy] = useState(false);

    async function handleConfirm() {
        setBusy(true);
        try {
            await onConfirm();
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && !busy && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogContent>{body}</DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" disabled={busy} onClick={onClose}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            disabled={busy}
                            icon={busy ? <Spinner size="tiny" /> : undefined}
                            className={danger ? styles.danger : undefined}
                            onClick={() => void handleConfirm()}
                        >
                            {confirmLabel}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
