/**
 * WP-60 — public props of the EmailBuilder (docs/43 §43.4.4, docs/45 §45.6.1).
 * Type-only: safe to import from pages without pulling the editor chunk.
 */
import type { Uuid } from '@/Lib/types/common';
import type { DynamicContentBlock, EmailDesign } from '@/Lib/types/editor';

/**
 * WP-61 — one dynamic-content section of a design: the frozen
 * `DynamicContentBlock` plus the id of its `data-dynamic-rule` marker. The
 * section's content stays in the design MJML, so `variants` holds exactly
 * one entry (`condition` = "Afficher si", `mjml` = "") and `fallback_mjml`
 * is "" (nothing is shown to contacts who do not match).
 */
export interface DynamicBlockRecord extends DynamicContentBlock {
    id: string;
}

/** `design_json` written by the builder: `EmailDesign` + `dynamic_blocks`. */
export type EmailDesignWithDynamic = EmailDesign & { dynamic_blocks?: DynamicBlockRecord[] };

/** What the builder hands to its host on save (docs/45 §45.6.1). */
export interface EmailBuilderSaveResult {
    design_json: EmailDesign;
    /** HTML compiled and sanitised by the server (`POST /templates/mjml/validate`). */
    html: string;
}

/** Initial content, in priority order: design project → design MJML → legacy HTML → blank. */
export interface EmailBuilderSource {
    design: EmailDesign | null;
    /** `html_content` / `html_body` of a design that has no `design_json` (imported as an HTML block). */
    html?: string | null;
}

export interface EmailBuilderRestoreOption {
    /** Number of the last saved version (shown in the confirmation). */
    versionNumber: number;
}

export interface EmailBuilderProps {
    /** Name shown in the top bar (template or campaign name). */
    name: string;
    source: EmailBuilderSource;
    /** Persists the design. Throw (axios error) to show the server message; 422 `design_json.mjml` errors are listed. */
    onSave: (result: EmailBuilderSaveResult) => Promise<void>;
    /** Leaves the editor (after *Enregistrer et quitter*, or *Retour*). */
    onExit: () => void;
    /** *Aperçu & test* → send a test of the saved design (1–5 addresses). */
    onSendTest?: (emails: string[]) => Promise<void>;
    /** Shown only when the host has a versions API (templates). */
    restore?: EmailBuilderRestoreOption | null;
    /**
     * Template being edited: enables the versions drawer (`GET
     * /templates/{id}/versions`, restore → editor reloaded). Without it
     * (campaigns) *Restaurer une version précédente* goes back to the last
     * design saved in this session.
     */
    templateId?: Uuid | null;
}
