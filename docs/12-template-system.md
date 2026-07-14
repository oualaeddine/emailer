# 12 — Template System

## 12.1 Purpose

Templates are reusable, named HTML email designs used as the starting point for Drafts and Campaigns. The Template system shares its editor surface with the Composer (rich text / blocks / HTML source, see [11-email-composer.md](11-email-composer.md)) but is managed as an independent library with categorization, versioning, and a thumbnail gallery.

## 12.2 Template Library

- Grid gallery view (Fluent `Card` grid) grouped/filterable by `category` (e.g. Newsletter, Announcement, Transactional, Promotional — categories are free-text/configurable, not hardcoded).
- Each card shows a thumbnail (`thumbnail_path`, generated server-side via a headless-render-to-image step on save, or a placeholder if generation is pending/unsupported), name, last modified date, usage count (derived from `campaigns.template_id`/`drafts.template_id` counts).
- Actions per template: Edit, Duplicate, Archive (soft state via `is_archived`, not deleted — see [04-database-design.md](04-database-design.md#44-templates-module)), Delete (only if unused by any non-archived campaign/draft; otherwise forced to Archive).

## 12.3 Block Library

Independent from any single template — `template_blocks` are reusable snippets (header, footer, hero, CTA button, image, social bar, divider, custom HTML) insertable into any template or draft's block editor. Organization-standard header/footer blocks (with logo, address, unsubscribe link) can be marked as defaults suggested first in the block picker.

## 12.4 Versioning

Every save to a template's `html_content` creates a `template_versions` row (see [04-database-design.md](04-database-design.md#44-templates-module)) with an optional `change_note`. Version history UI mirrors the Composer's (12.4 reuses the same `VersionHistoryPanel` component as Drafts) — diff view, restore-as-new-version.

**Important architectural rule**: Campaigns snapshot `html_body` at creation/send time (`campaigns.html_body`, see [04-database-design.md](04-database-design.md#48-campaigns-module)) rather than referencing the template live. Editing a template after a campaign was created **never** retroactively changes that campaign's content. This guarantees send-time content integrity and auditability.

## 12.5 Editing Workflow

1. Create new (blank, from block library starter, or duplicate existing) or open existing template.
2. Edit via the shared editor (rich text/blocks/HTML source, same component as Composer).
3. Merge variables supported identically to the Composer (12.5 reuses [11-email-composer.md §11.4](11-email-composer.md#114-merge-variables)).
4. Preview modes identical to Composer (desktop/tablet/mobile/dark/print).
5. Save → creates version → regenerates thumbnail asynchronously (queued job).

## 12.6 Usage in Campaigns/Drafts

Selecting a template in the Campaign Wizard ([15-campaign-management.md](15-campaign-management.md)) or Composer copies its current `html_content`/`subject` into the target `draft`/`campaign` row as an editable starting point — it is a **copy**, not a live reference, consistent with 12.4.

## 12.7 Permissions

Template creation/edit/archive restricted to Marketing Manager and Administrator by default; Marketing Operators have read/use-only access (can select a template in Composer but not edit the library). Full matrix in [26-rbac.md](26-rbac.md).

Continue to [13-recipient-management.md](13-recipient-management.md).
