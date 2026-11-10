# Cycle 90B — Calendar Queries and Directory Pagination

Cycle 90A provides immutable date windows and the shared paginator.

## Added in Cycle 90B
- `api/appointments.php?view=week&date=2026-11-10&week_start=1` uses server-generated tenant-local date windows. Role and tenant restrictions remain in the existing appointment query.
- `api/appointments.php?start=...&end=...` remains supported for FullCalendar.
- `api/appointments.php?view=month&date=2026-11-10&page=1&per_page=25` provides paginated appointment/agenda results, including metadata.
- `api/contractors.php?page=1&per_page=25` adds management-only pagination. Existing unpaginated requests and contractor self-profile responses remain unchanged.
- Appointment pagination is opt-in: never paginate the visible calendar grid or truncate appointment selection controls.
- Calendar range responses include the calculated range when `view/date` is used.
- No migrations or secrets.

## Before merge
Run PHP lint and `php tests/cycle90_foundation_test.php`. Exercise client/contractor/owner role isolation, overlapping appointment boundaries, week start preferences, and pagination with seeded MariaDB. Test large datasets and verify existing FullCalendar rendering and lookup dropdowns. CI execution is not available through this GitHub-only workflow.
