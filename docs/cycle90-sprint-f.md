# Cycle 90F — Marketplace and Quote API Pagination

Three existing authenticated GET APIs now accept optional `page` and `per_page`:
- `api/contractor_marketplace.php`: eligible open marketplace appointments, same tenant, active contractor, no assignment. Count query mirrors listing joins and visibility restrictions.
- `api/client_quote_requests.php`: only the authenticated client's quote requests; photographs loaded only for page records.
- `api/contractor_quote_inbox.php`: only the authenticated contractor's quote inbox; photographs loaded only for page records.

All return `pagination` metadata when requested. Legacy calls keep their original caps and response arrays. ServerPagination validates bounds (1–100 rows). No role or tenant changes, migrations, or secrets.

Next: connect actual Marketplace and quote list interfaces to shared remote paginator. Validate SQL syntax, record counts, visibility, no cross-tenant leaks, and photo modal functionality against seeded database before merge. No runtime tests have been executed.
