import { useEffect, useState, type ReactElement } from 'react';
import { router } from '@inertiajs/react';
import {
    Button,
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
    Radio,
    RadioGroup,
    Spinner,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { MailRegular, BranchForkRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { createCampaignV2 } from '@/Lib/api/campaigns';
import type { CampaignType } from '@/Lib/types/campaigns';
import { apiErrorMessage, apiValidationErrors, campaignSetupUrl } from '@/Components/Campaigns/campaignUi';

const useStyles = makeStyles({
    surface: {
        maxWidth: '600px',
        width: '100%',
    },
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    tiles: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    tile: {
        display: 'flex',
        alignItems: 'flex-start',
        gap: tokens.spacingHorizontalS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusLarge,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        backgroundColor: tokens.colorNeutralBackground1,
        cursor: 'pointer',
    },
    tileSelected: {
        border: `${tokens.strokeWidthThick} solid ${tokens.colorBrandStroke1}`,
        backgroundColor: tokens.colorBrandBackground2,
    },
    tileIcon: {
        fontSize: '28px',
        color: tokens.colorBrandForeground1,
        paddingTop: tokens.spacingVerticalXS,
    },
    tileText: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
});

export interface CreateCampaignDialogProps {
    open: boolean;
    onClose: () => void;
    /** Folder the new campaign is created in (the folder currently open on the list). */
    folderId?: string | null;
}

type Step = 'type' | 'name';

/**
 * docs/43 §43.4.1 — **Créer une campagne**: type chooser (E-mail · Test A/B)
 * → *Nom interne de la campagne* → **Créer la campagne** → `POST /campaigns`
 * → `/campaigns/{uuid}/setup` (Brevo asks only for the internal name).
 */
export function CreateCampaignDialog({ open, onClose, folderId = null }: CreateCampaignDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [step, setStep] = useState<Step>('type');
    const [type, setType] = useState<CampaignType>('classic');
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [nameError, setNameError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setStep('type');
            setType('classic');
            setName('');
            setError(null);
            setNameError(null);
        }
    }, [open]);

    async function handleCreate() {
        const trimmed = name.trim();
        if (trimmed.length === 0) {
            return;
        }
        setBusy(true);
        setError(null);
        setNameError(null);
        try {
            const campaign = await createCampaignV2({ name: trimmed, type, folder_id: folderId });
            router.visit(campaignSetupUrl(campaign.id));
        } catch (err) {
            const errors = apiValidationErrors(err);
            if (errors.name) {
                setNameError(errors.name);
            } else {
                setError(Object.values(errors)[0] ?? apiErrorMessage(err));
            }
            setBusy(false);
        }
    }

    const tiles: Array<{ value: CampaignType; label: string; help: string; icon: ReactElement }> = [
        { value: 'classic', label: t.campaigns.create.typeClassic, help: t.campaigns.create.typeClassicHelp, icon: <MailRegular /> },
        { value: 'ab_test', label: t.campaigns.create.typeAbTest, help: t.campaigns.create.typeAbTestHelp, icon: <BranchForkRegular /> },
    ];

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && !busy && onClose()}>
            <DialogSurface className={styles.surface}>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (step === 'type') {
                            setStep('name');
                        } else {
                            void handleCreate();
                        }
                    }}
                >
                    <DialogBody>
                        <DialogTitle>{t.campaigns.create.title}</DialogTitle>
                        <DialogContent className={styles.content}>
                            {error && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            {step === 'type' ? (
                                <Field label={t.campaigns.create.chooseType}>
                                    <RadioGroup
                                        value={type}
                                        className={styles.tiles}
                                        onChange={(_, data) => setType(data.value as CampaignType)}
                                    >
                                        {tiles.map((tile) => (
                                            <div
                                                key={tile.value}
                                                className={mergeClasses(styles.tile, type === tile.value && styles.tileSelected)}
                                                onClick={() => setType(tile.value)}
                                            >
                                                <span className={styles.tileIcon}>{tile.icon}</span>
                                                <div className={styles.tileText}>
                                                    <Radio value={tile.value} label={<Text weight="semibold">{tile.label}</Text>} />
                                                    <Text size={200}>{tile.help}</Text>
                                                </div>
                                            </div>
                                        ))}
                                    </RadioGroup>
                                </Field>
                            ) : (
                                <Field
                                    label={t.campaigns.create.internalName}
                                    hint={t.campaigns.create.internalNameHelp}
                                    required
                                    validationMessage={nameError ?? undefined}
                                >
                                    <Input
                                        value={name}
                                        autoFocus
                                        maxLength={255}
                                        disabled={busy}
                                        onChange={(_, data) => setName(data.value)}
                                    />
                                </Field>
                            )}
                        </DialogContent>
                        <DialogActions>
                            {step === 'name' ? (
                                <Button appearance="secondary" disabled={busy} onClick={() => setStep('type')}>
                                    {t.common.back}
                                </Button>
                            ) : (
                                <Button appearance="secondary" onClick={onClose}>
                                    {t.common.cancel}
                                </Button>
                            )}
                            {step === 'type' ? (
                                <Button appearance="primary" type="submit">
                                    {t.common.continue}
                                </Button>
                            ) : (
                                <Button
                                    appearance="primary"
                                    type="submit"
                                    disabled={busy || name.trim().length === 0}
                                    icon={busy ? <Spinner size="tiny" /> : undefined}
                                >
                                    {t.campaigns.create.submit}
                                </Button>
                            )}
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
