import { useCallback, useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Button,
    Dropdown,
    Field,
    Input,
    Option,
    Tab,
    TabList,
    Text,
    Drawer,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    makeStyles,
    tokens,
    type SelectTabData,
} from '@fluentui/react-components';
import { HistoryRegular, DesktopRegular, TabletRegular, PhoneRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { RichTextEditor } from '@/Components/Composer/RichTextEditor';
import { useText } from '@/Hooks/useText';
import { PageHelp } from '@/Components/Help/PageHelp';
import { HelpButton } from '@/Components/Help/HelpButton';
import {
    autosaveDraft,
    createDraft,
    fetchDraftVersions,
    fetchSignatures,
    restoreDraftVersion,
    saveDraftVersion,
} from '@/Lib/api/composer';
import type { Draft, DraftVersion, Signature } from '@/Lib/types/composer';

const useStyles = makeStyles({
    layout: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        maxWidth: '900px',
    },
    toolbarRow: {
        display: 'flex',
        flexWrap: 'wrap',
        justifyContent: 'space-between',
        alignItems: 'center',
        rowGap: tokens.spacingVerticalS,
        columnGap: tokens.spacingHorizontalS,
    },
    previewFrame: {
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        margin: '0 auto',
        width: '100%',
        boxSizing: 'border-box',
        transition: 'max-width 0.2s ease, width 0.2s ease',
    },
    versionItem: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: tokens.spacingVerticalS,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
});

const PREVIEW_WIDTHS: Record<'desktop' | 'tablet' | 'mobile', string> = {
    desktop: '640px',
    tablet: '480px',
    mobile: '375px',
};

const AUTOSAVE_DEBOUNCE_MS = 3000;

/**
 * docs/11-email-composer.md — Email Composer.
 * Rich text + HTML source modes implemented (§11.2); the drag-and-drop
 * block mode is deferred to when the Templates module's block library
 * exists (docs/34-roadmap.md build order places Templates after Composer).
 */
