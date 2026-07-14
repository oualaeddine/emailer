# 11 — Email Composer

## 11.1 Overview

The Composer is the core authoring surface, styled after Outlook's compose window, supporting both quick transactional emails and content authored for campaigns (a Draft can later be promoted into a Campaign's content — see [15-campaign-management.md](15-campaign-management.md)). It opens as an overlay (desktop) or full-screen (mobile) and auto-saves.

## 11.2 Composer Modes

| Mode | Editor Surface |
|---|---|
| **Rich text** | WYSIWYG rich text editor (block-level formatting: bold/italic/lists/links/images) |
| **Drag-and-drop blocks** | Structured block canvas built from `template_blocks` (header/hero/text/button/image/social/footer/divider/custom) |
| **HTML source** | Raw HTML editor (syntax-highlighted) with a "sync" action that re-parses HTML back into blocks where possible, and a warning that manual HTML edits may not round-trip perfectly into the block view |

The three modes operate on the same underlying `html_content` string; switching modes is explicit (tab control) and destructive-edit warnings are shown when moving from HTML source back to block mode if the HTML doesn't map cleanly to existing block structure.

## 11.3 Core Fields

- To (Recipient picker: internal contacts / manual entry / existing recipients / PageJaunes search — see [13-recipient-management.md](13-recipient-management.md))
- Subject (supports merge variables)
- Body (rich text / blocks / HTML)
- Signature (dropdown of the user's `signatures`, default pre-selected)
- Attachments (drag-drop or file picker, stored via polymorphic `attachments` table against the `draft`)

## 11.4 Merge Variables

Syntax: `{{recipient.first_name}}`, `{{recipient.company_name}}`, `{{recipient.custom_fields.xxx}}`, plus system variables `{{unsubscribe_url}}`, `{{current_date}}`. A variable picker inserts tokens at cursor position. Server-side rendering resolves variables per-recipient at send time (see [17-delivery-engine.md](17-delivery-engine.md)); a **Preview** mode renders sample-resolved output using either a real selected recipient or a synthetic sample record so unresolved/missing fields are visible before send (fallback default text configurable per variable, e.g. `{{recipient.first_name|there}}`).

## 11.5 Attachments

- Drag-drop zone plus explicit file picker; client-side validation for max size (configurable, default 10MB/file, 25MB total) and blocked extensions (executables) before upload.
- Uploaded via chunked/async upload to avoid blocking the composer; progress shown per file.
- Stored against `attachable_type = App\Modules\Composer\Models\Draft`.

## 11.6 Draft Autosave & Version History

- Autosave triggers on a debounce (e.g. 3s after last keystroke) and on explicit blur/navigation-away, writing to `drafts.html_body`/`subject`.
- Each explicit "Save" (manual or significant autosave checkpoint, e.g. every N autosaves or every 5 minutes) creates a `draft_versions` row (see [04-database-design.md](04-database-design.md#43-composer-module)).
- Version History panel (side drawer) lists versions with timestamp + author (relevant when Administrators can edit others' drafts), diff-style preview, and a **Restore** action that copies a prior version's content back into the working draft (creating a new version rather than mutating history).

## 11.7 Preview Modes

A preview toolbar toggles rendering context without leaving the composer:

| Preview | Behavior |
|---|---|
| Desktop | Full-width rendering, ~640px email-safe container |
| Tablet | ~480px container |
| Mobile | ~375px container, single-column forced |
| Dark mode | Applies `prefers-color-scheme: dark` simulation using a dark-mode CSS injection matching common email client dark-mode behavior (color inversion heuristics), so authors can catch unreadable dark-mode combinations |
| Print | Applies a print stylesheet (removes interactive chrome, ensures link URLs are visible as text where appropriate) and opens the browser print dialog |

All previews render inside a sandboxed `iframe` using the exact HTML that would be sent (after inline CSS processing, see 11.8) to avoid surprises between editor and inbox rendering.

## 11.8 HTML Processing Pipeline (pre-send)

> This section is superseded in detail by the full, authoritative 8-stage pipeline in [38-rendering-pipeline.md](38-rendering-pipeline.md) (merge variables → conditional blocks → signature → tracking → CSS inlining → plain-text generation → final MIME assembly), which also defines the exact ordering rules and why preview mode skips certain stages. The summary below remains accurate but 38 is the canonical reference.

1. Merge block structure (if using block editor) into final HTML.
2. Inline CSS (email clients largely ignore `<style>` blocks) via a CSS-inliner step run server-side on send/preview.
3. Inject tracking pixel and rewrite links for click tracking (see [21-open-click-tracking.md](21-open-click-tracking.md)) — **only at actual send time**, not in the editor preview, so previews reflect authored content without tracking noise.
4. Inject unsubscribe footer if not already present and the send is a campaign (policy-enforced, see [23-suppression-list.md](23-suppression-list.md)).

## 11.9 Save / Send Actions

- **Save as draft**: persists to `drafts`, no queueing.
- **Send** (transactional): validates recipients + content, creates a `messages` row per recipient, dispatches to the Delivery Engine queue (see [17-delivery-engine.md](17-delivery-engine.md)), then removes/archives the source draft.
- **Schedule send** (transactional single/small sends): sets a future dispatch time; appears in the Scheduled folder.
- **Promote to Campaign**: converts draft content + chosen recipient list into a new `Campaign` in `draft` status, handing off to [15-campaign-management.md](15-campaign-management.md).

## 11.10 Validation

- Subject required, non-empty body required.
- At least one recipient required to send (not required to save draft).
- Attachment size/type validated both client- and server-side.
- Merge variables referencing unknown fields flagged as warnings (not blocking) in the preview panel.

## 11.11 Accessibility

Rich text editor toolbar fully keyboard operable; block editor supports keyboard-based reordering (move block up/down via button, not only drag) per [07-ui-design.md](07-ui-design.md#78-accessibility).

Continue to [12-template-system.md](12-template-system.md).
