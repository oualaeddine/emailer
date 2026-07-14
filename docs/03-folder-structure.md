# 03 — Folder Structure & Conventions

## 3.1 Backend (Laravel 12 / PHP 8.4)

```
app/
  Modules/
    Identity/
      Models/            (User, Role, Permission)
      Services/          (AuthService, UserService, RoleService)
      Policies/
      Http/
        Controllers/
        Requests/
        Resources/
    Composer/
      Models/             (Draft, DraftVersion, Signature)
      Services/           (DraftService, SignatureService, VersionHistoryService)
      Http/...
    Templates/
      Models/             (Template, TemplateVersion, TemplateBlock)
      Services/           (TemplateRenderService, TemplateVersionService)
      Http/...
    Recipients/
      Models/             (Recipient, RecipientList, Segment, Tag)
      Services/           (RecipientService, SegmentEvaluationService, DedupeService)
      Http/...
    Importing/
      Models/             (ImportJob, ImportRow, ImportError)
      Services/           (CsvImportService, ExcelImportService, ImportValidationService)
      Jobs/               (ProcessImportFileJob, ImportRowChunkJob)
      Http/...
    PageJaunesIntegration/
      Models/             (PageJaunesCompanyCache)
      Repositories/       (PageJaunesCompanyRepository — read-only external connection)
      Services/           (PageJaunesSyncService, PageJaunesSearchService)
      Http/...
    Campaigns/
      Models/             (Campaign, CampaignRecipient, CampaignSchedule)
      Services/           (CampaignLifecycleService, CampaignSchedulingService, CampaignCloneService)
      Jobs/               (DispatchCampaignJob, SendCampaignMessageJob)
      Http/...
    DeliveryEngine/
      Models/             (SmtpAccount, QuotaLedger, SendAttempt, WarmupSchedule)
      Services/           (SmtpManager, QuotaManager, RateLimiter, RotationEngine,
                           WarmupManager, RetryEngine, FailoverEngine)
      Jobs/               (SendEmailJob)
      Http/...
    Tracking/
      Models/             (Message, MessageEvent, ClickLink)
      Services/           (DeliveryTrackerService, OpenTrackingService, ClickTrackingService,
                           WebhookReceiverService, BounceProcessor, ComplaintProcessor)
      Http/
        Controllers/      (TrackingPixelController, ClickRedirectController, WebhookController)
    Verification/
      Models/             (VerificationResult)
      Services/           (EmailVerificationService — provider adapter interface)
      Http/...
    Suppression/
      Models/             (SuppressionEntry)
      Services/           (SuppressionManager)
      Http/...
    Reporting/
      Services/           (ReportBuilderService, snapshot refresh jobs)
      Http/...
    Audit/
      Models/             (AuditLog)
      Services/           (AuditLogger — invoked via listener, not called directly by most code)
      Http/...
    Settings/
      Models/             (SettingValue)
      Services/           (SettingsService)
      Http/...
  Domain/
    Enums/                (cross-module enums: MessageStatus, CampaignStatus, SmtpEncryption, etc.)
    ValueObjects/          (EmailAddress, Quota, MergeVariableSet)
    Events/                (domain events, see 31-events.md)
    Listeners/
  Support/
    Repositories/          (BaseRepository, contracts)
    Concerns/              (traits: HasAuditableChanges, HasUuid, etc.)
  Providers/
  Http/
    Middleware/
    Kernel-level concerns only (module controllers live inside modules)
config/
database/
  migrations/              (grouped by module via filename prefix, see 3.3)
  factories/
  seeders/
routes/
  web.php                  (Inertia routes)
  api.php                  (versioned JSON API, see 29-api-specification.md)
  console.php              (scheduled tasks)
resources/
  js/                      (see 3.2)
tests/
  Unit/
  Feature/
  Integration/
```

### Module Internal Convention

Every module follows: `Models/ → Services/ → Http/{Controllers,Requests,Resources}/ → Jobs/ (if any) → Policies/ (if any)`. Application Services are the only classes controllers call. Services depend on Repository interfaces bound in each module's `ServiceProvider`.

## 3.2 Frontend (React + Inertia + TypeScript + Fluent UI v9 + Tailwind)

```
resources/js/
  Pages/                   (Inertia page components, mirror route names)
    Dashboard/
    Mailbox/
      Inbox.tsx
      Sent.tsx
      Drafts.tsx
      Outbox.tsx
      Scheduled.tsx
    Composer/
    Templates/
    Recipients/
    Campaigns/
    Smtp/
    Reporting/
    Settings/
    Admin/
  Components/
    Shell/                 (AppShell, NavRail, TopBar, CommandBar)
    Mailbox/                (MessageList, ReadingPane, MessageListItem)
    Composer/               (RichTextEditor, BlockEditor, HtmlSourceView, PreviewPane)
    DataGrid/               (shared Fluent DataGrid wrappers)
    Charts/                 (reporting chart wrappers)
    Common/                 (Buttons, EmptyState, ConfirmDialog, etc.)
  Hooks/                    (useSegmentPreview, useQuotaStatus, usePolling, etc.)
  Lib/
    api/                    (typed API client functions per module)
    types/                  (TypeScript types mirroring API Resources)
    permissions/            (client-side permission-gate helpers, mirrors 26-rbac.md)
  Theme/                    (Fluent UI v9 theme tokens, light/dark)
  app.tsx                   (Inertia entry point)
vite.config.ts
tailwind.config.ts
```

**Rule:** TypeScript types under `Lib/types` are generated/maintained to mirror the Laravel API Resources 1:1 (manually kept in sync per [29-api-specification.md](29-api-specification.md); code generation from OpenAPI is a roadmap item, see [34-roadmap.md](34-roadmap.md)).

## 3.3 Migration Naming Convention

`database/migrations/YYYY_MM_DD_HHMMSS_{module_snake_case}_create_{table}_table.php`, ordered so that referenced tables (e.g. `users`, `smtp_accounts`, `recipients`) migrate before dependent tables. Full table catalog: [04-database-design.md](04-database-design.md).

## 3.4 Configuration Files

| File | Purpose |
|---|---|
| `config/mailer.php` | Custom app config: quota defaults, warm-up defaults, tracking domain, verification provider settings |
| `config/pagejaunes.php` | External DB connection name, cache TTLs |
| `config/queue.php` | Standard Laravel queue config, named connections per priority (see 24) |
| `config/horizon.php` | Horizon supervisor definitions per queue |

Continue to [04-database-design.md](04-database-design.md).
