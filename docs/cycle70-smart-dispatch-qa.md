# Cycle 70 — Smart Dispatch QA and hardening

## Scope and status

Cycle 70 adds a stale-state guard around the Cycle 69D appointment review. It does **not** change the server-side appointment assignment policy, ranking algorithm, or Marketplace bidding rules.

**Source-reviewed:** recommendation endpoint returns `ok: true` and a `contractors` array; the wizard supplies `location_id`, `service_id`, `starts_at`, `ends_at`, and optional `exclude_appointment_id`. **Not yet demonstrated:** authenticated browser and database-backed regression results.

## Regression matrix (run with test accounts and test appointments)

| ID | Scenario | Expected result |
| --- | --- | --- |
| 70-01 | Owner opens recommendations for tenant-owned saved location and valid interval | Ranked advisory contractor cards; no automatic assignment |
| 70-02 | Client or contractor requests recommendation API | HTTP 403 |
| 70-03 | Owner requests a location from another tenant | HTTP 404, no tenant data returned |
| 70-04 | Owner excludes appointment from another tenant | HTTP 404 |
| 70-05 | Invalid or missing location / malformed interval | HTTP 422 |
| 70-06 | End time at or before start / cross-midnight interval | HTTP 422 |
| 70-07 | Filter by dispatch status, qualification, offering, availability, maximum rate | Visible cards/count update; source list and server assignments unchanged |
| 70-08 | Selected contractor outside recommended service area | Advisory warning; cancel prevents submission |
| 70-09 | Recommendation service fails or returns invalid payload | Failure warning; cancel prevents submission |
| 70-10 | No contractor selected | Appointment may be saved unassigned, subject to existing validation |
| 70-11 | Contractor selection or client/time/service changes while recommendation request is in flight | Stale response rejected; no submission |
| 70-12 | Change selection or appointment fields while a confirmation dialog is displayed | Snapshot mismatch rejects submission |
| 70-13 | Cancel advisory warning confirmation | No appointment POST/PUT |
| 70-14 | Existing appointment contractor conflict | Save blocked by existing conflict check |
| 70-15 | Rejected service qualification / inactive contractor | Server-side assignment validation rejects even if client UI is bypassed |
| 70-16 | Contractor bidding outside normal coverage | Marketplace behavior remains independent from recommendation filtering |
| 70-17 | Two concurrent assignments of the same contractor | Server locking and conflict validation prevent invalid overlapping assignments |
| 70-18 | Keyboard, focus, and screen-reader review of filters and warnings | Controls usable; review status and errors announced |

## Evidence collection

Record each test's tenant role, request URL/method (without secrets), HTTP status, response shape, resulting appointment ID if applicable, and PASS/FAIL. For mutation tests, use disposable fixtures and verify the database before/after. Do not use production data.

### Automated local source checks

Run the repository script `scripts/qa/cycle70-source-check.sh` from a local checkout. It checks PHP syntax, expected dispatch API contract fields, snapshot guard integration, and tracked diff hygiene. It does **not** replace the authenticated matrix above.

## Release gate

Do not label Cycle 70 end-to-end complete until tests 70-01 through 70-18 have been executed and recorded against the local application. Any failures must be fixed before proceeding to licensing/module architecture work.
