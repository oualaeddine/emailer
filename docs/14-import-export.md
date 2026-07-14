# 14 — Import / Export

## 14.1 Overview

All bulk recipient ingestion (CSV, Excel, PageJaunes selection) shares one asynchronous pipeline built around `import_jobs`/`import_rows`/`import_errors` (see [04-database-design.md](04-database-design.md#46-importing-module)), so the UI, validation rules, and error reporting are consistent regardless of source.

## 14.2 Pipeline Stages

```mermaid
graph LR
    A[Upload / Select] --> B[Parse]
    B --> C[Column Mapping]
    C --> D[Validate - staged in import_rows]
    D --> E[Review Screen]
    E --> F[Commit - create Recipients]
    F --> G[Completion Summary]
```

`import_jobs.status` is governed by the shared `WorkflowEngine` ([37-workflow-engine.md](37-workflow-engine.md)) as `ImportJobWorkflow` — the stage diagram above is the pipeline flow; the underlying status transitions (`pending → validating → processing → completed/completed_with_errors/failed`) are the workflow definition.

1. **Upload/Select** — file upload (CSV/Excel) to a private disk, or a PageJaunes selection payload (list of external IDs from [06-pagejaunes-integration.md](06-pagejaunes-integration.md)). Creates `import_jobs` row, `status = pending`.
2. **Parse** — queued job (`ParseImportFileJob`) streams the file (chunked, memory-safe for large files using generator-based CSV reading / spreadsheet streaming reader for Excel) into `import_rows.raw_data`. `status = validating`.
3. **Column Mapping** (CSV/Excel only) — UI presents detected headers and lets the user map source columns to recipient fields (email [required], first_name, last_name, company_name, tags, arbitrary → `custom_fields`). Mapping stored on `import_jobs.column_mapping` and reused as a suggested default for future imports from a similarly-shaped file (matched by header signature).
4. **Validate** — per-row: syntax email check, required-field check, duplicate check against existing `recipients.email` and within the batch itself. Sets `import_rows.validation_status` and `parsed_email`. Optionally, deep verification ([22-email-verification.md](22-email-verification.md)) can be triggered per row if the user opts in (slower, rate-limited by the verification provider).
5. **Review Screen** — paginated grid of `import_rows` grouped by status (valid/invalid/duplicate) with counts, allowing the user to fix individual rows inline (edit raw_data → re-validate that row) or bulk-exclude a status group (e.g. "skip all duplicates").
6. **Commit** — queued job (`CommitImportJob`) creates/links `recipients` for all rows marked `valid` (or user-approved), attaches to the target `recipient_list_id` if specified, updates `import_jobs` counters, `status = completed` or `completed_with_errors`.
7. **Completion Summary** — shown in-app and available historically under Recipients → Import (list of past `import_jobs`).

## 14.3 CSV Import

- Delimiter auto-detection (`,`/`;`/`\t`), UTF-8 and Windows-1252 encoding detection with fallback, header row required (first row assumed headers, user can toggle "no header row").
- Max file size configurable (default 20MB) enforced both client and server side.

## 14.4 Excel Import

- Supports `.xlsx` (not legacy `.xls`) via a streaming spreadsheet reader library to avoid loading entire workbooks into memory.
- If multiple sheets exist, user selects which sheet to import.
- Cell type coercion: dates/numbers converted to string representations consistent with CSV handling before validation.

## 14.5 Company Import from PageJaunes

Reuses this same pipeline with `source_type = pagejaunes`; the "file" stage is replaced by the selection payload from the PageJaunes search UI (see [06-pagejaunes-integration.md §6.6](06-pagejaunes-integration.md#66-email-import-workflow)). Column mapping stage is skipped (fields are already structured). Rows lacking an email are marked `invalid` with error code `missing_email` and are excluded from commit by default (excludable/includable is not applicable since email is mandatory for this platform).

## 14.6 Error Handling

`import_errors` captures row-level parse failures (malformed row, encoding issue) and job-level failures (file unreadable, unsupported format). The Review Screen surfaces both `import_rows` (validation-level, recoverable) and `import_errors` (parse-level, typically not recoverable without re-uploading a fixed file). Job-level failures set `import_jobs.status = failed` and trigger an in-app Notification to the initiating user.

## 14.7 Export

- **Recipient list export**: CSV export of any list/segment's current resolved membership, generated as a queued job for large lists, delivered via signed temporary download URL (private disk, 7-day expiry per [02-system-architecture.md §2.8](02-system-architecture.md#28-storage-strategy)).
- **Report export**: CSV/PDF export of any report (see [25-reporting.md](25-reporting.md)), same signed-URL delivery mechanism, tracked in `report_snapshots`.
- **Audit log export**: CSV export restricted to Administrators, itself logged as an audit event (`audit_log.exported`).

All exports containing recipient PII are access-controlled by the same permission required to view the source data, and the export action itself is an auditable event (see [27-audit-logs.md](27-audit-logs.md)).

## 14.8 Retention & Cleanup

Uploaded import source files retained 30 days (configurable) then pruned by a scheduled job (`PruneImportFilesJob`); `import_jobs`/`import_rows` metadata rows are retained indefinitely for history unless an Administrator explicitly purges old jobs.

Continue to [15-campaign-management.md](15-campaign-management.md).
