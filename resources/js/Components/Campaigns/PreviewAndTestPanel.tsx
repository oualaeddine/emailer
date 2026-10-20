import { useEffect, useState } from 'react';
import {
    Button,
    Divider,
    Drawer,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    Spinner,
    Tab,
    TabList,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { DesktopRegular, DismissRegular, PhoneRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { fetchCampaignPreview } from '@/Lib/api/campaigns';
import type { AbVariantLabel, CampaignV2Detail, TestSendResult } from '@/Lib/types/campaigns';
import { EmailFrame } from '@/Components/Campaigns/EmailFrame';
import { hasMergeTags, placeholderMergeTags } from '@/Components/Campaigns/MergeTagText';
import { InboxPreview } from '@/Components/Campaigns/SubjectCard';
import { formatText } from '@/Components/Campaigns/campaignUi';
import { TestSendForm } from '@/Components/Campaigns/TestSendDialog';

type Device = 'desktop' | 'mobile';

const useStyles = makeStyles({
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        paddingBottom: tokens.spacingVerticalXL,
    },
    stage: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusLarge,
        overflowX: 'auto',
    },
    device: {
        boxShadow: tokens.shadow8,
        borderRadius: tokens.borderRadiusMedium,
        overflow: 'hidden',
        backgroundColor: '#ffffff',
        maxWidth: '100%',
    },
    phone: {
        borderRadius: '24px',
        border: `8px solid ${tokens.colorNeutralForeground1Static}`,
    },
    note: {
        color: tokens.colorNeutralForeground3,
    },
    empty: {
        color: tokens.colorNeutralForeground3,
        padding: tokens.spacingVerticalXXL,
    },
});

export interface PreviewAndTestPanelProps {
    open: boolean;
    campaign: CampaignV2Detail;
    onClose: () => void;
    onTestSent?: (result: TestSendResult) => void;
}

/**
 * Sticky-header **Aperçu & test** (docs/43 §43.4.2): desktop / mobile
 * preview of the campaign HTML in a sandboxed iframe + test send (≤ 5).
 */
export function PreviewAndTestPanel({ open, campaign, onClose, onTestSent }: PreviewAndTestPanelProps) {
    const styles = useStyles();
    const t = useText();
    const [device, setDevice] = useState<Device>('desktop');
    const [html, setHtml] = useState<string | null>(campaign.html_body);
    const [loading, setLoading] = useState(false);
    // Subject / preview text rendered by the backend with a sample audience contact.
    const [rendered, setRendered] = useState<{ subject: string | null; previewText: string | null; sample: boolean; to: string | null } | null>(null);
    const abVariants = campaign.ab_test_enabled ? (campaign.ab_test?.variants ?? []) : [];
    const [variant, setVariant] = useState<AbVariantLabel>('A');
    const activeVariant = abVariants.length > 0 ? (abVariants.find((v) => v.label === variant) ?? abVariants[0]) : null;
    const variantLabel = activeVariant?.label ?? null;
    const baseHtml = activeVariant?.html_body || campaign.html_body;

    useEffect(() => {
        if (!open) {
            return;
        }
        let cancelled = false;
        setHtml(baseHtml);
        setRendered(null);
        // Rendered preview (merge tags resolved with the first audience contact); falls back to the raw html_body.
        setLoading(Boolean(baseHtml));
        fetchCampaignPreview(campaign.id, variantLabel ? { variant: variantLabel } : {})
            .then((preview) => {
                if (cancelled) {
                    return;
                }
                if (preview.html) {
                    setHtml(preview.html);
                }
                setRendered({
                    subject: preview.subject,
                    previewText: preview.preview_text,
                    sample: Boolean(preview.sample && preview.contact_id),
                    to: preview.to,
                });
            })
            .catch(() => undefined)
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [open, campaign.id, baseHtml, variantLabel]);

    const panel = t.campaignSetup.previewPanel;
    const rawSubject = (activeVariant ? activeVariant.subject : campaign.subject) ?? '';
    const rawPreviewText = (activeVariant ? activeVariant.preview_text : campaign.preview_text) ?? '';
    // Rendered with a real contact when the audience has one, else readable « [SOCIETE] » placeholders.
    const subject = rendered?.sample ? (rendered.subject ?? rawSubject) : placeholderMergeTags(rawSubject);
    const previewText = rendered?.sample ? (rendered.previewText ?? rawPreviewText) : placeholderMergeTags(rawPreviewText);
    const width = device === 'desktop' ? 680 : 375;

    return (
        <Drawer
            type="overlay"
            position="end"
            size="large"
            open={open}
            onOpenChange={(_, data) => !data.open && onClose()}
            style={{ width: 'min(860px, 100vw)' }}
        >
            <DrawerHeader>
                <DrawerHeaderTitle
                    action={<Button appearance="subtle" icon={<DismissRegular />} aria-label={t.common.close} onClick={onClose} />}
                >
                    {panel.title}
                </DrawerHeaderTitle>
            </DrawerHeader>
            <DrawerBody className={styles.body}>
                <InboxPreview
                    senderName={campaign.sender?.name ?? t.campaigns.noSender}
                    subject={subject}
                    previewText={previewText}
                />
                {rendered?.sample && rendered.to ? (
                    <Text size={200} className={styles.note}>
                        {formatText(panel.sampleContact, rendered.to)}
                    </Text>
                ) : (
                    rendered &&
                    (hasMergeTags(rawSubject) || hasMergeTags(rawPreviewText)) && (
                        <Text size={200} className={styles.note}>
                            {panel.samplePlaceholder}
                        </Text>
                    )
                )}
                {abVariants.length > 0 && (
                    <TabList
                        size="small"
                        aria-label={panel.abVersion}
                        selectedValue={variantLabel ?? 'A'}
                        onTabSelect={(_, data) => setVariant(data.value as AbVariantLabel)}
                    >
                        {abVariants.map((v) => (
                            <Tab key={v.label} value={v.label}>
                                {panel.abVersionLabel.replace('%s', v.label)}
                            </Tab>
                        ))}
                    </TabList>
                )}
                <TabList selectedValue={device} onTabSelect={(_, data) => setDevice(data.value as Device)}>
                    <Tab value="desktop" icon={<DesktopRegular />}>
                        {panel.desktop}
                    </Tab>
                    <Tab value="mobile" icon={<PhoneRegular />}>
                        {panel.mobile}
                    </Tab>
                </TabList>
                <div className={styles.stage}>
                    {loading ? (
                        <Spinner label={t.common.loading} />
                    ) : html ? (
                        <div className={mergeClasses(styles.device, device === 'mobile' && styles.phone)} style={{ width }}>
                            <EmailFrame html={html} title={panel.title} width={width} height={device === 'desktop' ? 640 : 667} />
                        </div>
                    ) : (
                        <Text className={styles.empty}>{t.campaignSetup.design.noDesign}</Text>
                    )}
                </div>
                <Divider />
                <Text weight="semibold">{panel.sendTest}</Text>
                <TestSendForm
                    campaignId={campaign.id}
                    variant={variantLabel}
                    lastTestSentAt={campaign.test_sent_at}
                    disabled={!campaign.html_body}
                    onSent={onTestSent}
                />
            </DrawerBody>
        </Drawer>
    );
}
