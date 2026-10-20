/**
 * WP-60 — `web: GET /campaigns/{id}/design` (`can:campaigns.create`): the
 * drag & drop EmailBuilder for a campaign (doc 43 §43.4.4). Loads
 * `GET /campaigns/{id}` and saves with `PUT /campaigns/{id}/design`
 * (`editor_type: dnd`, `design_json`, `html_body` = server-compiled HTML),
 * then returns to `/campaigns/{id}/setup`.
 */
import { useCallback, useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { MessageBar, MessageBarBody, Spinner } from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmailBuilderLazy, type EmailBuilderSaveResult, type EmailBuilderSource } from '@/Components/Editor/EmailBuilder';
import { useText } from '@/Hooks/useText';
import { fetchCampaignV2, sendCampaignTest } from '@/Lib/api/campaigns';
import { saveCampaignDesign } from '@/Lib/api/templates';
import type { CampaignV2Detail } from '@/Lib/types/campaigns';

interface Props {
    campaignId: string;
}

export default function CampaignDesign({ campaignId }: Props) {
    const t = useText();
    const [campaign, setCampaign] = useState<CampaignV2Detail | null>(null);
    const [source, setSource] = useState<EmailBuilderSource | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        fetchCampaignV2(campaignId)
            .then((detail) => {
                if (cancelled) {
                    return;
                }
                setCampaign(detail);
                setSource({ design: detail.design_json, html: detail.html_body });
            })
            .catch((err: unknown) => {
                if (cancelled) {
                    return;
                }
                const status = axios.isAxiosError(err) ? err.response?.status : undefined;
                setError(status === 404 ? t.common.notFound : status === 403 ? t.common.forbidden : t.common.genericError);
            });

        return () => {
            cancelled = true;
        };
    }, [campaignId, t]);

    const save = useCallback(
        async ({ design_json, html }: EmailBuilderSaveResult) => {
            const updated = await saveCampaignDesign(campaignId, { editor_type: 'dnd', design_json, html_body: html });
            setCampaign(updated);
        },
        [campaignId],
    );

    const sendTest = useCallback(
        async (emails: string[]) => {
            await sendCampaignTest(campaignId, { emails });
        },
        [campaignId],
    );

    const name = campaign?.name ?? t.campaignSetup.design.title;

    return (
        <AppShell>
            <Head title={`${t.campaignSetup.design.title} — ${name}`} />
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            {!error && (!campaign || !source) && <Spinner label={t.common.loading} />}
            {campaign && source && (
                <EmailBuilderLazy
                    key={campaign.id}
                    name={name}
                    source={source}
                    onSave={save}
                    onExit={() => router.visit(`/campaigns/${campaignId}/setup`)}
                    onSendTest={sendTest}
                />
            )}
        </AppShell>
    );
}
