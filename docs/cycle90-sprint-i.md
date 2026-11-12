# Cycle 90I — Complete Marketplace Service Facets

The contractor Marketplace now fetches all service options across eligible, open, unassigned appointments in the authenticated tenant, rather than deriving the filter dropdown from the current page. The new `GET api/contractor_marketplace.php?facets=services` response contains distinct service IDs/names and uses the same tenant, date, status, listing, and unassigned constraints as Marketplace discovery. No client contact data is exposed.

The existing paginated search and service filters remain server-side. Existing non-facet API calls retain their prior response. No migrations or secrets.

Validation before merge: contractor role/active profile, cross-tenant isolation, services represented only on later pages, service facet changes when listings close, empty tenant, and UI/browser interaction. Runtime tests not executed.
