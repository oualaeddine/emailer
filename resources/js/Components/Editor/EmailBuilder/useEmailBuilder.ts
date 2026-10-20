/**
 * WP-60 — owns the editor handle: mount, selection / change subscriptions,
 * device, undo / redo, dirty state and the save pipeline (docs/46 §7):
 *
 * 1. `dynamic_blocks` collected, then `mjml = composeMjml(mjml-code, global_styles)`;
 * 2. browser compile (`mjml-code-to-html`) — preview + trusted fallback only;
 * 3. `POST /templates/mjml/validate` — the server compiles, sanitises and
 *    returns **its** HTML, or the MJML errors (save aborted);
 * 4. host `onSave({ design_json, html })` — 422 `design_json.mjml` is listed.
 *
 * WP-61 adds the *Style* tab (`global_styles`), pre-made / saved sections,
 * the dynamic-content rule and the design reload used by version restore.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import type { EmailDesign, EmailGlobalStyles, MjmlValidationError, PremadeSection, SavedSection } from '@/Lib/types/editor';
import type { RuleTree } from '@/Lib/types/segments';
import {
    createSavedSection,
    deleteSavedSection,
    fetchEditorMergeTags,
    fetchPremadeSections,
    fetchSavedSections,
    renameSavedSection,
    toDesignSaveError,
    validateMjml,
} from '@/Lib/api/editor';
import { createEditor, type BuilderDevice, type BuilderSelection, type EngineHandle } from './engine/createEditor';
import type { EmailBlockDefinition } from './engine/blocks';
import { buildDesign, normaliseGlobalStyles, resolveSource } from './engine/globalStyles';
import type { EmailBuilderProps, EmailBuilderSource } from './types';
import { text } from './strings';

export interface BuilderFeedback {
    intent: 'success' | 'error';
    message: string;
    errors: MjmlValidationError[];
}

/** Dynamic-content rule being edited in the dialog. */
export interface DynamicEditing {
    id: string;
    condition: RuleTree | null;
}

function statusMessage(status: number | null): string {
    switch (status) {
        case 403:
            return text.common.forbidden;
        case 404:
            return text.common.notFound;
        case 409:
            return text.common.conflict;
        default:
            return text.common.genericError;
    }
}

const PREMADE_PREFIX = 'pj-premade-';
const SAVED_PREFIX = 'pj-saved-';

function premadeBlock(section: PremadeSection): EmailBlockDefinition {
    return {
        id: `${PREMADE_PREFIX}${section.key}`,
        key: 'columns',
        label: section.label,
        category: 'sections',
        icon: 'section',
        content: section.mjml,
        thumbnail: section.thumbnail_url || null,
        group: section.category,
    };
}

function savedBlock(section: SavedSection): EmailBlockDefinition {
    const component = section.component;
    const hasComponent = typeof component === 'object' && component !== null && typeof component.type === 'string';

    return {
        id: `${SAVED_PREFIX}${section.id}`,
        key: 'saved_section',
        label: section.name,
        category: 'saved',
        icon: 'saved',
        content: hasComponent ? component : section.mjml,
        thumbnail: section.thumbnail_url,
        savedId: section.id,
    };
}

function errorMessage(error: unknown): string {
    const parsed = toDesignSaveError(error);

    return parsed.errors[0]?.message ?? parsed.message ?? statusMessage(parsed.status);
}

