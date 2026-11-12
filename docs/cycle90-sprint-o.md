# Cycle 90O — Quote Filter Input Hardening

- Client quote requests and contractor quote inbox now reject non-string search parameters (including query arrays) with a 422 response instead of casting arrays to the string "Array" and emitting PHP warnings. The existing status validation, role authorization, tenant restrictions, and legacy unpaginated responses remain unchanged.
- Contractor Marketplace service ID validation now bounds IDs to positive 32-bit integers with a strict digit pattern. This prevents overflow/cast ambiguity for extremely large query strings while retaining normal service IDs.
- No schema changes, secrets, or main-branch merge.

Validation still required: PHP lint and automated tests, authenticated cross-tenant and role denial checks, quote search/status combinations, Marketplace facets, and pagination in a real browser. The connected GitHub tools cannot run the application/database.
