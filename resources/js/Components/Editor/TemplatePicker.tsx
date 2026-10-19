import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Input,
    MessageBar,
    MessageBarBody,
    Tab,
    TabList,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import type { SelectTabData } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import {
    copyCampaignDesign,
    fetchPreviousCampaigns,
    fetchTemplatesV2,
    saveCampaignDesign,
} from '@/Lib/api/templates';
import type { CampaignDesignCopyInput, CampaignV2, CampaignV2Detail } from '@/Lib/types/campaigns';
import type { TemplateV2 } from '@/Lib/types/templates';
import { TemplatePickerCode } from '@/Components/Editor/TemplatePickerCode';
import { PickerItem, TemplatePickerGallery } from '@/Components/Editor/TemplatePickerGallery';
import { TemplatePickerRichText } from '@/Components/Editor/TemplatePickerRichText';

const useStyles = makeStyles({
    surface: { maxWidth: '960px', width: '95vw' },
    content: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, minHeight: '420px' },
    panel: { overflowY: 'auto', maxHeight: '60vh', paddingTop: tokens.spacingVerticalS },
});

type PickerTab = 'gallery' | 'mine' | 'previousCampaigns' | 'code' | 'richText';

export interface TemplatePickerProps {
    open: boolean;
    onClose: () => void;
    campaignId: string;
    onApplied: (campaign: CampaignV2Detail) => void;
}

const BLANK_ID = '__blank__';

/**
 * docs/43 §43.4.3 — template picker modal. Performs the design write itself
 * (docs/45 §45.4.4: PUT /campaigns/{id}/design or POST …/design/copy) and
 * hands the updated campaign to `onApplied`.
 */
export default function TemplatePicker({ open, onClose, campaignId, onApplied }: TemplatePickerProps) {
    const styles = useStyles();
    const t = useText();
    const [tab, setTab] = useState<PickerTab>('gallery');
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [applying, setApplying] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [gallery, setGallery] = useState<TemplateV2[]>([]);
    const [mine, setMine] = useState<TemplateV2[]>([]);
    const [campaigns, setCampaigns] = useState<CampaignV2[]>([]);

    const load = useCallback(async (which: PickerTab, q: string) => {
        setLoading(true);
        setError(null);
        try {
            if (which === 'gallery') {
                setGallery((await fetchTemplatesV2({ is_gallery: true, q: q || undefined })).data);
            } else if (which === 'mine') {
                setMine((await fetchTemplatesV2({ is_gallery: false, scope: 'marketing', q: q || undefined })).data);
            } else if (which === 'previousCampaigns') {
                setCampaigns(await fetchPreviousCampaigns(q));
            }
        } catch {
            setError(t.common.genericError);
        } finally {
            setLoading(false);
        }
    }, [t.common.genericError]);

    useEffect(() => {
        if (!open || tab === 'code' || tab === 'richText') {
            return;
        }
        const handle = window.setTimeout(() => void load(tab, query), query ? 300 : 0);

        return () => window.clearTimeout(handle);
    }, [open, tab, query, load]);

    async function apply(task: () => Promise<CampaignV2Detail>) {
        setApplying(true);
        setError(null);
        try {
            const campaign = await task();
            onApplied(campaign);
            onClose();
        } catch {
            setError(t.common.genericError);
        } finally {
            setApplying(false);
        }
    }

    const copy = (input: CampaignDesignCopyInput) => apply(() => copyCampaignDesign(campaignId, input));
    const saveHtml = (editorType: 'html' | 'rich_text') => (html: string) =>
        apply(() => saveCampaignDesign(campaignId, { editor_type: editorType, html_body: html, design_json: null }));

    const galleryItems = useMemo<PickerItem[]>(
        () => [
            { id: BLANK_ID, name: t.templatePicker.blank, thumbnailUrl: null },
            ...gallery.map((tpl) => ({ id: tpl.id, name: tpl.name, thumbnailUrl: tpl.thumbnail_url })),
        ],
        [gallery, t.templatePicker.blank],
    );
    const mineItems = useMemo<PickerItem[]>(
        () => mine.map((tpl) => ({ id: tpl.id, name: tpl.name, thumbnailUrl: tpl.thumbnail_url, description: tpl.category })),
        [mine],
    );
    const campaignItems = useMemo<PickerItem[]>(
        () => campaigns.map((c) => ({ id: c.id, name: c.name, thumbnailUrl: c.thumbnail_url })),
        [campaigns],
    );

    const showSearch = tab === 'gallery' || tab === 'mine' || tab === 'previousCampaigns';

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{t.templatePicker.title}</DialogTitle>
                    <DialogContent className={styles.content}>
                        <TabList selectedValue={tab} onTabSelect={(_, data: SelectTabData) => { setTab(data.value as PickerTab); setQuery(''); }}>
                            <Tab value="gallery">{t.templatePicker.tabs.gallery}</Tab>
                            <Tab value="mine">{t.templatePicker.tabs.mine}</Tab>
                            <Tab value="previousCampaigns">{t.templatePicker.tabs.previousCampaigns}</Tab>
                            <Tab value="code">{t.templatePicker.tabs.code}</Tab>
                            <Tab value="richText">{t.templatePicker.tabs.richText}</Tab>
                        </TabList>
                        {showSearch && (
                            <Input value={query} placeholder={t.templatePicker.searchPlaceholder} onChange={(_, data) => setQuery(data.value)} />
                        )}
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                        <div className={styles.panel}>
                            {tab === 'gallery' && (
                                <TemplatePickerGallery
                                    items={galleryItems}
                                    loading={loading}
                                    disabled={applying}
                                    emptyLabel={t.templatePicker.noTemplates}
                                    onSelect={(id) =>
                                        void copy(id === BLANK_ID ? { source_type: 'blank' } : { source_type: 'gallery', source_id: id })
                                    }
                                />
                            )}
                            {tab === 'mine' && (
                                <TemplatePickerGallery
                                    items={mineItems}
                                    loading={loading}
                                    disabled={applying}
                                    emptyLabel={t.templatePicker.noTemplates}
                                    onSelect={(id) => void copy({ source_type: 'template', source_id: id })}
                                />
                            )}
                            {tab === 'previousCampaigns' && (
                                <TemplatePickerGallery
                                    items={campaignItems}
                                    loading={loading}
                                    disabled={applying}
                                    emptyLabel={t.templatePicker.noCampaigns}
                                    onSelect={(id) => void copy({ source_type: 'campaign', source_id: id })}
                                />
                            )}
                            {tab === 'code' && <TemplatePickerCode disabled={applying} onUse={(html) => void saveHtml('html')(html)} />}
                            {tab === 'richText' && (
                                <TemplatePickerRichText disabled={applying} onUse={(html) => void saveHtml('rich_text')(html)} />
                            )}
                        </div>
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onClose}>
                            {t.common.cancel}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}

export { TemplatePicker };
