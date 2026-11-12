# Cycle 90E — Contractor Directory Server Pagination and Sorting

- Contractor directory now uses the shared `BriteServerPagination` component and fetches only the selected page.
- Sort buttons send `sort=name|created_at|updated_at|status` and `direction=asc|desc` to the API, then reset to page one.
- `api/contractors.php` uses a strict SQL sort-column allowlist and stable `id` tie-breaker.
- Existing unpaginated API requests (including scheduling lookup dropdowns) remain unchanged.
- Contractor self-profile access, tenant filters, and management permissions remain enforced.
- Contractor service qualifications and offerings UI are unchanged.

## Before merging
Test server sorting across multiple pages, creation/refresh, edit and qualification interactions, all management roles, contractor self access, tenant isolation, and invalid sort values. Execute PHP lint, JavaScript syntax checks and authenticated browser/database integration tests. These runtime checks have not been executed via GitHub.
