# Cycle 89 — CRM, Scheduling & Notifications

Branch: `feature/cycle89-crm-scheduling-notifications`

## Scope
- Customer 360 activity metrics and timeline filtering
- Tenant-local upcoming appointment counts
- Notification filtering and owner panel controls
- Read-only contractor dispatch summaries and up to three alternative appointment windows

## Alternative-window contract
The dispatch preview evaluates six-hour increments for seven days after the original start.
Only future, same-day windows with at least one contractor passing assignment policy,
availability and conflict checks are returned. Appointment duration is preserved.
Times are tenant-local wall-clock strings. DST validation rejects invalid or ambiguous
local times. The preview is advisory, not a reservation or assignment. Scheduling
mutations must revalidate with the existing transaction and contractor locks.

## Validation before merge
Run `php -l` on changed PHP files, `node --check assets/js/owner-notifications.js`,
and `php tests/cycle89_regression_test.php`. Run integration tests against a seeded
MariaDB instance for tenant isolation, role restrictions, DST boundaries, contractor
availability, and appointment overlap. Verify responsive browser rendering.
Source-level regression checks do not replace integration testing.

No migrations or secrets are included in Cycle 89.
