import { useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    MessageBar,
    MessageBarBody,
    Spinner,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage, deleteAttribute, errorStatus, usedInFromError } from '@/Lib/api/attributes';
import type { AttributeUsageRef, ContactAttribute } from '@/Lib/types/attributes';

interface Props {
    attribute: ContactAttribute | null;
    onClose: () => void;
    onDeleted: () => void;
}

export function DeleteAttributeDialog({ attribute, onClose, onDeleted }: Props) {
    const t = useText();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [usedIn, setUsedIn] = useState<AttributeUsageRef[]>([]);

    useEffect(() => {
        setBusy(false);
        setError(null);
        setUsedIn([]);
    }, [attribute]);

    async function confirm() {
        if (!attribute) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            await deleteAttribute(attribute.id);
            onDeleted();
        } catch (e) {
            const refs = errorStatus(e) === 409 ? usedInFromError(e) : [];
            setUsedIn(refs);
            setError(
                refs.length > 0
                    ? t.attributes.inUse.replace('%s', refs.map((r) => r.name).join(', '))
                    : apiErrorMessage(e, t.common.genericError),
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <Dialog open={attribute !== null} onOpenChange={(_, d) => !d.open && onClose()}>
            <DialogSurface>
                <DialogBody>
                    <DialogTitle>{t.common.deleteConfirmTitle}</DialogTitle>
                    <DialogContent style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        {attribute && <span>{t.attributes.deleteConfirm.replace('%s', attribute.name)}</span>}
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                        {usedIn.length > 0 && (
                            <ul style={{ margin: 0, paddingLeft: 20 }}>
                                {usedIn.map((r) => (
                                    <li key={`${r.type}-${r.id}`}>{r.name}</li>
                                ))}
                            </ul>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onClose} disabled={busy}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            onClick={() => void confirm()}
                            disabled={busy || usedIn.length > 0}
                            icon={busy ? <Spinner size="tiny" /> : undefined}
                        >
                            {t.common.delete}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
