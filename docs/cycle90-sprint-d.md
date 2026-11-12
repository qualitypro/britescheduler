# Cycle 90D — Client Directory Server Pagination Integration

- Client directory (`clients.v2.php`) now mounts the shared `BriteServerPagination` component.
- `brite-directory-accordion.js` requests only the selected page from `api/clients.php?page=N&per_page=N`, and renders returned records with the existing accordion editor.
- Refresh and create actions reload the current page; editing retains the current row's existing behavior.
- The shared remote paginator now registers the optional error callback.
- Other directories and lookup selectors retain their previous behavior; no accidental truncation of appointment wizard dropdowns.
- No schema migrations or secrets.

## Validation before merge
Test client directory navigation with 0, 1, 26 and 100+ records, create/refresh on later pages, owner/admin/scheduler/accounting permissions, tenant isolation, and browser console. Confirm client-side script order and pagination status with screen readers. PHP and browser runtime tests have not been run through the GitHub connection.
