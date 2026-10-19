import { useEffect, useMemo, useRef, useState } from 'react';
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
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    SearchBox,
    Spinner,
    Tab,
    TabList,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { PeopleRegular, WarningRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import {
    estimateCampaignAudience,
    fetchAudienceLists,
    fetchAudienceSegments,
    type AudienceSourceOption,
} from '@/Lib/api/campaigns';
import type { AudienceEstimate, CampaignAudienceInput, CampaignV2Detail } from '@/Lib/types/campaigns';
import { apiErrorMessage, apiValidationErrors, formatText } from '@/Components/Campaigns/campaignUi';

const MAX_TARGETS = 50;

const useStyles = makeStyles({
    surface: {
        maxWidth: '880px',
        width: '100%',
    },
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    layout: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) 260px',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 760px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
    sections: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        minWidth: 0,
    },
    section: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    list: {
        display: 'flex',
        flexDirection: 'column',
        maxHeight: '220px',
        overflowY: 'auto',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalXS,
    },
    option: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
    },
    count: {
        color: tokens.colorNeutralForeground3,
        whiteSpace: 'nowrap',
        paddingRight: tokens.spacingHorizontalS,
    },
    estimate: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusLarge,
        backgroundColor: tokens.colorNeutralBackground2,
        alignSelf: 'start',
        position: 'sticky',
        top: 0,
    },
    big: {
        fontSize: tokens.fontSizeHero700,
        lineHeight: tokens.lineHeightHero700,
        fontWeight: tokens.fontWeightSemibold,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
});

interface Selection {
    lists: string[];
    segments: string[];
}

type SourceTab = 'lists' | 'segments';

interface TargetPickerProps {
    lists: AudienceSourceOption[];
    segments: AudienceSourceOption[];
    value: Selection;
    blocked: Selection;
    onChange: (value: Selection) => void;
    loading: boolean;
    limitReached: boolean;
}

