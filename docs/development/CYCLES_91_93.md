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

## Cycle 92 encrypted document vault foundation (not yet integrated)
- Added `app/Registration/DocumentVault.php` with authenticated AES-256-GCM encryption, random opaque storage IDs, exclusive private file creation, upload-only acceptance, 10 MiB maximum, and PDF/JPEG/PNG MIME checks.
- Configure `ENROLLMENT_DOCUMENT_ROOT` as a private directory outside `ENROLLMENT_WEB_ROOT` and the application directory. Configure a separate `ENROLLMENT_DOCUMENT_KEY` as base64-encoded 32 random bytes. Keep keys and documents out of Git.
- Added negative security tests for invalid references, absent documents and non-HTTP-upload inputs; CI now runs these tests.
- **Not complete:** no applicant-authenticated upload endpoint, database linkage, malware scanning, document access audit, retention deletion, provider verification, or contractor onboarding UI. The vault is an internal primitive only; storing a document does not verify an identity. Do not expose vault reads through public endpoints without tenant-scoped authorization and auditing.

## Cycle 91 migration plan safety
- Added `php bin/brite migrate:plan` to inspect the configured database and produce an ordered, read-only plan of pending migrations with SHA-256 checksums.
- The planner refuses unknown applied versions, duplicate inventory versions, and migration history gaps rather than guessing what to execute.
- Added pure regression tests for ordered pending migrations, unknown versions, duplicate entries and gaps.
- **No SQL execution is enabled.** Running migrations safely still requires reviewed migration parsing, lock and backup policies, idempotency checks, checksum persistence, partial-failure recovery, and an explicitly authorized installer flow.

## Cycle 91 migration file preflight
- Added `php bin/brite migrate:check`: read-only validation of pending migration inventory entries, file checksums, symlink refusal, empty SQL files, and selected unsupported SQL constructs.
- Added regression tests for accepted files, checksum drift and unsupported SQL.
- Preflight is **not** a complete SQL parser or a guarantee that migrations are safe or reversible. It does not execute SQL or create backups, and cannot substitute for reviewed migration execution and database integration testing.

## Cycle 92 contractor document review foundation
- Migration 042 adds `registration_document_audit` with document, application, tenant, actor, action and reason for sensitive review events. Its restrictive foreign keys mean deletion must be coordinated with audit retention policy.
- Added `DocumentReviewService::decide` and `api/enrollment_document_review.php`: CSRF-protected authenticated owner/admin review scoped to the current tenant, limited to contractor documents in pending status and eligible application states. Decisions and audit entries commit atomically.
- **Trust boundary:** document status `verified` indicates a reviewer marked the submitted document as reviewed; this does not create a `registration_verifications.government_id` record, grant account approval, or prove identity. A separate identity verification workflow is required.
- **Not ready for operational use:** applicant-authenticated uploads, safe document inspection, malware scanning, access audit, retention deletion and identity-verification provider integration remain unimplemented. Do not mark documents verified without actually inspecting them through a secure authorized workflow.

## Cycle 92 authenticated moderator document inspection
- Added `DocumentInspectionService` and POST `api/enrollment_document_inspect.php`. Existing authenticated tenant owner/admin roles and CSRF checks are required. The service joins the document to a contractor application belonging to the current tenant, decrypts the private vault object, validates its MIME, and writes a `viewed` audit entry before responding.
- Documents are returned as attachments, not inline browser content, with no-store caching, no-sniff, sandbox CSP, and frame denial. No public storage URLs or original filenames are disclosed.
- Requires migration 042 and the private vault configuration. No public inspection route should be added.
- **Outstanding:** applicant upload authorization, malware scanning, retention/deletion, identity verification, reviewer UI, and DB-backed integration/security testing. Do not use this endpoint in production until those controls and a security review are complete.

