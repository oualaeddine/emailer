/**
 * WP-60 — `web: GET /templates/{id}/edit` (`can:templates.manage`): the
 * drag & drop EmailBuilder for a template (doc 43 §43.4.4/§43.4.5). Loads
 * `GET /templates/{id}` (detail, also for gallery designs whose list rows have
 * no content) and saves with `PATCH /templates/{id}` (`editor_type: dnd`,
 * `design_json`, `html_content` = server-compiled HTML).
 */
import { useCallback, useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { MessageBar, MessageBarBody, Spinner } from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmailBuilderLazy, type EmailBuilderSaveResult, type EmailBuilderSource } from '@/Components/Editor/EmailBuilder';
import { useText } from '@/Hooks/useText';
import { fetchTemplate, testSendTemplate } from '@/Lib/api/templates';
import { fetchTemplateVersions, saveTemplateDesign } from '@/Lib/api/editor';
import type { TemplateV2Detail } from '@/Lib/types/templates';

interface Props {
    templateId: string;
}

export default function TemplateEdit({ templateId }: Props) {
    const t = useText();
    const [template, setTemplate] = useState<TemplateV2Detail | null>(null);
    const [source, setSource] = useState<EmailBuilderSource | null>(null);
    const [lastVersion, setLastVersion] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadVersions = useCallback(async () => {
        const versions = await fetchTemplateVersions(templateId);
        setLastVersion(versions && versions.length > 0 ? Math.max(...versions.map((v) => v.version_number)) : null);
    }, [templateId]);

    useEffect(() => {
        let cancelled = false;
        fetchTemplate(templateId)
            .then((detail) => {
                if (cancelled) {
                    return;
                }
                setTemplate(detail);
                setSource({ design: detail.design_json, html: detail.html_content });
            })
            .catch((err: unknown) => {
                if (cancelled) {
                    return;
                }
                const status = axios.isAxiosError(err) ? err.response?.status : undefined;
                setError(status === 404 ? t.common.notFound : status === 403 ? t.common.forbidden : t.common.genericError);
            });
        void loadVersions();

        return () => {
            cancelled = true;
        };
    }, [templateId, loadVersions, t]);

    const save = useCallback(
        async ({ design_json, html }: EmailBuilderSaveResult) => {
            const updated = await saveTemplateDesign(templateId, design_json, html);
            setTemplate(updated);
            void loadVersions();
        },
        [templateId, loadVersions],
    );

    const sendTest = useCallback(
        async (emails: string[]) => {
            await testSendTemplate(templateId, { emails });
        },
        [templateId],
    );

    return (
        <AppShell>
            <Head title={template ? `${t.templates.editTitle} — ${template.name}` : t.templates.editTitle} />
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            {!error && (!template || !source) && <Spinner label={t.common.loading} />}
            {template && source && (
                <EmailBuilderLazy
                    key={template.id}
                    name={template.name}
                    source={source}
                    onSave={save}
                    onExit={() => router.visit('/templates')}
                    onSendTest={sendTest}
                    restore={lastVersion !== null ? { versionNumber: lastVersion } : null}
                />
            )}
        </AppShell>
    );
}