/** Listes / Segments tabs with search and checkboxes showing each count. */
function TargetPicker({ lists, segments, value, blocked, onChange, loading, limitReached }: TargetPickerProps) {
    const styles = useStyles();
    const t = useText();
    const [tab, setTab] = useState<SourceTab>('lists');
    const [query, setQuery] = useState('');
    const source = tab === 'lists' ? lists : segments;
    const filtered = source.filter((option) => option.name.toLocaleLowerCase('fr').includes(query.toLocaleLowerCase('fr')));
    const selected = value[tab];

    function toggle(id: string, checked: boolean) {
        const next = checked ? [...selected, id] : selected.filter((item) => item !== id);
        onChange({ ...value, [tab]: next });
    }

    return (
        <div className={styles.section}>
            <TabList size="small" selectedValue={tab} onTabSelect={(_, data) => setTab(data.value as SourceTab)}>
                <Tab value="lists">
                    {t.campaignSetup.recipients.tabLists}
                    {value.lists.length > 0 ? ` (${value.lists.length})` : ''}
                </Tab>
                <Tab value="segments">
                    {t.campaignSetup.recipients.tabSegments}
                    {value.segments.length > 0 ? ` (${value.segments.length})` : ''}
                </Tab>
            </TabList>
            <SearchBox
                size="small"
                value={query}
                placeholder={tab === 'lists' ? t.campaignSetup.recipients.searchLists : t.campaignSetup.recipients.searchSegments}
                onChange={(_, data) => setQuery(data.value)}
            />
            <div className={styles.list} role="group">
                {loading ? (
                    <Spinner size="tiny" label={t.common.loading} />
                ) : filtered.length === 0 ? (
                    <Text className={styles.muted}>
                        {tab === 'lists' ? t.campaignSetup.recipients.noLists : t.campaignSetup.recipients.noSegments}
                    </Text>
                ) : (
                    filtered.map((option) => {
                        const checked = selected.includes(option.id);
                        const isBlocked = blocked[tab].includes(option.id);

                        return (
                            <div key={option.id} className={styles.option}>
                                <Checkbox
                                    checked={checked}
                                    disabled={isBlocked || (!checked && limitReached)}
                                    label={option.name}
                                    onChange={(_, data) => toggle(option.id, data.checked === true)}
                                />
                                {option.count !== null && (
                                    <Text size={200} className={styles.count}>
                                        {formatText(t.campaignSetup.recipients.contactsCount, option.count)}
                                    </Text>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}

export interface RecipientsPickerDialogProps {
    open: boolean;
    campaign: CampaignV2Detail;
    onClose: () => void;
    /** Persists `audience` + `to_field` (PATCH /campaigns/{id}). */
    onSubmit: (audience: CampaignAudienceInput, toField: string | null) => Promise<void>;
}

/**
 * docs/43 §43.4.2 item 3 — **Ajouter des destinataires**: Listes / Segments
 * tabs, *Exclure* section, live estimate (`POST …/audience/estimate`),
 * unengaged warning and optional *Champ À*.
 */
export function RecipientsPickerDialog({ open, campaign, onClose, onSubmit }: RecipientsPickerDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [lists, setLists] = useState<AudienceSourceOption[]>([]);
    const [segments, setSegments] = useState<AudienceSourceOption[]>([]);
    const [loadingSources, setLoadingSources] = useState(false);
    const [include, setInclude] = useState<Selection>({ lists: [], segments: [] });
    const [exclude, setExclude] = useState<Selection>({ lists: [], segments: [] });
    const [toField, setToField] = useState('');
    const [estimate, setEstimate] = useState<AudienceEstimate | null>(null);
    const [estimating, setEstimating] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const estimateRequest = useRef(0);

    useEffect(() => {
        if (!open) {
            return;
        }
        const audience = campaign.audience;
        setInclude({ lists: audience.lists.map((x) => x.id), segments: audience.segments.map((x) => x.id) });
        setExclude({
            lists: audience.exclusion_lists.map((x) => x.id),
            segments: audience.excluded_segments.map((x) => x.id),
        });
        setToField(campaign.to_field ?? '');
        setError(null);
        setEstimate(null);
        setLoadingSources(true);
        void Promise.all([
            fetchAudienceLists().catch(() => [] as AudienceSourceOption[]),
            fetchAudienceSegments().catch(() => [] as AudienceSourceOption[]),
        ]).then(([listRows, segmentRows]) => {
            // Keep already-selected targets visible even if the endpoint does not return them.
            const withSelected = (rows: AudienceSourceOption[], targets: typeof audience.lists) => {
                const ids = new Set(rows.map((row) => row.id));
                const missing = targets
                    .filter((target) => !ids.has(target.id))
                    .map((target) => ({ id: target.id, name: target.name, count: target.contact_count }));

                return [...rows, ...missing];
            };
            setLists(withSelected(listRows, [...audience.lists, ...audience.exclusion_lists]));
            setSegments(withSelected(segmentRows, [...audience.segments, ...audience.excluded_segments]));
            setLoadingSources(false);
        });
    }, [open, campaign]);

    const audienceInput: CampaignAudienceInput = useMemo(
        () => ({
            lists: include.lists,
            segments: include.segments,
            exclusion_lists: exclude.lists,
            excluded_segments: exclude.segments,
        }),
        [include, exclude],
    );

    const includeCount = include.lists.length + include.segments.length;
    const totalTargets = includeCount + exclude.lists.length + exclude.segments.length;
    const limitReached = totalTargets >= MAX_TARGETS;

    // Live estimate (debounced).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (includeCount === 0) {
            setEstimate(null);
            setEstimating(false);

            return;
        }
        const requestId = ++estimateRequest.current;
        setEstimating(true);
        const handle = window.setTimeout(() => {
            estimateCampaignAudience(campaign.id, { audience: audienceInput })
                .then((result) => {
                    if (requestId === estimateRequest.current) {
                        setEstimate(result);
                    }
                })
                .catch(() => {
                    if (requestId === estimateRequest.current) {
                        setEstimate(null);
                    }
                })
                .finally(() => {
                    if (requestId === estimateRequest.current) {
                        setEstimating(false);
                    }
                });
        }, 500);

        return () => window.clearTimeout(handle);
    }, [open, campaign.id, audienceInput, includeCount]);

    async function handleSave() {
        setSaving(true);
        setError(null);
        try {
            await onSubmit(audienceInput, toField.trim() === '' ? null : toField.trim());
        } catch (err) {
            const validation = apiValidationErrors(err);
            setError(Object.values(validation)[0] ?? apiErrorMessage(err));
        } finally {
            setSaving(false);
        }
    }

    const unengagedPercent = estimate ? (estimate.unengaged_percent * 100).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) : '';

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && !saving && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{t.campaignSetup.recipients.dialogTitle}</DialogTitle>
                    <DialogContent className={styles.content}>
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                        {limitReached && (
                            <MessageBar intent="warning">
                                <MessageBarBody>{t.campaignSetup.recipients.maxTargets}</MessageBarBody>
                            </MessageBar>
                        )}
                        <div className={styles.layout}>
                            <div className={styles.sections}>
                                <Text weight="semibold">{t.campaignSetup.recipients.sendTo}</Text>
                                <TargetPicker
                                    lists={lists}
                                    segments={segments}
                                    value={include}
                                    blocked={exclude}
                                    onChange={setInclude}
                                    loading={loadingSources}
                                    limitReached={limitReached}
                                />
                                <Divider />
                                <Text weight="semibold">{t.campaignSetup.recipients.exclude}</Text>
                                <TargetPicker
                                    lists={lists}
                                    segments={segments}
                                    value={exclude}
                                    blocked={include}
                                    onChange={setExclude}
                                    loading={loadingSources}
                                    limitReached={limitReached}
                                />
                                <Divider />
                                <Field
                                    label={t.campaignSetup.recipients.toField}
                                    hint={t.campaignSetup.recipients.toFieldHelp}
                                >
                                    <Input
                                        value={toField}
                                        placeholder="{{ contact.PRENOM }} {{ contact.NOM }}"
                                        onChange={(_, data) => setToField(data.value)}
                                    />
                                </Field>
                            </div>
                            <aside className={styles.estimate} aria-live="polite">
                                <Text weight="semibold">
                                    <PeopleRegular /> {t.campaignSetup.recipients.estimatedAudience}
                                </Text>
                                {estimating ? (
                                    <Spinner size="tiny" label={t.campaignSetup.recipients.computing} />
                                ) : estimate ? (
                                    <>
                                        <Text className={styles.big}>{estimate.total.toLocaleString('fr-FR')}</Text>
                                        <Text>{formatText(t.campaignSetup.recipients.estimatedCount, estimate.total)}</Text>
                                        <Text size={200} className={styles.muted}>
                                            {formatText(t.campaignSetup.recipients.excludedCount, estimate.excluded)}
                                        </Text>
                                        <Text size={200} className={styles.muted}>
                                            {formatText(t.campaignSetup.recipients.suppressedCount, estimate.suppressed)}
                                        </Text>
                                        {estimate.unengaged_warning && (
                                            <MessageBar intent="warning" icon={<WarningRegular />}>
                                                <MessageBarBody>
                                                    {formatText(t.campaignSetup.recipients.unengagedWarning, unengagedPercent)}
                                                </MessageBarBody>
                                            </MessageBar>
                                        )}
                                    </>
                                ) : (
                                    <Text className={styles.big}>{t.common.emptyValue}</Text>
                                )}
                            </aside>
                        </div>
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" disabled={saving} onClick={onClose}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            disabled={saving || totalTargets > MAX_TARGETS}
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
