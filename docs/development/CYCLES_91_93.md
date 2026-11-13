# Cycles 91–93 — Installation and registration

This branch is isolated from main until local review. No migration is run by GitHub commits.

## Cycle 91 — Native PHP installation
- 91A: read-only environment diagnostics and ordered SQL migration inventory (implemented)
- 91B: protected browser installation wizard (not implemented)
- 91C: transactional/resumable schema migration execution and first-owner bootstrap (not implemented)
- 91D: installer parity audit, tests, then retire the legacy Python installer (not implemented)

### CLI foundation
```sh
/opt/lampp/bin/php bin/brite doctor
/opt/lampp/bin/php bin/brite migrations
```
Neither command modifies configuration, database, or accounts. The doctor reports checks only, never secret values. An Apache .htaccess check is not proof of web-server protection; production installation must verify web server configuration independently.

## Cycle 92 — Contractor onboarding and identity
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
