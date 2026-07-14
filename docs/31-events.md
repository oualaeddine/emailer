# 31 — Domain Events

## 31.1 Purpose

Domain Events decouple side effects (audit logging, notifications, suppression, denormalized-field updates) from the Application Services that trigger them, per the event-driven principle in [02-system-architecture.md §2.10](02-system-architecture.md#210-event-driven-backbone). Application Services raise events; they never directly call unrelated modules' services.

## 31.2 Event Catalog & Listener Map

| Event | Raised By | Listeners |
|---|---|---|
| `CampaignCreated` | `CampaignLifecycleService` | `AuditListener` |
| `CampaignApproved` | `CampaignLifecycleService` | `AuditListener`, `NotifyCreatorListener` |
| `CampaignSent` (transition to `running`) | `CampaignLifecycleService` | `AuditListener`, `DispatchCampaignListener` (kicks off `DispatchCampaignJob`) |
| `CampaignPaused` / `CampaignResumed` / `CampaignCancelled` | `CampaignLifecycleService` | `AuditListener` |
| `CampaignCompleted` | Dispatcher (last chunk processed) | `AuditListener`, `NotifyCompletionListener` (internal notification, 2.11), `RefreshCampaignStatsListener` |
| `MessageQueued` | `SendEmailJob` (enqueue) | `DeliveryTrackerListener` |
| `MessageSent` | `SmtpManager` (successful attempt) | `DeliveryTrackerListener`, `UpdateRecipientLastContactedListener` |
| `MessageDelivered` | Webhook Receiver | `DeliveryTrackerListener` |
| `MessageOpened` | Tracking Pixel Controller | `DeliveryTrackerListener`, `ReportingCounterListener` |
| `MessageClicked` | Click Redirect Controller | `DeliveryTrackerListener`, `ReportingCounterListener` |
| `MessageSoftBounced` | Webhook Receiver / Bounce Processor | `DeliveryTrackerListener`, `RetryEngineListener` |
| `MessageHardBounced` | Bounce Processor | `DeliveryTrackerListener`, `SuppressionListener` (→ `SuppressRecipientJob`), `SmtpHealthListener` |
| `MessageFailed` | Retry Engine (retries exhausted) | `DeliveryTrackerListener`, `AuditListener` (only for transactional, not per-campaign-message, per [27-audit-logs.md §27.6](27-audit-logs.md#276-what-is-not-in-audit_logs)) |
| `MessageSpamComplaint` | Complaint Processor | `DeliveryTrackerListener`, `SuppressionListener`, `SmtpHealthListener` |
| `RecipientUnsubscribed` | Unsubscribe Controller | `SuppressionListener`, `AuditListener` |
| `SmtpAccountHealthChanged` | `ProbeSmtpHealthJob` / Failover Engine | `NotifyAdminListener` (2.11), `AuditListener` |
| `SmtpAccountCredentialsUpdated` | `SmtpManagementService` | `AuditListener` (redacted, 27.4) |
| `ImportCompleted` | `CommitImportJob` | `NotifyCreatorListener`, `AuditListener` |
| `ImportFailed` | `ParseImportFileJob`/`CommitImportJob` | `NotifyCreatorListener`, `AuditListener` |
| `RecipientVerified` | `EmailVerificationService` | `UpdateRecipientStatusListener` |
| `SettingsUpdated` | `SettingsService` | `AuditListener`, `ConfigCacheInvalidationListener` |
| `UserRoleChanged` | `UserService` | `AuditListener` |
| `SuppressionEntryAdded` / `SuppressionEntryRemoved` | `SuppressionManager` | `AuditListener` |

## 31.3 Event Payload Convention

Every event carries the affected model's `uuid` (not the full model, to keep queued listeners lightweight when a listener is itself queued) plus minimal context needed by listeners (e.g. `MessageHardBounced` carries `message_uuid`, `recipient_email`, `bounce_reason`). Listeners reload full model state as needed.

## 31.4 Sync vs. Queued Listeners

| Listener type | Examples | Rationale |
|---|---|---|
| Synchronous | `ConfigCacheInvalidationListener`, `UpdateRecipientStatusListener` | Must complete before the request/job returns to keep immediately-following reads consistent |
| Queued (`ShouldQueue`) | `AuditListener`, `NotifyAdminListener`, `NotifyCompletionListener`, `ReportingCounterListener` | Non-blocking side effects; failure here must never roll back the primary action (e.g. a failed audit write should not prevent a campaign from sending) |

## 31.5 Relationship to Notifications

Several listeners (`NotifyAdminListener`, `NotifyCreatorListener`, `NotifyCompletionListener`) dispatch Laravel Notifications (database/mail/Slack channels per [02-system-architecture.md §2.11](02-system-architecture.md#211-notifications)) rather than performing the notification logic inline — Events → Listeners → Notifications is the full chain, keeping each layer single-responsibility. As of [41-notification-center.md](41-notification-center.md), these listeners call `NotificationCenterService::notify()` rather than constructing Notifications directly, so per-user channel preferences are respected without each listener re-implementing routing logic.

Continue to [32-testing.md](32-testing.md).
