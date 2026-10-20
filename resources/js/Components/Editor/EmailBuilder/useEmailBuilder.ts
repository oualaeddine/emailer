/**
 * WP-60 — owns the editor handle: mount, selection / change subscriptions,
 * device, undo / redo, dirty state and the save pipeline (docs/46 §7):
 *
 * 1. `mjml = composeMjml(mjml-code, global_styles)`;
 * 2. browser compile (`mjml-code-to-html`) — preview + trusted fallback only;
 * 3. `POST /templates/mjml/validate` — the server compiles, sanitises and
 *    returns **its** HTML, or the MJML errors (save aborted);
 * 4. host `onSave({ design_json, html })` — 422 `design_json.mjml` is listed.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import type { EmailDesign, MjmlValidationError } from '@/Lib/types/editor';
import { fetchEditorMergeTags, toDesignSaveError, validateMjml } from '@/Lib/api/editor';
import { createEditor, type BuilderDevice, type BuilderSelection, type EngineHandle } from './engine/createEditor';
import type { EmailBlockDefinition } from './engine/blocks';
import { buildDesign, resolveSource } from './engine/globalStyles';
import type { EmailBuilderProps } from './types';
import { text } from './strings';

export interface BuilderFeedback {
    intent: 'success' | 'error';
    message: string;
    errors: MjmlValidationError[];
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

export function useEmailBuilder({ source, onSave }: Pick<EmailBuilderProps, 'source' | 'onSave'>) {
    const canvasRef = useRef<HTMLDivElement>(null);
    const styleManagerRef = useRef<HTMLDivElement>(null);
    const layerManagerRef = useRef<HTMLDivElement>(null);
    const handleRef = useRef<EngineHandle | null>(null);
    const savedDesignRef = useRef<EmailDesign | null>(source.design);
    const savingRef = useRef(false);
    const onSaveRef = useRef(onSave);
    onSaveRef.current = onSave;

    const [ready, setReady] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [blocks, setBlocks] = useState<EmailBlockDefinition[]>([]);
    const [selection, setSelection] = useState<BuilderSelection | null>(null);
    const [device, setDeviceState] = useState<BuilderDevice>('desktop');
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [savedAt, setSavedAt] = useState<Date | null>(null);
    const [feedback, setFeedback] = useState<BuilderFeedback | null>(null);

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
                });
                handleRef.current = handle;
                const sync = () => {
                    setCanUndo(handle.hasUndo());
                    setCanRedo(handle.hasRedo());
                    setDirty(handle.isDirty());
                };
                unsubscribe = [handle.onSelect(setSelection), handle.onChange(sync)];
                setBlocks(handle.blocks());
                sync();
                setReady(true);
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

    /** Browser-compiled HTML of the current design (preview only). */
    const previewHtml = useCallback((): string => handleRef.current?.compile().html ?? '', []);

    const save = useCallback(async (): Promise<boolean> => {
        const handle = handleRef.current;
        if (!handle || savingRef.current) {
            return false;
        }
        savingRef.current = true;
        setSaving(true);
        setFeedback(null);
        try {
            const mjml = handle.getMjml();
            const client = handle.compile(mjml);
            const result = await validateMjml(mjml, client.html);
            if (!result.valid) {
                const messages = result.errors.map((e) => e.message).join(' ; ');
                setFeedback({ intent: 'error', message: messages ? '' : text.compilerUnavailable, errors: result.errors });

                return false;
            }
            const design = buildDesign(mjml, handle.getProject(), handle.getGlobalStyles());
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

    /** Back to the last saved design (the current version on the server). */
    const restoreSaved = useCallback(() => {
        const handle = handleRef.current;
        if (!handle) {
            return;
        }
        handle.load(resolveSource({ design: savedDesignRef.current, html: initialSource.current.html }));
        setSelection(null);
        setCanUndo(false);
        setCanRedo(false);
        setDirty(false);
        setFeedback({ intent: 'success', message: text.restoredLocal, errors: [] });
    }, []);

    const isDirty = useCallback(() => handleRef.current?.isDirty() ?? false, []);

    return {
        refs: { canvasRef, styleManagerRef, layerManagerRef },
        ready,
        loadError,
        blocks,
        selection,
        device,
        canUndo,
        canRedo,
        dirty,
        saving,
        savedAt,
        feedback,
        dismissFeedback: () => setFeedback(null),
        setDevice,
        undo,
        redo,
        setAttributes,
        startBlockDrag,
        endBlockDrag,
        previewHtml,
        save,
        restoreSaved,
        isDirty,
    };
}
