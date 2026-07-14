# 30 — Background Jobs

## 30.1 Job Catalog

| Job | Queue | Trigger | Schedule/Frequency |
|---|---|---|---|
| `SendEmailJob` | `smtp-send-high`/`smtp-send-campaign` | Dispatcher (17.4) | On demand |
| `DispatchCampaignJob` | `smtp-send-campaign` | Campaign send/scheduler tick | Self-re-enqueuing per chunk (17.4) |
| `CampaignDueCheckJob` | `default` | Scheduler | Every 1 minute |
| `CampaignRecurrenceExpansionJob` | `maintenance` | Scheduler | Daily — materializes next 90 days of `campaign_schedules` for recurring campaigns (15.3) |
| `ParseImportFileJob` | `imports` | Import upload | On demand |
| `CommitImportJob` | `imports` | Import review commit | On demand |
| `PruneImportFilesJob` | `maintenance` | Scheduler | Daily |
| `SyncPageJaunesCompaniesJob` | `maintenance` | Scheduler | Daily (configurable, 6.4) |
| `ProbeSmtpHealthJob` | `maintenance` | Scheduler | Every 5 minutes (18.5) |
| `ReconcileQuotaLedgerJob` | `maintenance` | Scheduler | Every 10 minutes (19.2) |
| `RefreshReportSnapshotsJob` | `reporting` | Scheduler | Every 15 minutes (materialized view refresh, 25.1) |
| `VerifyRecipientEmailJob` | `default` | Ad hoc / import opt-in / scheduled sweep | On demand + monthly sweep (22.7) |
| `SuppressRecipientJob` | `default` | Event listener (bounce/complaint/unsubscribe) | On demand |
| `ExportGenerationJob` | `default` | Export request | On demand |
| `PruneExportFilesJob` | `maintenance` | Scheduler | Daily |
| `GenerateTemplateThumbnailJob` | `default` | Template save | On demand |

## 30.2 Job Design Principles

- Jobs carry IDs, not full payloads (e.g. `SendEmailJob(string $messageUuid)`), reloading fresh state from the DB on execution to avoid stale-payload bugs if a job sits in the queue for a while.
- All jobs implement `ShouldBeUnique` where duplicate execution would be harmful (e.g. `CommitImportJob` keyed by `import_job_id`) to guard against accidental double-dispatch.
- Idempotency: every job checks current entity state before acting (see [17-delivery-engine.md §17.10](17-delivery-engine.md#1710-idempotency-safety)) so at-least-once queue delivery semantics never cause duplicate side effects (e.g. double-sending an email).
- Failure handling: `failed()` method on each job records context to `failed_jobs`/triggers the Dead Letter handling described in [24-queue-management.md §24.4](24-queue-management.md#244-dead-letter-queue-dlq).

## 30.3 Scheduler Configuration (`routes/console.php`)

Illustrative mapping (no code, just cadence/ownership documentation):

```
schedule->job(CampaignDueCheckJob::class)->everyMinute();
schedule->job(ProbeSmtpHealthJob::class)->everyFiveMinutes();
schedule->job(ReconcileQuotaLedgerJob::class)->everyTenMinutes();
schedule->job(RefreshReportSnapshotsJob::class)->everyFifteenMinutes();
schedule->job(CampaignRecurrenceExpansionJob::class)->daily();
schedule->job(SyncPageJaunesCompaniesJob::class)->daily();
schedule->job(PruneImportFilesJob::class)->daily();
schedule->job(PruneExportFilesJob::class)->daily();
schedule->job(VerifyRecipientEmailJob::class)->monthly(); // sweep mode, batched
```

Scheduler runs via a single cron entry (`* * * * * php artisan schedule:run`) per standard Laravel convention, itself supervised in the deployment environment ([33-deployment.md](33-deployment.md)).

## 30.4 Long-Running Job Considerations

`DispatchCampaignJob`'s self-re-enqueue pattern (rather than one giant job looping over all recipients) ensures Horizon timeouts/memory limits are never at risk regardless of campaign size, and allows pause/cancel to take effect within one chunk's processing time rather than requiring a full job to complete first.

Continue to [31-events.md](31-events.md).
