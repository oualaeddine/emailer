/**
 * WP-61 — *Restaurer une version précédente* for templates (doc 43
 * §43.4.4 / §43.4.5, docs/45 §45.4.4): lists `GET /templates/{id}/versions`
 * and restores one (`POST /templates/{id}/versions/{n}/restore`, which
 * creates a new version); the editor then reloads the restored design.
 */
import { useCallback, useEffect, useState } from 'react';
import {
    Badge,
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    MessageBar,
    MessageBarBody,
    OverlayDrawer,
    Spinner,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DismissRegular, HistoryRegular } from '@fluentui/react-icons';
import type { Uuid } from '@/Lib/types/common';
import type { TemplateV2Detail, TemplateVersion } from '@/Lib/types/templates';
import { fetchTemplateVersions, restoreTemplateDesignVersion, toDesignSaveError } from '@/Lib/api/editor';
import { format, text } from '../strings';

const useStyles = makeStyles({
    list: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, margin: 0, padding: 0, listStyleType: 'none' },
    row: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        padding: tokens.spacingVerticalS,
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
    meta: { display: 'flex', flexDirection: 'column', flex: '1 1 auto', minWidth: 0 },
    muted: { color: tokens.colorNeutralForeground3 },
});

function formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export interface VersionsDrawerProps {
    open: boolean;
    templateId: Uuid;
    onClose: () => void;
    onRestored: (detail: TemplateV2Detail) => void;
}

export function VersionsDrawer({ open, templateId, onClose, onRestored }: VersionsDrawerProps) {
    const styles = useStyles();
    const [versions, setVersions] = useState<TemplateVersion[] | null>(null);
    const [failed, setFailed] = useState(false);
    const [confirming, setConfirming] = useState<TemplateVersion | null>(null);
    const [restoring, setRestoring] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setFailed(false);
        setVersions(null);
        const rows = await fetchTemplateVersions(templateId);
        if (rows === null) {
            setFailed(true);
            setVersions([]);
        } else {
            setVersions([...rows].sort((a, b) => b.version_number - a.version_number));
        }
    }, [templateId]);

    useEffect(() => {
        if (open) {
            setError(null);
            void load();
        }
    }, [open, load]);

    const restore = async (version: TemplateVersion) => {
        setRestoring(true);
        setError(null);
        try {
            const detail = await restoreTemplateDesignVersion(templateId, version.version_number);
            setConfirming(null);
            onRestored(detail);
            onClose();
        } catch (err) {
            const parsed = toDesignSaveError(err);
            setError(parsed.message ?? text.common.genericError);
            setConfirming(null);
        } finally {
            setRestoring(false);
        }
    };

    return (
        <>
            <OverlayDrawer position="end" open={open} onOpenChange={(_, data) => !data.open && onClose()}>
                <DrawerHeader>
                    <DrawerHeaderTitle
                        action={<Button appearance="subtle" aria-label={text.common.close} icon={<DismissRegular />} onClick={onClose} />}
                    >
                        {text.versions.title}
                    </DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody>
                    {error && (
                        <MessageBar intent="error">
                            <MessageBarBody>{error}</MessageBarBody>
                        </MessageBar>
                    )}
                    {failed && (
                        <MessageBar intent="error">
                            <MessageBarBody>{text.common.genericError}</MessageBarBody>
                        </MessageBar>
                    )}
                    {versions === null && <Spinner label={text.common.loading} />}
                    {versions !== null && versions.length === 0 && !failed && <Text>{text.common.noResults}</Text>}
                    {versions !== null && versions.length > 0 && (
                        <ul className={styles.list}>
                            {versions.map((version) => (
                                <li key={version.id} className={styles.row}>
                                    <HistoryRegular />
                                    <div className={styles.meta}>
                                        <Text weight="semibold">
                                            {format(text.versions.versionNumber, version.version_number)}{' '}
                                            {version.is_current && (
                                                <Badge appearance="tint" color="brand" size="small">
                                                    {text.versions.current}
                                                </Badge>
                                            )}
                                        </Text>
                                        <Text size={200} className={styles.muted}>
                                            {formatDate(version.created_at)}
                                            {version.author ? ` · ${version.author.name}` : ''}
                                        </Text>
                                    </div>
                                    {!version.is_current && (
                                        <Button size="small" disabled={restoring} onClick={() => setConfirming(version)}>
                                            {text.versions.restore}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </DrawerBody>
            </OverlayDrawer>

            <Dialog open={confirming !== null} onOpenChange={(_, data) => !data.open && !restoring && setConfirming(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{text.versions.restore}</DialogTitle>
                        <DialogContent>{confirming ? format(text.versions.confirm, confirming.version_number) : ''}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" disabled={restoring} onClick={() => setConfirming(null)}>
                                {text.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={restoring} onClick={() => confirming && void restore(confirming)}>
                                {restoring ? <Spinner size="tiny" /> : text.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </>
    );
}