export function useEmailBuilder({ source, onSave }: Pick<EmailBuilderProps, 'source' | 'onSave'>) {
    const canvasRef = useRef<HTMLDivElement>(null);
    const styleManagerRef = useRef<HTMLDivElement>(null);
    const layerManagerRef = useRef<HTMLDivElement>(null);
    const handleRef = useRef<EngineHandle | null>(null);
    const savedDesignRef = useRef<EmailDesign | null>(source.design);
    const htmlRef = useRef<string | null | undefined>(source.html);
    const savingRef = useRef(false);
    const onSaveRef = useRef(onSave);
    onSaveRef.current = onSave;

    const [ready, setReady] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [blocks, setBlocks] = useState<EmailBlockDefinition[]>([]);
    const [sectionsLoaded, setSectionsLoaded] = useState(false);
    const [selection, setSelection] = useState<BuilderSelection | null>(null);
    const [device, setDeviceState] = useState<BuilderDevice>('desktop');
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [savedAt, setSavedAt] = useState<Date | null>(null);
    const [feedback, setFeedback] = useState<BuilderFeedback | null>(null);
    const [globalStyles, setGlobalStylesState] = useState<EmailGlobalStyles>(() => normaliseGlobalStyles(source.design?.global_styles));
    const [dynamicEditing, setDynamicEditing] = useState<DynamicEditing | null>(null);

    // Mount once; the source is read at mount time only (hosts key the builder by id).
    const initialSource = useRef(source);
    useEffect(() => {
        let cancelled = false;
        let unsubscribe: (() => void)[] = [];

        fetchEditorMergeTags()
            .then((mergeTags) => {
                if (cancelled || !canvasRef.current || !styleManagerRef.current || !layerManagerRef.current) {
                    return;
                }
                const handle = createEditor({
                    container: canvasRef.current,
                    styleManagerEl: styleManagerRef.current,
                    layerManagerEl: layerManagerRef.current,
                    source: resolveSource(initialSource.current),
                    mergeTags,
                    onDynamicDrop: (id) => setDynamicEditing({ id, condition: null }),
                });
                handleRef.current = handle;
                const sync = () => {
                    setCanUndo(handle.hasUndo());
                    setCanRedo(handle.hasRedo());
                    setDirty(handle.isDirty());
                };
                unsubscribe = [handle.onSelect(setSelection), handle.onChange(sync)];
                setBlocks(handle.blocks());
                setGlobalStylesState(handle.getGlobalStyles());
                sync();
                setReady(true);

                // Sections / Enregistrés tabs (failures leave the tab empty).
                Promise.allSettled([fetchPremadeSections(), fetchSavedSections()]).then(([premade, saved]) => {
                    if (cancelled || handleRef.current !== handle) {
                        return;
                    }
                    const extra = [
                        ...(premade.status === 'fulfilled' ? premade.value.map(premadeBlock) : []),
                        ...(saved.status === 'fulfilled' ? saved.value.map(savedBlock) : []),
                    ];
                    setBlocks(handle.addBlocks(extra));
                    setSectionsLoaded(true);
                });
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadError(true);
                }
            });

        return () => {
            cancelled = true;
            unsubscribe.forEach((off) => off());
            handleRef.current?.destroy();
            handleRef.current = null;
        };
    }, []);

    // Native "leave page" prompt while changes are unsaved.
    useEffect(() => {
        if (!dirty) {
            return;
        }
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [dirty]);

    const setDevice = useCallback((next: BuilderDevice) => {
        setDeviceState(next);
        handleRef.current?.setDevice(next);
    }, []);

    const undo = useCallback(() => handleRef.current?.undo(), []);
    const redo = useCallback(() => handleRef.current?.redo(), []);

    const setAttributes = useCallback((values: Record<string, string>) => handleRef.current?.setAttributes(values), []);

    const startBlockDrag = useCallback((id: string, event: DragEvent) => handleRef.current?.startBlockDrag(id, event), []);
    const endBlockDrag = useCallback(() => handleRef.current?.endBlockDrag(), []);
    const insertBlock = useCallback((id: string) => handleRef.current?.insertBlock(id), []);

    /** Browser-compiled HTML of the current design (preview only). */
    const previewHtml = useCallback((): string => handleRef.current?.compile().html ?? '', []);

    // ---------------------------------------------------------------- Style tab

    const updateGlobalStyles = useCallback((next: EmailGlobalStyles) => {
        setGlobalStylesState(next);
        handleRef.current?.setGlobalStyles(next);
    }, []);

    // ---------------------------------------------------------------- dynamic content

    const editDynamic = useCallback((editing: DynamicEditing | null) => setDynamicEditing(editing), []);

    const applyDynamicCondition = useCallback((id: string, condition: RuleTree | null) => {
        handleRef.current?.setDynamicCondition(id, condition);
        setDynamicEditing(null);
    }, []);

    const removeDynamic = useCallback((id: string) => {
        handleRef.current?.clearDynamic(id);
        setDynamicEditing(null);
    }, []);

    // ---------------------------------------------------------------- saved sections

    const saveSection = useCallback(async (name: string): Promise<boolean> => {
        const handle = handleRef.current;
        const snapshot = handle?.getSelectedSection();
        if (!handle || !snapshot) {
            setFeedback({ intent: 'error', message: text.onlySectionsCanBeSaved, errors: [] });

            return false;
        }
        try {
            const created = await createSavedSection({ name, mjml: snapshot.mjml, component: snapshot.component });
            setBlocks(handle.addBlocks([savedBlock(created)]));
            setFeedback({ intent: 'success', message: text.sectionSaved, errors: [] });

            return true;
        } catch (error) {
            setFeedback({ intent: 'error', message: errorMessage(error), errors: [] });

            return false;
        }
    }, []);

    const renameSection = useCallback(async (savedId: string, name: string): Promise<boolean> => {
        const handle = handleRef.current;
        try {
            const updated = await renameSavedSection(savedId, name);
            if (handle) {
                setBlocks(handle.addBlocks([savedBlock(updated)]));
            }

            return true;
        } catch (error) {
            setFeedback({ intent: 'error', message: errorMessage(error), errors: [] });

            return false;
        }
    }, []);

    const deleteSection = useCallback(async (savedId: string): Promise<boolean> => {
        const handle = handleRef.current;
        try {
            await deleteSavedSection(savedId);
            if (handle) {
                setBlocks(handle.removeBlock(`${SAVED_PREFIX}${savedId}`));
            }

            return true;
        } catch (error) {
            setFeedback({ intent: 'error', message: errorMessage(error), errors: [] });

            return false;
        }
    }, []);

    // ---------------------------------------------------------------- save / restore

    const save = useCallback(async (): Promise<boolean> => {
        const handle = handleRef.current;
        if (!handle || savingRef.current) {
            return false;
        }
        savingRef.current = true;
        setSaving(true);
        setFeedback(null);
        try {
            // Before the MJML: duplicate dynamic ids are renewed in place.
            const dynamicBlocks = handle.getDynamicBlocks();
            const mjml = handle.getMjml();
            const client = handle.compile(mjml);
            const result = await validateMjml(mjml, client.html);
            if (!result.valid) {
                const messages = result.errors.map((e) => e.message).join(' ; ');
                setFeedback({ intent: 'error', message: messages ? '' : text.compilerUnavailable, errors: result.errors });

                return false;
            }
            const design = buildDesign(mjml, handle.getProject(), handle.getGlobalStyles(), dynamicBlocks);
            await onSaveRef.current({ design_json: design, html: result.html });
            savedDesignRef.current = design;
            handle.markSaved();
            setDirty(false);
            setSavedAt(new Date());
            setFeedback({ intent: 'success', message: text.saved, errors: [] });

            return true;
        } catch (error) {
            const parsed = toDesignSaveError(error);
            setFeedback({
                intent: 'error',
                message: parsed.errors.length > 0 ? '' : parsed.message ?? statusMessage(parsed.status),
                errors: parsed.errors,
            });

            return false;
        } finally {
            savingRef.current = false;
            setSaving(false);
        }
    }, []);

    const reload = useCallback((next: EmailBuilderSource, message: string) => {
        const handle = handleRef.current;
        if (!handle) {
            return;
        }
        handle.load(resolveSource(next));
        setGlobalStylesState(handle.getGlobalStyles());
        setSelection(null);
        setDynamicEditing(null);
        setCanUndo(false);
        setCanRedo(false);
        setDirty(false);
        setFeedback({ intent: 'success', message, errors: [] });
    }, []);

    /** Back to the last saved design (local snapshot of this session). */
    const restoreSaved = useCallback(() => {
        reload({ design: savedDesignRef.current, html: htmlRef.current }, text.restoredLocal);
    }, [reload]);

    /** A server version became the current design (template versions restore). */
    const replaceDesign = useCallback(
        (design: EmailDesign | null, html: string | null) => {
            savedDesignRef.current = design;
            htmlRef.current = html;
            reload({ design, html }, text.versions.restored);
            setSavedAt(new Date());
        },
        [reload],
    );

    const isDirty = useCallback(() => handleRef.current?.isDirty() ?? false, []);

    return {
        refs: { canvasRef, styleManagerRef, layerManagerRef },
        ready,
        loadError,
        blocks,
        sectionsLoaded,
        selection,
        device,
        canUndo,
        canRedo,
        dirty,
        saving,
        savedAt,
        feedback,
        globalStyles,
        dynamicEditing,
        dismissFeedback: () => setFeedback(null),
        setDevice,
        undo,
        redo,
        setAttributes,
        startBlockDrag,
        endBlockDrag,
        insertBlock,
        previewHtml,
        updateGlobalStyles,
        editDynamic,
        applyDynamicCondition,
        removeDynamic,
        saveSection,
        renameSection,
        deleteSection,
        save,
        restoreSaved,
        replaceDesign,
        isDirty,
    };
}
