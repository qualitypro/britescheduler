# Cycle 90A — Server-side Calendar and Pagination Foundation

- `app/ServerCalendar.php` calculates month/week/day date windows with exclusive end boundaries, using immutable PHP date objects.
- `api/calendar_range.php?view=month&date=2026-11-10&week_start=0` exposes tenant-aware date ranges without changing appointments.
- `app/ServerPagination.php` validates bounded page sizes and returns reusable metadata.
- `api/clients.php?page=1&per_page=25` enables tenant-scoped server pagination for management roles. Calls without pagination parameters retain the original response.
- Calendar grids should fetch the entire visible date range, not paginate away individual appointments.
- Other list endpoints and their front-end consumers will migrate incrementally after integration testing.
- Run `php tests/cycle90_foundation_test.php` and PHP lint, followed by authenticated tenant/role integration tests before merge.
