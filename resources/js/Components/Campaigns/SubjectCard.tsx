import { useEffect, useRef, useState } from 'react';
import {
    Button,
    Field,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    MessageBar,
    MessageBarBody,
    Popover,
    PopoverSurface,
    PopoverTrigger,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { EmojiRegular, PersonRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { FALLBACK_MERGE_TAGS, fetchMergeTags, updateCampaignV2 } from '@/Lib/api/campaigns';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import type { SetupStepCardProps } from '@/Components/Campaigns/cardTypes';
import { placeholderMergeTags } from '@/Components/Campaigns/MergeTagText';
import { apiErrorMessage, apiValidationErrors, formatText } from '@/Components/Campaigns/campaignUi';

const SUBJECT_MAX = 255;
const SUBJECT_RECOMMENDED = 60;

const EMOJIS = [
    '😀', '😊', '😍', '🥳', '😎', '🤩', '👍', '👏', '🙏', '💪',
    '🎉', '🎁', '🔥', '⭐', '✨', '💡', '📣', '📢', '📅', '⏰',
    '✅', '❗', '❤️', '💰', '🛒', '🏷️', '🚀', '📦', '☀️', '🌙',
];

const useStyles = makeStyles({
    tagMenu: { maxHeight: '320px', overflowY: 'auto' },
    fieldRow: {
        display: 'flex',
        gap: tokens.spacingHorizontalXS,
        alignItems: 'flex-start',
    },
    grow: {
        flexGrow: 1,
        minWidth: 0,
    },
    tools: {
        display: 'flex',
        gap: tokens.spacingHorizontalXS,
        paddingTop: '26px',
    },
    emojiGrid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(10, 32px)',
        gap: tokens.spacingHorizontalXXS,
    },
    counter: {
        display: 'flex',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
        color: tokens.colorNeutralForeground3,
    },
    inbox: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        padding: tokens.spacingVerticalS,
        paddingLeft: tokens.spacingHorizontalM,
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground2,
    },
    inboxLine: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    summary: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
});

export function isSubjectComplete(campaign: SetupStepCardProps['campaign']): boolean {
    return (campaign.subject ?? '').trim().length > 0;
}

type FieldKey = 'subject' | 'preview_text';

/** Inbox preview line: sender · subject — preview text (Brevo "Aperçu dans la boîte de réception"). */
export function InboxPreview({ senderName, subject, previewText }: { senderName: string; subject: string; previewText: string }) {
    const styles = useStyles();
    const t = useText();

    return (
        <div className={styles.inbox} aria-label={t.campaignSetup.subject.inboxPreview}>
            <Text size={200} className={styles.muted}>
                {t.campaignSetup.subject.inboxPreview}
            </Text>
            <Text weight="semibold" className={styles.inboxLine}>
                {senderName}
            </Text>
            <Text className={styles.inboxLine}>
                <Text weight="semibold">{subject || t.campaignSetup.subject.subjectPlaceholder}</Text>
                {previewText && <span className={styles.muted}> — {previewText}</span>}
            </Text>
        </div>
    );
}

