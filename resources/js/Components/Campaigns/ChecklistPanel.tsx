import { useCallback, useEffect, useRef, useState } from 'react';
import { Badge, Button, MessageBar, MessageBarBody, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import {
    CheckmarkCircleFilled,
    DismissCircleFilled,
    InfoRegular,
    LinkRegular,
    SubtractCircleRegular,
    WarningFilled,
} from '@fluentui/react-icons';
import type { ReactElement } from 'react';
import { useText } from '@/Hooks/useText';
import { checkCampaignLinks, fetchCampaignChecklist } from '@/Lib/api/campaigns';
import type { CampaignChecklist, CampaignChecklistItem, CampaignChecklistItemStatus, CampaignChecklistStatus } from '@/Lib/types/campaigns';
import { apiErrorMessage, apiStatus, formatText } from '@/Components/Campaigns/campaignUi';

const POLL_MS = 3000;
const MAX_POLLS = 40;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalL,
        paddingLeft: tokens.spacingHorizontalL,
        paddingRight: tokens.spacingHorizontalL,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusLarge,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        boxShadow: tokens.shadow2,
    },
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
        flexWrap: 'wrap',
    },
    items: {
        listStyleType: 'none',
        margin: 0,
        padding: 0,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    item: {
        display: 'flex',
        gap: tokens.spacingHorizontalS,
        alignItems: 'flex-start',
    },
    icon: {
        fontSize: '20px',
        lineHeight: '20px',
        flexShrink: 0,
        paddingTop: '1px',
    },
    ok: { color: tokens.colorPaletteGreenForeground1 },
    warning: { color: tokens.colorPaletteDarkOrangeForeground1 },
    error: { color: tokens.colorPaletteRedForeground1 },
    neutral: { color: tokens.colorNeutralForeground3 },
    text: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        minWidth: 0,
        flexGrow: 1,
    },
    titleRow: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        flexWrap: 'wrap',
    },
    message: {
        color: tokens.colorNeutralForeground3,
        overflowWrap: 'anywhere',
    },
});

const STATUS_BADGE: Record<CampaignChecklistItemStatus, 'success' | 'warning' | 'danger' | 'informative' | 'subtle'> = {
    ok: 'success',
    warning: 'warning',
    error: 'danger',
    pending: 'informative',
    skipped: 'subtle',
};

const OVERALL_BADGE: Record<CampaignChecklistStatus, 'success' | 'warning' | 'danger'> = {
    complete: 'success',
    incomplete: 'warning',
    blocking: 'danger',
};

export interface ChecklistPanelProps {
    campaignId: string;
    /** Bump to re-fetch (after each card save / test send). */
    refreshKey: number;
    canCheckLinks: boolean;
    onLoaded?: (checklist: CampaignChecklist | null) => void;
}

/**
 * docs/43 §43.4.2 item 6 / docs/45 §45.4.5 — pre-send checklist (WP-45).
 * Renders whatever items come back, labelled by `key`; shows an
 * "indisponible" state when the endpoint 404s.
 */
