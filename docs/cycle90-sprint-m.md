# Cycle 90M — Integration Hardening (First Pass)

- CRM overview's due-date boundary now uses TenantClock::today for the authenticated tenant rather than the server's default PHP timezone.
- Shared BriteServerPagination now exposes a Retry button after a failed request, preserving the current page and filter inputs.
- Added standalone source integration guards at tests/cycle90_integration_guards.php for CRM list SQL, tenant date handling, Marketplace facets, shared pagination, and screen wiring.

Run: php -l modules/crm/page.php && php tests/cycle90_integration_guards.php. Then execute authenticated API, browser, and database integration checks for every tenant role and pagination path. These tests have not been run through the GitHub connector. Cycle 90 remains unmerged. No secrets or migrations.
