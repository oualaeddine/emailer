# 05 — Entity-Relationship Diagrams

Full column-level detail is in [04-database-design.md](04-database-design.md). These diagrams show cardinality and key relationships grouped by domain area for readability.

## 5.1 Identity & Access

```mermaid
erDiagram
    ROLES ||--o{ USERS : "has many"
    ROLES ||--o{ PERMISSION_ROLE : "has many"
    PERMISSIONS ||--o{ PERMISSION_ROLE : "has many"
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : "has many"

    ROLES {
        bigint id PK
        varchar name
    }
    USERS {
        bigint id PK
        varchar email
        bigint role_id FK
    }
    PERMISSIONS {
        bigint id PK
        varchar name
    }
```

## 5.2 Composer, Templates & Attachments

```mermaid
erDiagram
    USERS ||--o{ DRAFTS : owns
    USERS ||--o{ SIGNATURES : owns
    TEMPLATES ||--o{ DRAFTS : "used by"
    DRAFTS ||--o{ DRAFT_VERSIONS : "has history"
    SIGNATURES ||--o{ DRAFTS : "attached to"
    TEMPLATES ||--o{ TEMPLATE_VERSIONS : "has history"
    DRAFTS ||--o{ ATTACHMENTS : "polymorphic has"
    TEMPLATES ||--o{ ATTACHMENTS : "polymorphic has"
    MESSAGES ||--o{ ATTACHMENTS : "polymorphic has"

    DRAFTS {
        bigint id PK
        bigint user_id FK
        bigint template_id FK
        bigint signature_id FK
        varchar status
    }
    TEMPLATES {
        bigint id PK
        varchar name
        text html_content
    }
```

## 5.3 Recipients & Segmentation

```mermaid
erDiagram
    RECIPIENTS ||--o{ RECIPIENT_TAG : has
    TAGS ||--o{ RECIPIENT_TAG : has
    RECIPIENTS ||--o{ RECIPIENT_LIST_RECIPIENT : "member of"
    RECIPIENT_LISTS ||--o{ RECIPIENT_LIST_RECIPIENT : contains
    RECIPIENT_LISTS ||--o| SEGMENTS : "defines rules for (dynamic)"
    PAGEJAUNES_COMPANY_CACHE ||--o{ RECIPIENTS : "sourced from"
    PAGEJAUNES_COMPANY_CACHE ||--o{ PAGEJAUNES_COMPANY_EMAILS_CACHE : "has many emails"

    RECIPIENTS {
        bigint id PK
        citext email
        varchar source
        varchar status
        bigint pagejaunes_company_cache_id FK
    }
    RECIPIENT_LISTS {
        bigint id PK
        varchar type
    }
    SEGMENTS {
        bigint id PK
        bigint recipient_list_id FK
        jsonb rules
    }
    PAGEJAUNES_COMPANY_CACHE {
        bigint id PK
        bigint pagejaunes_company_id
        varchar pagejaunes_code
        varchar company_name
    }
    PAGEJAUNES_COMPANY_EMAILS_CACHE {
        bigint id PK
        bigint pagejaunes_company_cache_id FK
        citext email
    }
```

> Note: the external PageJaunes source (`pjnewdb`) is a MariaDB/MySQL database, not PostgreSQL — `PAGEJAUNES_COMPANY_CACHE`/`PAGEJAUNES_COMPANY_EMAILS_CACHE` above are our own Postgres cache tables mirroring it, per [06-pagejaunes-integration.md](06-pagejaunes-integration.md). A company can have zero or many emails, which is why the cache mirrors that as a child table rather than a single `email` column.

## 5.4 Import Pipeline

```mermaid
erDiagram
    USERS ||--o{ IMPORT_JOBS : initiates
    IMPORT_JOBS ||--o{ IMPORT_ROWS : contains
    IMPORT_JOBS ||--o{ IMPORT_ERRORS : logs
    IMPORT_JOBS }o--|| RECIPIENT_LISTS : "targets"
    IMPORT_ROWS }o--o| RECIPIENTS : "creates"
```

## 5.5 Campaigns & Delivery

```mermaid
erDiagram
    CAMPAIGNS }o--|| RECIPIENT_LISTS : targets
    CAMPAIGNS }o--o| TEMPLATES : "based on"
    CAMPAIGNS ||--o{ CAMPAIGN_SCHEDULES : "recurs as"
    CAMPAIGNS ||--o{ CAMPAIGN_RECIPIENTS : resolves
    CAMPAIGN_RECIPIENTS }o--|| RECIPIENTS : references
    CAMPAIGN_RECIPIENTS |o--o| MESSAGES : produces
    CAMPAIGNS ||--o{ MESSAGES : generates
    SMTP_ACCOUNTS ||--o{ MESSAGES : "sends via"
    SMTP_ACCOUNTS ||--o{ SEND_ATTEMPTS : "attempted via"
    MESSAGES ||--o{ SEND_ATTEMPTS : "has attempts"
    SMTP_ACCOUNTS ||--o{ QUOTA_LEDGER : tracks
    SMTP_ACCOUNTS }o--o| WARMUP_SCHEDULES : follows

    CAMPAIGNS {
        bigint id PK
        varchar status
        varchar send_mode
        bigint recipient_list_id FK
    }
    SMTP_ACCOUNTS {
        bigint id PK
        varchar health_status
        int priority
    }
    MESSAGES {
        bigint id PK
        bigint campaign_id FK
        bigint recipient_id FK
        bigint smtp_account_id FK
        varchar status
    }
```

## 5.6 Tracking, Verification & Suppression

```mermaid
erDiagram
    MESSAGES ||--o{ MESSAGE_EVENTS : logs
    MESSAGES ||--o{ CLICK_LINKS : contains
    RECIPIENTS ||--o{ VERIFICATION_RESULTS : "verified via email"
    MESSAGES |o--o| SUPPRESSION_ENTRIES : "may trigger"

    MESSAGE_EVENTS {
        bigint id PK
        bigint message_id FK
        varchar event_type
        jsonb metadata
    }
    SUPPRESSION_ENTRIES {
        bigint id PK
        citext email
        varchar reason
        bigint source_message_id FK
    }
```

## 5.7 Governance (Audit, Settings)

```mermaid
erDiagram
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ SETTINGS : updates

    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar auditable_type
        bigint auditable_id
    }
```

## 5.8 Full Cross-Domain Overview (Simplified)

```mermaid
erDiagram
    USERS ||--o{ CAMPAIGNS : creates
    USERS ||--o{ DRAFTS : owns
    RECIPIENT_LISTS ||--o{ CAMPAIGNS : "targeted by"
    RECIPIENTS ||--o{ MESSAGES : receives
    CAMPAIGNS ||--o{ MESSAGES : generates
    SMTP_ACCOUNTS ||--o{ MESSAGES : delivers
    MESSAGES ||--o{ MESSAGE_EVENTS : logs
    RECIPIENTS ||--o{ SUPPRESSION_ENTRIES : "may be listed"
    PAGEJAUNES_COMPANY_CACHE ||--o{ RECIPIENTS : sources
    TEMPLATES ||--o{ CAMPAIGNS : "based on"
    IMPORT_JOBS ||--o{ RECIPIENTS : creates
```

Continue to [06-pagejaunes-integration.md](06-pagejaunes-integration.md).
