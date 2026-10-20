/**
 * WP-60 — public props of the EmailBuilder (docs/43 §43.4.4, docs/45 §45.6.1).
 * Type-only: safe to import from pages without pulling the editor chunk.
 */
import type { EmailDesign } from '@/Lib/types/editor';

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
}
