# Cycle 65 — Dispatch / Assignment Scheduling Parity

## Scope
The read-only dispatch appointment preview now invokes the same `check_contractor_availability` and `check_contractor_conflicts` functions as the existing appointment assignment path. Each candidate includes `schedule_policy: {allowed, errors}`. The candidate is eligible for review only if both the shared scheduling policy and assignment eligibility policy pass, in addition to the existing recommendation checks.

No appointment, bid, contractor, or tenant records are written. No migrations or changes to the existing scheduling endpoint.

## Test matrix
1. No availability rules: backward-compatible unrestricted availability.
2. Weekly available rule fully covers interval: allowed.
3. Partial weekly interval: disallowed.
4. Date-specific available override: allowed when fully covered.
5. Date-specific unavailable overlap: disallowed.
6. Assigned or accepted overlapping appointment: disallowed.
7. Own appointment excluded from conflict checks.
8. Cross-tenant appointment: not accessible.
9. Rejected qualification: disallowed; unverified qualification: warning, not automatically verified.
10. Geographic mismatch: disallowed.
11. Marketplace listing: only flagged; no automatic award.
12. Read-only GET repeated: no database changes.

## Important discrepancy observed
The older recommendation engine's private conflict query filters appointment and assignment statuses more narrowly than `check_contractor_conflicts` in the actual scheduling path. This cycle uses the shared scheduling function as an additional safety gate in the appointment preview, **without changing legacy recommendation behavior**. The older engine can still rank a candidate whose `schedule_policy.allowed` is false; the new preview's `eligible_for_review` then becomes false.

## Acceptance
Run PHP lint and exercise the API with tenant-owned appointments; check that `schedule_policy` reflects actual assignment behavior. The endpoint remains advisory and must not be used as authorization for a write.
