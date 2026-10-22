# Brite Scheduler — Cycle 00 Baseline Audit
Date: 2026-10-21
Status: repository source inspection completed; runtime/database tests pending.

## Verified against GitHub main
- Main contains `api/user_preferences.php` with authenticated GET/PUT, CSRF verification on PUT, and allowlisted values for four palettes (ocean, forest, midnight, slate). Verify migration and multi-session behavior before marking WBS 7 complete.
- `partials/app_header.php` reads user preferences and applies palette/accessibility settings.
- `dashboard.v2.php`, `client-dashboard.php`, and `contractor-dashboard.php` exist. Management dashboard includes tab-workspace CSS. Full role-by-role above-the-fold and mobile behavior require browser testing (WBS 6).
- `scheduling.php` already includes `partials/client_booking_wizard.php` and `assets/js/client-booking-wizard.js` for client role. WBS 1 is **partially implemented**, not greenfield. Verify the management scheduling flow before building another wizard.
- `api/appointments.php` references AppointmentValidation, ContractorScheduleValidator, and ContractorAssignmentValidator, and validates tenant ownership for selected client locations. Test concurrency and permission paths.
- `client-locations.php` already includes service-location map UI; `migrations/005_client_locations.sql` includes a one-primary-per-client uniqueness mechanism. WBS 3 and 5 are partially implemented.
- `app/Geocoding.php` uses a persistent cache, opt-in Nominatim configuration, and distinguishes rejected/not_found/unavailable.
- `feature/location-intelligence` is **37 commits behind main and 0 ahead** as of audit. Do not merge or base new work on it without checking local unpushed changes.

## Important corrections to original WBS assumptions
1. Client booking wizard already exists; prioritize **staff/admin scheduling wizard parity**, not a second client wizard.
2. Client service-location map already exists; prioritize contractor radius/matching and map regression.
3. Four preference palettes already exist in source; focus on persistence, UI integration, and accessibility QA.
4. Invoice queries exist in client dashboard, but this alone does not establish completed payment workflows.
5. A missing file at `migrations/007_user_preferences.sql` does not prove a missing migration: discover actual schema/migration naming before changes.

## Cycle 00 exit checks — remaining local/runtime verification
- [ ] Record clean `git status` and actual local HEAD; check unpushed branches.
- [ ] Inventory all migrations, schema, and migration execution history on disposable DB.
- [ ] Run PHP syntax checks for all changed/critical PHP files.
- [ ] Exercise GET/PUT preferences and persistence across login sessions.
- [ ] Test client/contractor/admin dashboards at desktop and mobile widths.
- [ ] Test appointment create/edit and overlapping contractor assignment under tenant isolation.
- [ ] Validate client location map, geocoding failure states, and one-primary invariant.
- [ ] Run available automated tests and capture pass/fail outputs.
- [ ] Document database backup and rollback plan before feature migrations.

## Next batch (Cycle 01)
1. Dashboard: fix verified usability gaps only; preserve existing tabbed workspace.
2. Preferences: fix verified persistence, color, and accessibility gaps only.
3. Keep commits granular; one PR for the integrated batch; do not merge without runtime QA.

## Review rule
This audit documents source observations, **not** evidence of successful runtime tests. Do not mark any initiative fully complete solely on this report.
