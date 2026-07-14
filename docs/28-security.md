# 28 — Security

## 28.1 Authentication

- **Sanctum SPA session authentication**: cookie-based, first-party session (Inertia app served from the same domain as the API), CSRF-protected via Sanctum's stateful-domain mechanism — appropriate for a single-organization internal app with no need for third-party token-bearing clients in v1.
- Password policy: minimum length/complexity enforced server-side (configurable in Settings, sane defaults: min 12 chars), hashed with `bcrypt`/`argon2id` (Laravel default hasher).
- Account lockout after N consecutive failed attempts (configurable, default 5) with exponential lockout duration, to mitigate credential stuffing — logged as `auth.login_failed`/lockout events ([27-audit-logs.md](27-audit-logs.md)).
- Session timeout: configurable idle timeout (default 2 hours) and absolute session lifetime.

## 28.2 Authorization

Layered enforcement per [26-rbac.md §26.5](26-rbac.md#265-enforcement-layers): route middleware → Policies → repository query scoping → frontend gating (UX-only). No endpoint relies solely on frontend hiding; every API/controller action independently re-checks permissions server-side.

## 28.3 CSRF Protection

Standard Laravel/Sanctum CSRF token (`XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header) on all state-changing requests from the Inertia SPA. Webhook endpoints ([17-delivery-engine.md §17.9](17-delivery-engine.md#179-webhook-receiver)) are explicitly exempted from CSRF (they're server-to-server, unauthenticated by session) but instead require provider-specific signature/HMAC verification — never both unauthenticated AND unverified.

## 28.4 Input Validation

All write endpoints validated via Laravel Form Requests with explicit rules per field (never relying on client-side validation as the boundary). HTML content fields (Composer/Template body) are **not** arbitrarily trusted: stored as authored (since the user is an authenticated internal staff member composing their own content, not rendering untrusted third-party HTML to other users of the *admin* app), but when **rendering recipient-facing email**, output is sent via email transport (not reflected into the app's own HTML pages for other users), which limits XSS blast radius to the recipient's mail client rather than the admin application itself. Any place where message/template HTML is rendered *within the app UI* (previews, message detail view) uses a sandboxed `iframe` with a restrictive `sandbox` attribute (no `allow-scripts`, no `allow-same-origin`) — this is the primary XSS mitigation for the admin UI itself, per [11-email-composer.md §11.7](11-email-composer.md#117-preview-modes).

## 28.5 SQL Injection

Exclusively parameterized queries via Eloquent/Query Builder; the one deliberately dynamic query surface — Segment rule evaluation ([13-recipient-management.md §13.7](13-recipient-management.md#137-lists-vs-segments)) — builds queries through the query builder's parameter binding for every rule value (never raw string concatenation of user-supplied rule values into SQL), with `field` names validated against an explicit allow-list of real column/relation names rather than accepted as free-text.

## 28.6 Encryption & Secret Management

- SMTP account passwords (`smtp_accounts.password_encrypted`) and verification-provider API keys stored using Laravel's encrypted casts (`APP_KEY`-derived, AES-256-CBC).
- Secret `settings` rows (`is_secret = true`) use the same encrypted-cast mechanism, decrypted only server-side when actually needed (e.g. constructing an SMTP transport), never serialized to the frontend.
- `APP_KEY` and external DB credentials (`PAGEJAUNES_DB_*`) live in environment variables / a secrets manager in production (not committed, not stored in the database) — see [33-deployment.md](33-deployment.md).
- Tracking tokens (`click_links.tracking_token`) and unsubscribe tokens are cryptographically random (not sequential/guessable) to prevent enumeration of other recipients' tracking data.

## 28.7 Rate Limiting

- Login endpoint: per-IP and per-account rate limiting (Laravel's built-in throttle middleware) in addition to the lockout policy (28.1).
- Public unauthenticated endpoints (tracking pixel, click redirect, unsubscribe, webhook receiver) are rate-limited per-IP to prevent abuse/DoS of these necessarily-public surfaces.
- API endpoints generally rate-limited per authenticated user (sane default, e.g. 120 req/min) to prevent runaway client bugs from overwhelming the app.

## 28.8 Webhook Security

Every provider webhook endpoint verifies the provider's signature scheme (HMAC-SHA256 of payload with a shared secret, or provider-specific equivalent) before processing; unverified/invalid-signature requests are rejected with 401 and logged (not silently dropped, so misconfiguration is visible) but never processed as trusted data — see [17-delivery-engine.md §17.9](17-delivery-engine.md#179-webhook-receiver) and [29-api-specification.md](29-api-specification.md).

## 28.9 Audit Trail as a Security Control

[27-audit-logs.md](27-audit-logs.md) is itself a security control: append-only at the DB-role level, covering all permission/credential/settings changes, ensuring tampering or misuse is detectable after the fact.

## 28.10 OWASP Top 10 Mapping

| OWASP Category | Mitigation |
|---|---|
| Broken Access Control | Layered RBAC enforcement (28.2), object-level policies |
| Cryptographic Failures | Encrypted secrets at rest (28.6), TLS enforced in transit (HSTS, see 33) |
| Injection | Parameterized queries everywhere, allow-listed segment fields (28.5) |
| Insecure Design | Suppression pre-send gate, quota reservation atomicity, sandboxed HTML rendering designed in from the architecture, not bolted on |
| Security Misconfiguration | Infra-as-config via `.env`/deployment docs ([33-deployment.md](33-deployment.md)), no debug mode in production |
| Vulnerable Components | Dependency scanning in CI ([32-testing.md](32-testing.md)) |
| Identification & Auth Failures | Lockout policy, session timeout, Sanctum (28.1) |
| Software/Data Integrity Failures | Webhook signature verification (28.8), audit trail (28.9) |
| Logging & Monitoring Failures | Audit logs + queue/health monitoring dashboards ([24-queue-management.md](24-queue-management.md)) ensure observability |
| Server-Side Request Forgery | PageJaunes DB connection is a fixed, admin-configured host (not user-suppliable); no user-controlled outbound URL fetching exists in the app surface (attachments are uploaded, not fetched by URL) |

## 28.11 Data Privacy

Recipient PII (email, name, custom fields) access is permission-gated and audited on export ([27-audit-logs.md §27.2](27-audit-logs.md#272-audit-event-catalog)). IP addresses captured for tracking/audit are subject to configurable anonymization ([21-open-click-tracking.md §21.6](21-open-click-tracking.md#216-privacy-considerations)).

Continue to [29-api-specification.md](29-api-specification.md).
