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
import type { CampaignV2Detail, TestSendResult } from '@/Lib/types/campaigns';
import { EmailFrame } from '@/Components/Campaigns/EmailFrame';
import { InboxPreview } from '@/Components/Campaigns/SubjectCard';
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
        border: `8px solid ${tokens.colorNeutralForeground1}`,
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

    useEffect(() => {
        if (!open) {
            return;
        }
        let cancelled = false;
        setHtml(campaign.html_body);
        if (!campaign.html_body) {
            return;
        }
        // Rendered preview (merge tags resolved); falls back to the raw html_body.
        setLoading(true);
        fetchCampaignPreview(campaign.id)
            .then((preview) => {
                if (!cancelled && preview.html) {
                    setHtml(preview.html);
                }
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
    }, [open, campaign.id, campaign.html_body]);

    const panel = t.campaignSetup.previewPanel;
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
                    subject={campaign.subject ?? ''}
                    previewText={campaign.preview_text ?? ''}
                />
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
                    lastTestSentAt={campaign.test_sent_at}
                    disabled={!campaign.html_body}
                    onSent={onTestSent}
                />
            </DrawerBody>
        </Drawer>
    );
}