## Cycle 92 moderator queue integration
- The owner/admin enrollment queue now loads document metadata for only the applications already selected in the tenant-scoped queue.
- For each document, reviewers can request an audited attachment download and, while pending, submit a reasoned review decision. All actions use existing authenticated, CSRF-protected endpoints.
- This UI does not allow applicants to upload documents and does not constitute government ID verification. Operational use remains blocked pending upload authentication, malware scanning, retention, and identity verification.

## Cycle 92 applicant document upload
- Email verification now returns the verified application ID to the verification page, which regenerates the session ID and grants a 30-minute session-bound upload capability. This is not a general login or approval.
- Added `enrollment-documents.php` for contractor-only upload after verified email and `api/enrollment_document_upload.php` with CSRF, server-side application status/type validation, row locking, a maximum of 12 active documents per application, 10 MiB file limits, private encrypted vault storage and an atomic document/audit database transaction.
- Failed database transactions attempt to discard newly created vault objects. Reviewers use the existing audited inspection and decision workflow.
- **Production blockers:** upload malware scanning, file content sanitization, stronger applicant reauthentication/re-entry after the short-lived session, retention/deletion implementation, DB-backed integration testing, and real government ID verification. Do not expose this workflow publicly in production before those controls are implemented and security reviewed.

## Cycle 92 mandatory upload malware scanning
- Added `UploadScanner::scan` before vault storage. It requires a verified HTTP upload and a configured absolute executable path to a trusted ClamAV `clamscan` binary in `ENROLLMENT_CLAMSCAN_BIN`.
- The process is invoked without a shell, discards scanner output, and fails closed on malware detection, missing configuration, scanner errors, and a 30-second timeout.
- Added a negative regression test for non-upload files. Full positive-path scanning requires an HTTP upload integration environment with ClamAV and malware signature fixtures.
- **Security limitations:** scanners do not guarantee harmless documents; PDF/image sanitization, scan engine signature maintenance, resource controls, retention and identity verification still require operational controls. Uploads must not be enabled until the scanner is installed and integration-tested.

## Cycle 92 document retention foundation
- Newly uploaded documents receive a 90-day `delete_after` deadline. This is an initial application policy, not a jurisdiction-specific legal retention determination.
- CLI `php bin/brite documents:due` lists up to 100 expired document IDs without exposing storage keys. `php bin/brite documents:purge ID --confirm-retention` removes only expired encrypted objects and marks the record deleted while retaining the audit trail.
- Filesystem removal and SQL commits cannot be atomic; a crash after unlink but before commit requires a retry, and `DocumentVault::discard` tolerates missing objects. Operator intervention is required if the vault or database is unavailable.
- **Before production:** validate legal retention/hold requirements, add automated scheduled cleanup and monitoring, account for backups and disaster recovery copies, and test the full lifecycle against MariaDB and a configured private vault. A 90-day deadline must not override a lawful preservation hold.

## Cycle 92 preservation holds
- Migration 043 adds `registration_documents.retention_hold`, hold metadata and actor FK, and audit actions `hold_placed` and `hold_released`.
- Owners/admins can place or release a document hold from the enrollment queue, providing a reason. Both changes are tenant-scoped, CSRF-protected, transactional and audited.
- `documents:due` and `documents:purge` exclude held documents even when their 90-day retention deadline has passed.
- **Operational limitations:** this is an application-level preservation hold, not a substitute for legal hold procedures, off-site backup controls, access review or regulatory compliance. Migration 043 must be applied before enabling the new UI or retention commands.

## Cycle 92–93 onboarding integration regression tests
- Added `registration_lifecycle_integration_test.php` to reject premature email approval, rejected-application reactivation, contractor approval without government ID/phone and contractor approval without moderator authorization.
- Added `registration_approval_integration_test.php` using an isolated SQLite schema to exercise `ApprovalService::approve`: an email-verified contractor without identity verification must remain pending with no user created; an eligible client must provision a user, client and membership atomically.
- These tests run in enrollment CI. **Scope:** they validate service logic and transaction behavior, not the MariaDB migration schema, real upload scanning, authenticated HTTP requests, moderator UI, or a complete deployed onboarding journey. Full MariaDB and browser integration testing is still required.
