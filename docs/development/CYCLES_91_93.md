# Cycles 91–93 — Installation and registration

This branch is isolated from main until local review. No migration is run by GitHub commits.

## Cycle 91 — Native PHP installation
- 91A: read-only environment diagnostics and ordered SQL migration inventory (implemented)
- 91B: read-only browser requirements page implemented; protected write-capable installation wizard not implemented
- 91C: transactional/resumable schema migration execution and first-owner bootstrap (not implemented)
- 91D: installer parity audit, tests, then retire the legacy Python installer (not implemented)

### CLI foundation
```sh
/opt/lampp/bin/php bin/brite doctor
/opt/lampp/bin/php bin/brite migrations
```
Neither command modifies configuration, database, or accounts. The doctor reports checks only, never secret values. An Apache .htaccess check is not proof of web-server protection; production installation must verify web server configuration independently.

## Cycle 93 foundation delivered\n- Migration 038 adds application, verification, review, token, requirements, and private document metadata tables. Not applied to a database.\n- Registration Policy is a pure eligibility helper; no signup, approval, or identity upload endpoint is exposed.\n- Run `/opt/lampp/bin/php tests/registration_policy_test.php` after pulling for policy smoke tests.\n\n## Cycle 92 — Contractor onboarding and identity
- Private ID document ingestion with encryption, retention/deletion and restricted access
- Identity verification and moderation before contractor activation
- Business profile, licenses, insurance, qualifications and expiration reminders
- No public access to raw identity documents

## Cycle 93 — Registration and moderation
- Client, contractor, and business signup paths
- Single-use expiring email verification links, tenant-aware application queue
- Client low-friction verified-email access, optional ID and payment at transaction
- Platform owner vs tenant owner approval boundaries
- Audit trail, rate limits, anti-enumeration and permission enforcement

## Safety gates
- Never store secrets, raw government IDs or payment credentials in Git.
- Do not merge until browser, migration, security and cross-tenant regression tests pass.
- Preserve existing tenants and schema migration history; do not re-run previously applied migrations.

## Enrollment navigation (Cycle 93C partial)
- Owner/admin main navigation now uses **Enrollment** under Administration.
- `enrollment.php` is an authenticated, tenant-scoped, read-only application queue with status filters and counts.
- It handles missing migration 038 without exposing database errors.
- Do not enable review mutations until reviewer authorization, audit transactions, email verification, and provisioning are implemented and tested.
- Browser, PHP lint, and MariaDB integration checks remain pending.

## Cycle 93C moderation actions (partial)
- Enrollment now supports request-info and rejection for `pending_review` applications only.
- `api/enrollment_decision.php` enforces POST, CSRF, authenticated owner/admin, active tenant membership, and server-side tenant isolation.
- `ReviewService` locks the application row and commits its status change and reviewer audit record atomically.
- Request-info does **not** send email. Manual follow-up is required until notification delivery is implemented.
- Approval and account provisioning remain disabled. Migration 038 must be applied before the page or endpoint can function.
- No PHP runtime, database, browser, or cross-tenant integration test has been run in this environment.
