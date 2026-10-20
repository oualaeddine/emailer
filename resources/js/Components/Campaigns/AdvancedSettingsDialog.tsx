import { useEffect, useState } from 'react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Divider,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Radio,
    RadioGroup,
    Spinner,
    Switch,
    Tag,
    TagGroup,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AttachRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import {
    fetchCampaignFolders,
    fetchUnsubscribePages,
    fetchUpdateProfileForms,
    updateCampaignV2,
    type NamedOption,
} from '@/Lib/api/campaigns';
import { fetchSmtpAccounts } from '@/Lib/api/smtp';
import type { Folder } from '@/Lib/types/common';
import type { SmtpAccount } from '@/Lib/types/smtp';
import type {
    CampaignSendWindow,
    CampaignUpdateInput,
    CampaignUtm,
    CampaignV2Detail,
    EmailExpirationUnit,
    SmtpStrategy,
} from '@/Lib/types/campaigns';
import { apiErrorMessage, apiValidationErrors } from '@/Components/Campaigns/campaignUi';
import { CheckboxGroup } from '@/Components/Common/CheckboxGroup';

const MAX_TAGS = 20;
const MAX_TAG_LENGTH = 50;
const NONE = '__none__';
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 0] as const;
const UTM_KEYS = ['source', 'medium', 'campaign', 'content', 'term', 'id'] as const;
type UtmKey = (typeof UTM_KEYS)[number];

const useStyles = makeStyles({
    surface: {
        maxWidth: '720px',
        width: '100%',
    },
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    section: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    inline: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-end',
        gap: tokens.spacingHorizontalS,
    },
    days: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalXS,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
});

interface FormState {
    unsubscribeMode: 'default' | 'custom';
    unsubscribePageId: string | null;
    updateProfileFormId: string | null;
    tags: string[];
    folderId: string | null;
    utm: CampaignUtm;
    attachmentRemoved: boolean;
    mirror: boolean;
    inlineImages: boolean;
    expirationEnabled: boolean;
    expirationDuration: string;
    expirationUnit: EmailExpirationUnit;
    tracking: boolean;
    smtpStrategy: SmtpStrategy;
    smtpAccountId: string | null;
    windowEnabled: boolean;
    window: CampaignSendWindow;
    requiresApproval: boolean;
}

function initialState(campaign: CampaignV2Detail): FormState {
    return {
        unsubscribeMode: campaign.unsubscribe_page_id ? 'custom' : 'default',
        unsubscribePageId: campaign.unsubscribe_page_id,
        updateProfileFormId: campaign.update_profile_form_id,
        tags: [...campaign.tags],
        folderId: campaign.folder_id,
        utm: { ...campaign.utm },
        attachmentRemoved: false,
        mirror: campaign.mirror_link_enabled,
        inlineImages: campaign.inline_images,
        expirationEnabled: campaign.email_expiration !== null,
        expirationDuration: String(campaign.email_expiration?.duration ?? 30),
        expirationUnit: campaign.email_expiration?.unit ?? 'days',
        tracking: campaign.tracking_enabled,
        smtpStrategy: campaign.smtp_strategy,
        smtpAccountId: campaign.smtp_account_id,
        windowEnabled: campaign.send_window !== null,
        window: campaign.send_window ?? { days: [1, 2, 3, 4, 0], start: '08:00', end: '17:00' },
        requiresApproval: campaign.requires_approval,
    };
}

export interface AdvancedSettingsDialogProps {
    open: boolean;
    campaign: CampaignV2Detail;
    onClose: () => void;
    onSaved: (campaign: CampaignV2Detail) => void;
}