export function ChecklistPanel({ campaignId, refreshKey, canCheckLinks, onLoaded }: ChecklistPanelProps) {
    const styles = useStyles();
    const t = useText();
    const [checklist, setChecklist] = useState<CampaignChecklist | null>(null);
    const [unavailable, setUnavailable] = useState(false);
    const [loading, setLoading] = useState(true);
    const [checkingLinks, setCheckingLinks] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pollTimer = useRef<number | null>(null);
    const polls = useRef(0);
    // `pending` on links_valid means "not checked yet" until the user starts a check.
    const checkingRef = useRef(false);
    const onLoadedRef = useRef(onLoaded);
    onLoadedRef.current = onLoaded;

    const stopPolling = () => {
        if (pollTimer.current !== null) {
            window.clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    };

    const apply = useCallback((next: CampaignChecklist) => {
        setChecklist(next);
        setUnavailable(false);
        onLoadedRef.current?.(next);
        const pending = checkingRef.current && next.items.some((item) => item.key === 'links_valid' && item.status === 'pending');
        stopPolling();
        if (pending && polls.current < MAX_POLLS) {
            polls.current += 1;
            pollTimer.current = window.setTimeout(() => {
                void fetchCampaignChecklist(campaignId).then(apply).catch(() => undefined);
            }, POLL_MS);
        } else {
            checkingRef.current = false;
            setCheckingLinks(false);
        }
    }, [campaignId]);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            polls.current = 0;
            apply(await fetchCampaignChecklist(campaignId));
        } catch (err) {
            setChecklist(null);
            onLoadedRef.current?.(null);
            if (apiStatus(err) === 404) {
                setUnavailable(true);
            } else {
                setError(apiErrorMessage(err));
            }
        } finally {
            setLoading(false);
        }
    }, [campaignId, apply]);

    useEffect(() => {
        void load();

        return stopPolling;
    }, [load, refreshKey]);

    async function handleCheckLinks() {
        checkingRef.current = true;
        setCheckingLinks(true);
        setError(null);
        try {
            polls.current = 0;
            apply(await checkCampaignLinks(campaignId));
        } catch (err) {
            checkingRef.current = false;
            setCheckingLinks(false);
            setError(apiErrorMessage(err));
        }
    }

    function displayStatus(item: CampaignChecklistItem): CampaignChecklistItemStatus {
        return item.key === 'links_valid' && item.status === 'pending' && !checkingLinks ? 'skipped' : item.status;
    }

    function icon(status: CampaignChecklistItemStatus): ReactElement {
        switch (status) {
            case 'ok':
                return <CheckmarkCircleFilled className={styles.ok} />;
            case 'warning':
                return <WarningFilled className={styles.warning} />;
            case 'error':
                return <DismissCircleFilled className={styles.error} />;
            case 'pending':
                return <Spinner size="extra-tiny" />;
            default:
                return <SubtractCircleRegular className={styles.neutral} />;
        }
    }

    function details(item: CampaignChecklistItem): string | null {
        if (item.key === 'links_valid' && item.details && Array.isArray(item.details.broken) && item.details.broken.length > 0) {
            return formatText(t.campaignSetup.checklist.brokenLinks, item.details.broken.length);
        }

        if (item.key === 'merge_tags' && item.details && Array.isArray(item.details.unknown) && item.details.unknown.length > 0) {
            return formatText(t.campaignSetup.checklist.unknownTags, (item.details.unknown as string[]).join(', '));
        }

        return null;
    }

    return (
        <section className={styles.root} aria-label={t.campaignSetup.checklist.title}>
            <div className={styles.header}>
                <Text weight="semibold" size={500}>
                    {t.campaignSetup.checklist.title}
                </Text>
                {checklist && (
                    <Badge appearance="tint" color={OVERALL_BADGE[checklist.status]}>
                        {t.campaignSetup.checklistStatus[checklist.status]}
                    </Badge>
                )}
            </div>

            {loading && !checklist && !unavailable ? (
                <Spinner size="tiny" label={t.common.loading} />
            ) : unavailable ? (
                <MessageBar intent="info" icon={<InfoRegular />}>
                    <MessageBarBody>{t.common.notFound}</MessageBarBody>
                </MessageBar>
            ) : checklist ? (
                <>
                    <Text size={200} className={styles.message}>
                        {checklist.can_schedule ? t.campaignSetup.checklist.allGood : t.campaignSetup.checklist.blocking}
                    </Text>
                    <ul className={styles.items}>
                        {checklist.items.map((item) => {
                            const extra = details(item);

                            return (
                                <li key={item.key} className={styles.item}>
                                    <span className={styles.icon}>{icon(displayStatus(item))}</span>
                                    <div className={styles.text}>
                                        <div className={styles.titleRow}>
                                            <Text weight="semibold">{t.campaignSetup.checklistItems[item.key] ?? item.key}</Text>
                                            <Badge appearance="outline" size="small" color={STATUS_BADGE[displayStatus(item)]}>
                                                {t.campaignSetup.checklistItemStatus[displayStatus(item)]}
                                            </Badge>
                                        </div>
                                        {item.message && (
                                            <Text size={200} className={styles.message}>
                                                {item.message}
                                            </Text>
                                        )}
                                        {extra && (
                                            <Text size={200} className={styles.error}>
                                                {extra}
                                            </Text>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    {canCheckLinks && (
                        <Button
                            appearance="secondary"
                            icon={checkingLinks ? <Spinner size="tiny" /> : <LinkRegular />}
                            disabled={checkingLinks}
                            onClick={() => void handleCheckLinks()}
                        >
                            {checkingLinks ? t.campaignSetup.checklist.checking : t.campaignSetup.checklist.checkLinks}
                        </Button>
                    )}
                </>
            ) : null}

            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
        </section>
    );
}