/** Card 2 — **Objet**: subject + personalisation + emoji, preview text, counters, inbox preview. */
export function SubjectCard({ campaign, editable, expanded, onExpand, onCollapse, onSaved }: SetupStepCardProps) {
    const styles = useStyles();
    const t = useText();
    const [subject, setSubject] = useState(campaign.subject ?? '');
    const [previewText, setPreviewText] = useState(campaign.preview_text ?? '');
    const [mergeTags, setMergeTags] = useState<string[]>(FALLBACK_MERGE_TAGS);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const subjectRef = useRef<HTMLInputElement>(null);
    const previewRef = useRef<HTMLInputElement>(null);
    const lastFocused = useRef<FieldKey>('subject');

    useEffect(() => {
        if (expanded) {
            setSubject(campaign.subject ?? '');
            setPreviewText(campaign.preview_text ?? '');
            setErrors({});
            setError(null);
        }
    }, [expanded, campaign.subject, campaign.preview_text]);

    useEffect(() => {
        if (expanded) {
            void fetchMergeTags().then(setMergeTags);
        }
    }, [expanded]);

    function insert(text: string, target: FieldKey = lastFocused.current) {
        const element = target === 'subject' ? subjectRef.current : previewRef.current;
        const current = target === 'subject' ? subject : previewText;
        const start = element?.selectionStart ?? current.length;
        const end = element?.selectionEnd ?? current.length;
        const next = (current.slice(0, start) + text + current.slice(end)).slice(0, SUBJECT_MAX);
        if (target === 'subject') {
            setSubject(next);
        } else {
            setPreviewText(next);
        }
        window.requestAnimationFrame(() => {
            element?.focus();
            const caret = start + text.length;
            element?.setSelectionRange(caret, caret);
        });
    }

    async function handleSave() {
        if (subject.trim().length === 0) {
            return;
        }
        setSaving(true);
        setErrors({});
        setError(null);
        try {
            const updated = await updateCampaignV2(campaign.id, {
                subject: subject.trim(),
                preview_text: previewText.trim() === '' ? null : previewText.trim(),
            });
            onSaved(updated);
            onCollapse();
        } catch (err) {
            const validation = apiValidationErrors(err);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setError(apiErrorMessage(err));
            }
        } finally {
            setSaving(false);
        }
    }

    function tools(target: FieldKey) {
        return (
            <div className={styles.tools}>
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <Button
                            appearance="subtle"
                            icon={<PersonRegular />}
                            title={t.campaignSetup.subject.personalize}
                            aria-label={t.campaignSetup.subject.personalize}
                            onMouseDown={() => (lastFocused.current = target)}
                        />
                    </MenuTrigger>
                    <MenuPopover className={styles.tagMenu}>
                        <MenuList>
                            {mergeTags.map((tag) => (
                                <MenuItem key={tag} onClick={() => insert(tag, target)}>
                                    {tag}
                                </MenuItem>
                            ))}
                        </MenuList>
                    </MenuPopover>
                </Menu>
                <Popover withArrow>
                    <PopoverTrigger disableButtonEnhancement>
                        <Button
                            appearance="subtle"
                            icon={<EmojiRegular />}
                            title={t.campaignSetup.subject.emoji}
                            aria-label={t.campaignSetup.subject.emoji}
                            onMouseDown={() => (lastFocused.current = target)}
                        />
                    </PopoverTrigger>
                    <PopoverSurface>
                        <div className={styles.emojiGrid} role="listbox" aria-label={t.campaignSetup.subject.emoji}>
                            {EMOJIS.map((emoji) => (
                                <Button key={emoji} appearance="subtle" size="small" onClick={() => insert(emoji, target)}>
                                    {emoji}
                                </Button>
                            ))}
                        </div>
                    </PopoverSurface>
                </Popover>
            </div>
        );
    }

    const senderName = campaign.sender?.name ?? t.campaigns.noSender;

    return (
        <SetupCard
            title={t.campaignSetup.subject.title}
            complete={isSubjectComplete(campaign)}
            summary={
                campaign.subject ? (
                    <div className={styles.summary}>
                        <Text weight="semibold">{campaign.subject}</Text>
                        {campaign.preview_text && <Text size={200}>{campaign.preview_text}</Text>}
                    </div>
                ) : undefined
            }
            actionLabel={campaign.subject ? t.campaignSetup.subject.edit : t.campaignSetup.subject.add}
            onAction={onExpand}
            disabled={!editable}
            expanded={expanded}
            onCancel={onCollapse}
            onSave={() => void handleSave()}
            saving={saving}
            saveDisabled={subject.trim().length === 0}
        >
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <div className={styles.fieldRow}>
                <Field
                    className={styles.grow}
                    label={t.campaignSetup.subject.subject}
                    required
                    validationMessage={errors.subject ?? (subject.length > SUBJECT_RECOMMENDED ? t.campaignSetup.subject.tooLong : undefined)}
                    validationState={errors.subject ? 'error' : subject.length > SUBJECT_RECOMMENDED ? 'warning' : 'none'}
                >
                    <Input
                        ref={subjectRef}
                        value={subject}
                        maxLength={SUBJECT_MAX}
                        placeholder={t.campaignSetup.subject.subjectPlaceholder}
                        disabled={saving}
                        onFocus={() => (lastFocused.current = 'subject')}
                        onChange={(_, data) => setSubject(data.value)}
                    />
                </Field>
                {tools('subject')}
            </div>
            <div className={styles.counter}>
                <Text size={200}>{formatText(t.campaignSetup.subject.characters, subject.length)}</Text>
            </div>
            <div className={styles.fieldRow}>
                <Field
                    className={styles.grow}
                    label={t.campaignSetup.subject.previewText}
                    hint={t.campaignSetup.subject.previewTextHelp}
                    validationMessage={errors.preview_text}
                >
                    <Input
                        ref={previewRef}
                        value={previewText}
                        maxLength={SUBJECT_MAX}
                        disabled={saving}
                        onFocus={() => (lastFocused.current = 'preview_text')}
                        onChange={(_, data) => setPreviewText(data.value)}
                    />
                </Field>
                {tools('preview_text')}
            </div>
            <div className={styles.counter}>
                <Text size={200}>{formatText(t.campaignSetup.subject.characters, previewText.length)}</Text>
            </div>
            <InboxPreview senderName={senderName} subject={placeholderMergeTags(subject)} previewText={placeholderMergeTags(previewText)} />
        </SetupCard>
    );
}
