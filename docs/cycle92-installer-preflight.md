# Cycle 92A — Installer preflight foundation

This narrowly scoped change extracts **read-only** installer prerequisites from the unfinished Cycles 91–93 installer branch without merging its 200+ unrelated commits.

- `InstallerGate::inspect()` refuses to suggest setup when an installation lock or existing `.env` exists, or when a properly shaped setup token is absent.
- `MigrationInventory::list()` enumerates numbered SQL migration files and hashes their contents without executing SQL.
- `tests/cycle92_installer_preflight_test.php` uses a temporary directory, not the application's live database, to test lock/config/token guards and inventory ordering.
- **No web-accessible installer, database mutations, credentials, or owner provisioning** are introduced.

## Local validation

```bash
/opt/lampp/bin/php -l app/Installation/InstallerGate.php
/opt/lampp/bin/php -l app/Installation/MigrationInventory.php
/opt/lampp/bin/php -l tests/cycle92_installer_preflight_test.php
/opt/lampp/bin/php tests/cycle92_installer_preflight_test.php
```

## Before any installer activation

Complete independent review of CSRF and setup-token authentication, concurrency and idempotence, existing-installation protection, migration sequencing and rollback/recovery, permission handling, tenant/owner creation, and browser/CLI integration tests against a **disposable empty database**. Do not run a first-run installer against the existing Brite Scheduler database.
