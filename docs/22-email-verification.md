# 22 — Email Verification

## 22.1 Purpose

Reduce bounce/complaint rates (protecting SMTP account reputation, see [18-smtp-management.md §18.5](18-smtp-management.md#185-health-monitoring)) by validating recipient addresses before they enter active sending, either individually (Recipient profile action), in bulk (import-time opt-in, [14-import-export.md §14.2](14-import-export.md#142-pipeline-stages)), or scheduled (periodic re-verification of an existing list).

## 22.2 Verification Checks

| Check | Description |
|---|---|
| Syntax validation | RFC 5322-conformant structural check — always performed synchronously, free, no external call |
| MX validation | DNS MX record lookup confirms the domain can receive mail |
| Disposable detection | Match domain against a maintained disposable-domain list (provider-supplied or a bundled/updatable list in Settings) |
| Role-based detection | Pattern match on local-part (`info@`, `admin@`, `support@`, `noreply@`) — flagged, not necessarily blocked (role addresses can be legitimate B2B contacts) |
| Catch-all detection | Provider-side SMTP probe (or heuristic) determining whether the domain accepts mail for any address — affects confidence of a "deliverable" verdict |

## 22.3 Provider Abstraction

`EmailVerificationService` defines a provider-agnostic interface (`verify(email): VerificationResultDto`) with a swappable adapter (configurable in Settings → Verification Provider: provider name, API key, endpoint). This allows the platform to integrate any third-party verification API (e.g. ZeroBounce, NeverBounce, Kickbox-style services) without coupling core logic to one vendor — mirrors the SMTP provider-agnostic pattern in [18-smtp-management.md §18.3](18-smtp-management.md#183-provider-defaults-configurable-not-hardcoded). Syntax/MX checks can run locally even without a configured third-party provider (baseline verification always available).

## 22.4 Verdict Computation

`verdict` (`deliverable`/`risky`/`undeliverable`/`unknown`) is computed from the combination of checks:
- `undeliverable`: syntax invalid, or MX invalid, or provider explicitly returns undeliverable.
- `risky`: disposable, or catch-all with no further signal, or role-based (configurable whether role-based counts as risky).
- `deliverable`: all checks pass with high confidence.
- `unknown`: provider unavailable/timeout and local checks pass — treated as "not blocked" by default policy (configurable to be more conservative).

## 22.5 Verification Caching

`verification_results` rows are keyed by email with `checked_at`/`expires_at` (default 90-day validity, configurable). Any verification request first checks for a non-expired cached result before calling the provider — this both saves cost and avoids re-querying providers excessively for the same address across multiple imports/recipients. `recipients.status` is updated to `invalid` when a fresh verification returns `undeliverable`, feeding directly into segment exclusion logic ([13-recipient-management.md §13.7](13-recipient-management.md#137-lists-vs-segments)).

## 22.6 Verification History

Every check (not just the latest) is retained in `verification_results` (append rather than update-in-place) so a Recipient's verification history is auditable — "this address was deliverable in January, became undeliverable in June" is answerable, useful for diagnosing reputation issues over time. The Recipient profile shows only the latest result prominently, with a "View history" expansion.

## 22.7 Workflows

- **Ad hoc**: "Verify Email" button on Recipient profile → synchronous call (with loading state) → immediate result.
- **Bulk at import**: opt-in checkbox during CSV/Excel/PageJaunes import review ([14-import-export.md](14-import-export.md)) → queued job verifies each row's email before commit, rows failing hard verification are flagged (excludable, not auto-excluded, so the user retains judgment — e.g. a "risky" role-based address might still be wanted).
- **Scheduled re-verification**: optional recurring job (Settings-configurable, e.g. monthly) re-verifies recipients whose cached result has expired or is approaching expiry, prioritizing recipients targeted by upcoming scheduled campaigns.
- **Pre-send gate** (optional policy, Settings): campaigns can require all targeted recipients have a non-expired `deliverable` or `unknown` verdict before sending is allowed, surfaced as a blocking check in the Campaign Wizard's Review step ([15-campaign-management.md §15.2](15-campaign-management.md#152-campaign-wizard)).

## 22.8 Permissions

Verification actions require `recipients.verify` permission (Marketing Operator and above); provider configuration (API keys) restricted to Administrator ([26-rbac.md](26-rbac.md)).

Continue to [23-suppression-list.md](23-suppression-list.md).
