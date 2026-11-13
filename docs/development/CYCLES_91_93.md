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

## Cycle 93 email verification foundation
- `EmailVerificationService::consume` locks and consumes a hashed email token atomically, advances pending-email to pending-review, and records verification and audit entries.
- `verify-enrollment.php` provides a CSRF-protected token submission page; this is not a signup or email-delivery workflow. It does not activate an account.
- Token generation and outbound email delivery are **not connected**. Do not publish verification links until issuance and delivery are integrated.
- `.github/workflows/enrollment-checks.yml` adds PHP lint and pure unit tests on pull requests. CI success does not replace database, browser, and tenant-isolation testing.

## Cycle 93 registration integration (partial)
- Existing `api/auth/register.php` no longer creates active users or memberships. It creates a tenant-specific `pending_email` application, a hashed one-time token, and an audit record.
- `sign-up.v2.php` now requires explicit business selection, a 12-character minimum password, and a consent checkbox; successful submission displays pending-verification instructions rather than signing the applicant in.
- Configure `ENROLLMENT_MAIL_FROM` and a working PHP `mail()` transport before testing. Transport acceptance is **not** proof of delivery.
- **Deployment blocker:** Real published Terms/Privacy links and versioned consent evidence, abuse/rate limiting, email queue/retry, duplicate-race protection, provider verification, and approval/provisioning are not yet complete. Do not deploy public signup from this branch.
- No migration or end-to-end test was executed against the local environment.

## Cycle 93D — Approval, throttling, and consent (code committed; integration pending)
- `ApprovalService::approve` provisions a verified eligible client or contractor in a single database transaction, creates the active tenant membership and role profile, clears the pending password hash, and writes an approval audit entry. Business applications are not supported by this approval endpoint.
- Contractor approval is blocked unless email, phone, and government ID verification records are explicitly marked verified and unexpired. No actual phone or ID verification provider is integrated, so contractors cannot complete this path yet.
- Enrollment owner/admin UI now exposes Approve; request-info/reject remain available.
- Migration 039 adds database-backed fixed-window request throttling. Set `ENROLLMENT_RATE_SECRET` to a stable high-entropy secret (32+ characters). Limits: 10 submissions per source IP and 5 per email per hour. Throttle storage holds HMAC digests, not raw identifiers.
- Migration 040 adds versioned consent evidence. Configure `ENROLLMENT_TERMS_URL`, `ENROLLMENT_PRIVACY_URL` (HTTPS), `ENROLLMENT_TERMS_VERSION`, `ENROLLMENT_PRIVACY_VERSION`; signup is blocked when legal configuration is missing.
- Configure `ENROLLMENT_MAIL_FROM` and a working PHP mail transport. No real delivery, mail retries, provider verification, browser checks, or database integration tests have been completed.
- Apply migrations 038, 039 and 040 in order only after reviewing backup and schema compatibility. No local changes or migrations have been performed by this development work.
- Remaining release blockers: real identity/phone verification, business enrollment and platform review, legal document publication, mail queue and delivery assurance, cross-tenant/integration tests, concurrency/duplicate race hardening, installation wizard completion.

## Cycle 91 migration status and Cycle 93 approval hardening
- Added `app/Installation/InstallationStatus.php` and `php bin/brite status` to report applied, pending, and unknown migration names from the configured database, without executing migrations or revealing connection credentials.
- Added an isolated inventory ordering/checksum regression test and expanded CI lint coverage to the signup endpoint, signup page, CLI, and installer.
- Approval now requires a persisted `email` verification record; `email_verified_at` alone is insufficient.
- No installer migration execution, schema migration, or initial tenant/owner bootstrap is enabled yet.
- GitHub Actions and local PHP/MariaDB test results have not been confirmed in this session.

## Cycle 93 email delivery reliability (partial)
- Migration 041 creates `registration_email_outbox`. Signup now atomically stores an AES-256-GCM encrypted verification email with the application, token hash and consent record, avoiding sending email before database commit.
- Set `ENROLLMENT_OUTBOX_KEY` to a base64-encoded 32-byte random key (never commit it). Losing or rotating the key before pending emails are delivered makes those emails undecryptable.
- `php bin/brite mail:send` processes queued emails from CLI with bounded retry and exponential delay; schedule it through an operator-controlled cron job only after testing mail transport and applying migrations.
- The worker clears the encrypted body after transport acceptance; transport acceptance is **not** proof of inbox delivery.
- **Operational limitation:** A worker terminated while sending leaves the message in `sending` state. It must be reconciled by an operator; automatic recovery could resend a delivered message. Mail provider callbacks, stale claim reconciliation, delivery metrics, and end-to-end testing remain pending.
- The CI test checks encryption round-trips and tamper rejection, but does not test a real mail transport or database.

## Reliability and operations hardening
- Corrected Enrollment approval UI so a reason is required for request-info/reject but not for approve.
- Corrected enrollment throttle bucket rollover handling.
- Outbox delivery excludes consumed or expired email-verification tokens.
- Added `php bin/brite mail:status` with aggregate pending/sending/sent/failed counts and stale (>15-minute) sending claim count; it does not expose recipient email or token content.
- **Manual intervention remains necessary** for stale `sending` claims to avoid duplicate verification emails. Mail transport acceptance does not guarantee delivery.
- Earlier commit `94184042b3eb77ace06f931d47ba73ccabaf3ba2` passed GitHub Actions Source smoke checks and Enrollment foundation checks; newer commits must pass their own runs.

## Email outbox recovery (operator only)
- `php bin/brite mail:status` now lists up to 50 stale message IDs, without displaying email addresses or token bodies.
- `php bin/brite mail:retry-stale ID --confirm-duplicate-risk` explicitly requeues a stale sending message only if its token is still unconsumed and unexpired, the encrypted body exists, and retry budget remains. It does not automatically recover stalled messages.
- **Duplicate delivery risk:** a worker may have delivered the message before crashing. Inspect mail provider logs before requeuing; the confirmation flag acknowledges this uncertainty. No automated resend is performed.
- Enrollment decision API now permits an empty reason for approval, while request-info/reject retain their reason validation.
- These changes are committed but require CI and DB-backed integration tests before deployment.