export default function ComposerIndex() {
    const styles = useStyles();
    const t = useText();

    const [draft, setDraft] = useState<Draft | null>(null);
    const [subject, setSubject] = useState('');
    const [htmlBody, setHtmlBody] = useState('');
    const [mode, setMode] = useState<'rich' | 'html'>('rich');
    const [preview, setPreview] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
    const [signatures, setSignatures] = useState<Signature[]>([]);
    const [signatureId, setSignatureId] = useState<string | null>(null);
    const [versionsOpen, setVersionsOpen] = useState(false);
    const [versions, setVersions] = useState<DraftVersion[]>([]);
    const [savingState, setSavingState] = useState<'idle' | 'saving' | 'saved'>('idle');

    const autosaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const draftRef = useRef<Draft | null>(null);

    useEffect(() => {
        void fetchSignatures().then((list) => {
            setSignatures(list);
            const defaultSig = list.find((s) => s.is_default);
            if (defaultSig) {
                setSignatureId(defaultSig.id);
            }
        });
    }, []);

    const ensureDraft = useCallback(async (): Promise<Draft> => {
        if (draftRef.current) {
            return draftRef.current;
        }
        const created = await createDraft({});
        draftRef.current = created;
        setDraft(created);

        return created;
    }, []);

    const scheduleAutosave = useCallback(
        (nextSubject: string, nextHtmlBody: string) => {
            if (autosaveTimer.current) {
                clearTimeout(autosaveTimer.current);
            }
            autosaveTimer.current = setTimeout(async () => {
                setSavingState('saving');
                const current = await ensureDraft();
                const updated = await autosaveDraft(current.id, {
                    subject: nextSubject,
                    html_body: nextHtmlBody,
                    signature_id: signatureId,
                });
                draftRef.current = updated;
                setDraft(updated);
                setSavingState('saved');
            }, AUTOSAVE_DEBOUNCE_MS);
        },
        [ensureDraft, signatureId],
    );

    function handleSubjectChange(value: string) {
        setSubject(value);
        scheduleAutosave(value, htmlBody);
    }

    function handleBodyChange(value: string) {
        setHtmlBody(value);
        scheduleAutosave(subject, value);
    }

    async function handleSaveVersion() {
        const current = await ensureDraft();
        await saveDraftVersion(current.id);
    }

    async function openVersionHistory() {
        if (!draftRef.current) {
            return;
        }
        const list = await fetchDraftVersions(draftRef.current.id);
        setVersions(list);
        setVersionsOpen(true);
    }

    async function handleRestore(versionId: number) {
        if (!draftRef.current) {
            return;
        }
        const restored = await restoreDraftVersion(draftRef.current.id, versionId);
        setDraft(restored);
        setSubject(restored.subject ?? '');
        setHtmlBody(restored.html_body ?? '');
        setVersionsOpen(false);
    }

    const signatureHtml = signatures.find((s) => s.id === signatureId)?.html_content ?? '';

    return (
        <AppShell>
            <Head title={t.composer.title} />
            <div className={styles.layout}>
                <PageHelp
                    topic="composer"
                    title={t.composer.title}
                    actions={
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalS, alignItems: 'center' }}>
                            {savingState === 'saving' && <Text size={200}>{t.composer.saving}</Text>}
                            {savingState === 'saved' && draft && (
                                <Text size={200}>
                                    {t.composer.savedAt} {new Date(draft.updated_at ?? '').toLocaleTimeString('fr-FR')}
                                </Text>
                            )}
                            <Button icon={<HistoryRegular />} onClick={openVersionHistory}>
                                {t.composer.versionHistory}
                            </Button>
                            <Button appearance="primary" onClick={handleSaveVersion}>
                                {t.composer.saveVersion}
                            </Button>
                        </div>
                    }
                />

                <Field label={t.composer.subject}>
                    <Input value={subject} onChange={(_, data) => handleSubjectChange(data.value)} />
                </Field>

                <Field label={t.composer.signature}>
                    <Dropdown
                        value={signatures.find((s) => s.id === signatureId)?.name ?? t.composer.noSignature}
                        onOptionSelect={(_, data) => setSignatureId(data.optionValue ?? null)}
                    >
                        <Option value="">{t.composer.noSignature}</Option>
                        {signatures.map((s) => (
                            <Option key={s.id} value={s.id}>
                                {s.name}
                            </Option>
                        ))}
                    </Dropdown>
                </Field>

                <TabList
                    selectedValue={mode}
                    onTabSelect={(_, data: SelectTabData) => setMode(data.value as 'rich' | 'html')}
                >
                    <Tab value="rich">{t.composer.richText}</Tab>
                    <Tab value="html">{t.composer.htmlSource}</Tab>
                </TabList>

                {mode === 'rich' ? (
                    <RichTextEditor value={htmlBody} onChange={handleBodyChange} />
                ) : (
                    <textarea
                        value={htmlBody}
                        onChange={(e) => handleBodyChange(e.target.value)}
                        rows={16}
                        style={{ fontFamily: 'monospace', width: '100%', padding: tokens.spacingVerticalM }}
                    />
                )}

                <div className={styles.toolbarRow}>
                    <Text weight="semibold">Aperçu</Text>
                    <div style={{ display: 'flex', gap: tokens.spacingHorizontalXS }}>
                        <Button
                            icon={<DesktopRegular />}
                            appearance={preview === 'desktop' ? 'primary' : 'subtle'}
                            onClick={() => setPreview('desktop')}
                            aria-label={t.composer.previewDesktop}
                        />
                        <Button
                            icon={<TabletRegular />}
                            appearance={preview === 'tablet' ? 'primary' : 'subtle'}
                            onClick={() => setPreview('tablet')}
                            aria-label={t.composer.previewTablet}
                        />
                        <Button
                            icon={<PhoneRegular />}
                            appearance={preview === 'mobile' ? 'primary' : 'subtle'}
                            onClick={() => setPreview('mobile')}
                            aria-label={t.composer.previewMobile}
                        />
                    </div>
                </div>
                <div className={styles.previewFrame} style={{ width: '100%', maxWidth: PREVIEW_WIDTHS[preview] }}>
                    <iframe
                        title="Aperçu de l'e-mail"
                        sandbox=""
                        srcDoc={`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      padding: 16px;
      color: #242424;
      line-height: 1.5;
      word-wrap: break-word;
    }
    img { max-width: 100%; height: auto; }
  </style>
</head>
<body>
  ${htmlBody}${signatureHtml}
</body>
</html>`}
                        style={{ width: '100%', height: '400px', border: 'none' }}
                    />
                </div>
            </div>

            <Drawer open={versionsOpen} onOpenChange={(_, data) => setVersionsOpen(data.open)} position="end">
                <DrawerHeader>
                    <DrawerHeaderTitle action={<HelpButton topic="composer" />}>
                        {t.composer.versionHistory}
                    </DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody>
                    {versions.map((version) => (
                        <div key={version.id} className={styles.versionItem}>
                            <div>
                                <Text weight="semibold">v{version.version_number}</Text>
                                <Text block size={200}>
                                    {version.author} —{' '}
                                    {version.created_at ? new Date(version.created_at).toLocaleString('fr-FR') : ''}
                                </Text>
                            </div>
                            <Button size="small" onClick={() => handleRestore(version.id)}>
                                {t.composer.restore}
                            </Button>
                        </div>
                    ))}
                </DrawerBody>
            </Drawer>
        </AppShell>
    );
}