/** docs/43 §43.4.2 item 5 — *Paramètres supplémentaires* → *Modifier les paramètres*. */
export function AdvancedSettingsDialog({ open, campaign, onClose, onSaved }: AdvancedSettingsDialogProps) {
    const styles = useStyles();
    const t = useText();
    const s = t.campaignSetup.settings;
    const [form, setForm] = useState<FormState>(() => initialState(campaign));
    const [tagInput, setTagInput] = useState('');
    const [folders, setFolders] = useState<Folder[]>([]);
    const [pages, setPages] = useState<NamedOption[]>([]);
    const [profileForms, setProfileForms] = useState<NamedOption[]>([]);
    const [smtpAccounts, setSmtpAccounts] = useState<SmtpAccount[]>([]);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        setForm(initialState(campaign));
        setTagInput('');
        setErrors({});
        setError(null);
        void fetchCampaignFolders().then(setFolders).catch(() => setFolders([]));
        void fetchUnsubscribePages().then(setPages);
        void fetchUpdateProfileForms().then(setProfileForms);
        void fetchSmtpAccounts().then(setSmtpAccounts).catch(() => setSmtpAccounts([]));
    }, [open, campaign]);

    function set<K extends keyof FormState>(key: K, value: FormState[K]) {
        setForm((previous) => ({ ...previous, [key]: value }));
    }

    function addTag() {
        const tag = tagInput.trim().slice(0, MAX_TAG_LENGTH);
        if (tag && !form.tags.includes(tag) && form.tags.length < MAX_TAGS) {
            set('tags', [...form.tags, tag]);
        }
        setTagInput('');
    }

    function toggleDay(day: number, checked: boolean) {
        const days = checked ? [...form.window.days, day] : form.window.days.filter((d) => d !== day);
        set('window', { ...form.window, days: [...new Set(days)].sort() });
    }

    const duration = Number(form.expirationDuration);
    const durationInvalid = form.expirationEnabled && (!Number.isInteger(duration) || duration < 1 || duration > 365);
    const smtpInvalid = form.smtpStrategy === 'single' && !form.smtpAccountId;
    const windowInvalid = form.windowEnabled && (form.window.days.length === 0 || form.window.start >= form.window.end);

    async function handleSave() {
        if (durationInvalid || smtpInvalid || windowInvalid) {
            return;
        }
        const utm: Partial<CampaignUtm> = { enabled: form.utm.enabled };
        UTM_KEYS.forEach((key) => {
            const value = (form.utm[key] ?? '').trim();
            utm[key] = value === '' ? null : value;
        });
        const payload: CampaignUpdateInput = {
            unsubscribe_page_id: form.unsubscribeMode === 'custom' ? form.unsubscribePageId : null,
            update_profile_form_id: form.updateProfileFormId,
            tags: form.tags,
            folder_id: form.folderId,
            utm,
            mirror_link_enabled: form.mirror,
            inline_images: form.inlineImages,
            email_expiration: form.expirationEnabled ? { duration, unit: form.expirationUnit } : null,
            tracking_enabled: form.tracking,
            smtp_strategy: form.smtpStrategy,
            smtp_account_id: form.smtpStrategy === 'single' ? form.smtpAccountId : null,
            send_window: form.windowEnabled ? form.window : null,
            requires_approval: form.requiresApproval,
            ...(form.attachmentRemoved ? { attachment_id: null } : {}),
        };
        setSaving(true);
        setErrors({});
        setError(null);
        try {
            onSaved(await updateCampaignV2(campaign.id, payload));
            onClose();
        } catch (err) {
            const validation = apiValidationErrors(err);
            setErrors(validation);
            setError(Object.values(validation)[0] ?? apiErrorMessage(err));
        } finally {
            setSaving(false);
        }
    }

    const folderLabel = form.folderId ? (folders.find((f) => f.id === form.folderId)?.name ?? '') : t.common.noFolder;
    const pageLabel = pages.find((p) => p.id === form.unsubscribePageId)?.name ?? '';
    const profileLabel = form.updateProfileFormId
        ? (profileForms.find((p) => p.id === form.updateProfileFormId)?.name ?? '')
        : t.common.default;
    const smtpLabel = smtpAccounts.find((a) => a.id === form.smtpAccountId)?.name ?? '';
    const utmLabels: Record<UtmKey, string> = {
        source: s.utmSource,
        medium: s.utmMedium,
        campaign: s.utmCampaign,
        content: s.utmContent,
        term: s.utmTerm,
        id: s.utmId,
    };
    const attachment = form.attachmentRemoved ? null : campaign.attachment;

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && !saving && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{s.title}</DialogTitle>
                    <DialogContent className={styles.content}>
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}

                        {/* Désinscription & profil */}
                        <div className={styles.section}>
                            <Field label={s.unsubscribePage} validationMessage={errors.unsubscribe_page_id}>
                                <RadioGroup
                                    layout="horizontal"
                                    value={form.unsubscribeMode}
                                    onChange={(_, data) => set('unsubscribeMode', data.value as FormState['unsubscribeMode'])}
                                >
                                    <Radio value="default" label={s.unsubscribeDefault} />
                                    <Radio value="custom" label={s.unsubscribeCustom} disabled={pages.length === 0} />
                                </RadioGroup>
                            </Field>
                            {form.unsubscribeMode === 'custom' && (
                                <Dropdown
                                    aria-label={s.unsubscribeCustom}
                                    value={pageLabel}
                                    selectedOptions={form.unsubscribePageId ? [form.unsubscribePageId] : []}
                                    onOptionSelect={(_, data) => set('unsubscribePageId', data.optionValue ?? null)}
                                >
                                    {pages.map((page) => (
                                        <Option key={page.id} value={page.id}>
                                            {page.name}
                                        </Option>
                                    ))}
                                </Dropdown>
                            )}
                            <Field label={s.updateProfileForm} validationMessage={errors.update_profile_form_id}>
                                <Dropdown
                                    value={profileLabel}
                                    selectedOptions={[form.updateProfileFormId ?? NONE]}
                                    onOptionSelect={(_, data) =>
                                        set('updateProfileFormId', data.optionValue === NONE ? null : (data.optionValue ?? null))
                                    }
                                >
                                    <Option value={NONE}>{t.common.default}</Option>
                                    {profileForms.map((item) => (
                                        <Option key={item.id} value={item.id}>
                                            {item.name}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                        </div>
                        <Divider />

                        {/* Organisation */}
                        <div className={styles.section}>
                            <Field label={s.tags} hint={s.tagsHelp} validationMessage={errors.tags}>
                                <div className={styles.inline}>
                                    <Input
                                        value={tagInput}
                                        maxLength={MAX_TAG_LENGTH}
                                        disabled={form.tags.length >= MAX_TAGS}
                                        onChange={(_, data) => setTagInput(data.value)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ',') {
                                                event.preventDefault();
                                                addTag();
                                            }
                                        }}
                                    />
                                    <Button onClick={addTag} disabled={tagInput.trim() === '' || form.tags.length >= MAX_TAGS}>
                                        {t.common.addTag}
                                    </Button>
                                </div>
                            </Field>
                            {form.tags.length > 0 && (
                                <TagGroup
                                    aria-label={s.tags}
                                    onDismiss={(_, data) => set('tags', form.tags.filter((tag) => tag !== data.value))}
                                >
                                    {form.tags.map((tag) => (
                                        <Tag key={tag} value={tag} dismissible dismissIcon={{ 'aria-label': t.common.remove }} size="small">
                                            {tag}
                                        </Tag>
                                    ))}
                                </TagGroup>
                            )}
                            <Field label={s.folder}>
                                <Dropdown
                                    value={folderLabel}
                                    selectedOptions={[form.folderId ?? NONE]}
                                    onOptionSelect={(_, data) => set('folderId', data.optionValue === NONE ? null : (data.optionValue ?? null))}
                                >
                                    <Option value={NONE}>{t.common.noFolder}</Option>
                                    {folders.map((folder) => (
                                        <Option key={folder.id} value={folder.id}>
                                            {folder.name}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                        </div>
                        <Divider />

                        {/* UTM */}
                        <div className={styles.section}>
                            <Text weight="semibold">{s.utm}</Text>
                            <Switch
                                checked={form.utm.enabled}
                                label={s.utmEnable}
                                onChange={(_, data) => set('utm', { ...form.utm, enabled: data.checked })}
                            />
                            {form.utm.enabled && (
                                <div className={styles.grid}>
                                    {UTM_KEYS.map((key) => (
                                        <Field key={key} label={utmLabels[key]} validationMessage={errors[`utm.${key}`]}>
                                            <Input
                                                value={form.utm[key] ?? ''}
                                                maxLength={100}
                                                onChange={(_, data) => set('utm', { ...form.utm, [key]: data.value })}
                                            />
                                        </Field>
                                    ))}
                                </div>
                            )}
                        </div>
                        <Divider />

                        {/* Contenu de l'e-mail */}
                        <div className={styles.section}>
                            <Field label={s.attachment}>
                                {attachment ? (
                                    <div className={styles.inline}>
                                        <Text>
                                            <AttachRegular /> {attachment.filename} ({new Intl.NumberFormat('fr-FR', { style: 'unit', unit: 'kilobyte', maximumFractionDigits: 0 }).format(attachment.size / 1024)})
                                        </Text>
                                        <Button appearance="subtle" onClick={() => set('attachmentRemoved', true)}>
                                            {t.common.remove}
                                        </Button>
                                    </div>
                                ) : (
                                    <Text className={styles.muted}>{t.common.none}</Text>
                                )}
                            </Field>
                            <Switch checked={form.mirror} label={s.mirrorLink} onChange={(_, data) => set('mirror', data.checked)} />
                            <Switch
                                checked={form.inlineImages}
                                label={s.inlineImages}
                                onChange={(_, data) => set('inlineImages', data.checked)}
                            />
                            <Switch
                                checked={form.tracking}
                                label={s.tracking}
                                onChange={(_, data) => set('tracking', data.checked)}
                            />
                            <Switch
                                checked={form.expirationEnabled}
                                label={s.expiration}
                                onChange={(_, data) => set('expirationEnabled', data.checked)}
                            />
                            {form.expirationEnabled && (
                                <>
                                    <Text size={200} className={styles.muted}>
                                        {s.expirationHelp}
                                    </Text>
                                    <div className={styles.inline}>
                                        <Field
                                            label={s.expirationDuration}
                                            validationState={durationInvalid || errors['email_expiration.duration'] ? 'error' : 'none'}
                                            validationMessage={errors['email_expiration.duration']}
                                        >
                                            <Input
                                                type="number"
                                                min={1}
                                                max={365}
                                                value={form.expirationDuration}
                                                onChange={(_, data) => set('expirationDuration', data.value)}
                                            />
                                        </Field>
                                        <Dropdown
                                            aria-label={s.expirationDuration}
                                            value={t.campaignSetup.expirationUnits[form.expirationUnit]}
                                            selectedOptions={[form.expirationUnit]}
                                            onOptionSelect={(_, data) =>
                                                set('expirationUnit', (data.optionValue as EmailExpirationUnit) ?? 'days')
                                            }
                                        >
                                            {(['days', 'weeks', 'months'] as const).map((unit) => (
                                                <Option key={unit} value={unit}>
                                                    {t.campaignSetup.expirationUnits[unit]}
                                                </Option>
                                            ))}
                                        </Dropdown>
                                    </div>
                                </>
                            )}
                        </div>
                        <Divider />

                        {/* Envoi (our own settings) */}
                        <div className={styles.section}>
                            <Field label={s.smtpStrategy} hint={form.smtpStrategy === 'single' ? s.smtpStrategyHelp : undefined} validationMessage={errors.smtp_strategy}>
                                <RadioGroup
                                    layout="horizontal"
                                    value={form.smtpStrategy}
                                    onChange={(_, data) => set('smtpStrategy', data.value as SmtpStrategy)}
                                >
                                    <Radio value="rotation" label={t.campaignSetup.smtpStrategies.rotation} />
                                    <Radio value="single" label={t.campaignSetup.smtpStrategies.single} />
                                </RadioGroup>
                            </Field>
                            {form.smtpStrategy === 'single' && (
                                <Field
                                    label={s.smtpAccount}
                                    required
                                    validationState={smtpInvalid || errors.smtp_account_id ? 'error' : 'none'}
                                    validationMessage={errors.smtp_account_id}
                                >
                                    <Dropdown
                                        value={smtpLabel}
                                        selectedOptions={form.smtpAccountId ? [form.smtpAccountId] : []}
                                        onOptionSelect={(_, data) => set('smtpAccountId', data.optionValue ?? null)}
                                    >
                                        {smtpAccounts.map((account) => (
                                            <Option key={account.id} value={account.id} text={account.name}>
                                                {account.name} — {account.from_email}
                                            </Option>
                                        ))}
                                    </Dropdown>
                                </Field>
                            )}
                            <Switch
                                checked={form.windowEnabled}
                                label={s.sendWindow}
                                onChange={(_, data) => set('windowEnabled', data.checked)}
                            />
                            {form.windowEnabled && (
                                <>
                                    <Text size={200} className={styles.muted}>
                                        {s.sendWindowHelp}
                                    </Text>
                                    <Field
                                        label={s.sendWindowDays}
                                        validationState={windowInvalid || errors.send_window ? 'error' : 'none'}
                                        validationMessage={errors.send_window}
                                    >
                                        <CheckboxGroup className={styles.days}>
                                            {WEEKDAYS.map((day) => (
                                                <Checkbox
                                                    key={day}
                                                    label={t.campaignSetup.weekdays[day]}
                                                    checked={form.window.days.includes(day)}
                                                    onChange={(_, data) => toggleDay(day, data.checked === true)}
                                                />
                                            ))}
                                        </CheckboxGroup>
                                    </Field>
                                    <div className={styles.inline}>
                                        <Field label={s.sendWindowStart}>
                                            <Input
                                                type="time"
                                                value={form.window.start}
                                                onChange={(_, data) => set('window', { ...form.window, start: data.value })}
                                            />
                                        </Field>
                                        <Field label={s.sendWindowEnd}>
                                            <Input
                                                type="time"
                                                value={form.window.end}
                                                onChange={(_, data) => set('window', { ...form.window, end: data.value })}
                                            />
                                        </Field>
                                    </div>
                                </>
                            )}
                            <Switch
                                checked={form.requiresApproval}
                                label={s.requiresApproval}
                                onChange={(_, data) => set('requiresApproval', data.checked)}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" disabled={saving} onClick={onClose}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            disabled={saving || durationInvalid || smtpInvalid || windowInvalid}
                            icon={saving ? <Spinner size="tiny" /> : undefined}
                            onClick={() => void handleSave()}
                        >
                            {t.common.save}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
