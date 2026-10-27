# Cycle 64 — Smart Dispatch Readiness (read-only)

## Existing source reviewed
- `app/ContractorEligibilityEngine.php`: tenant-scoped geographic coverage, availability, conflicts, qualifications.
- `app/ContractorRankingEngine.php`: deterministic scoring and workload.
- `app/ContractorAssignmentValidator.php`: final assignment eligibility policy (geography and qualifications).
- `api/contractor_recommendations.php`: general advisory endpoint.
- `api/owner_assignment_eligibility_preview.php`: owner reassignment policy preview.

## New interface
`GET /dashboard/api/dispatch_appointment_preview.php?appointment_id=123`

Only authenticated owner/admin/scheduler roles can access it. Uses the active tenant, loads the appointment within that tenant, requires an existing client location and a schedulable status, excludes the appointment from its own conflict and workload calculations, and returns ranked contractor candidates.

Each candidate includes `assignment_policy` with `allowed`, `warnings`, `errors`, qualification and geographic statuses. `eligible_for_review` is advisory, not authorization. `assignment_approved` and `automatic_assignment` are always false. Marketplace presence is reported; this endpoint does not change any listing.

## Acceptance / regression checks
1. Owner with a valid appointment gets HTTP 200 and read_only=true.
2. Admin/scheduler with access to the tenant gets a preview; client/contractor/accounting gets 403.
3. Missing/invalid appointment ID returns 422; foreign-tenant appointment returns 404.
4. Appointment without client location returns 422.
5. Completed/cancelled/no-show/in-progress appointment returns 409.
6. Conflicting contractor is marked unavailable for review.
7. Rejected qualification is blocked by assignment policy; unverified qualification is a warning.
8. Geographic non-match is not eligible for review.
9. Ordering is stable for ties (contractor ID).
10. Existing appointment, assignments, bids and Marketplace records remain unchanged after requests.
11. Verify cross-midnight and malformed date handling is rejected without writes.
12. Verify existing scheduling, Marketplace and reassignment flows still work.

## Deployment
No schema changes. No existing API routes replaced. PHP syntax-check the new endpoint and run role/tenant/browser checks before enabling any UI integration.

## Next gated cycles
- 65: parity tests and shared scheduling-conflict semantics; do not relax assignment policy.
- 66: optional owner-facing dispatch panel, single-active read-only contractor records.
- 67: explicit owner-confirmed assignment with fresh transactional validation and Marketplace guards.
- 68–70: job lifecycle, metrics and end-to-end regression tests.

## Known caveats
The existing eligibility engine starts from geographic matches; contractors with unconfigured service areas may be absent, rather than displayed as ineligible. The preview does not imply that an assignment can be performed. Time values are interpreted according to the application's existing stored appointment semantics.
