# Cycle 90P — Automated Source Regression Coverage

- Expanded scripts/ci-smoke.sh to lint the Cycle 90 CRM, calendar, pagination, Marketplace, quote APIs, and related PHP/JavaScript entry points.
- CI now runs all three Cycle 90 standalone PHP regression suites, including calendar/pagination foundation, pagination boundaries, and source integration guards.
- Source guards now cover tenant-scoped quote filters, contractor/client role checks, Marketplace parameter scoping, and malformed quote search rejection.
- Existing .github/workflows/source-smoke.yml already invokes scripts/ci-smoke.sh on pull requests, so these checks are wired to PR CI without changing workflow permissions.

Important: these are lint/unit/source-contract checks, **not authenticated integration tests**. They cannot establish actual tenant isolation or workflow correctness without a configured database and browser. No CI runs or local PHP execution were performed during this GitHub-only sprint. No migrations, secrets, or main-branch changes.
