# Cycle 92F — Initial owner and tenant provisioning

This sprint introduces `BriteScheduler\\Installation\\InitialOwnerProvisioner::provision(PDO $pdo, array $input, bool $confirmed, bool $alreadyLocked)`.

**Not connected to a web route or the live application.** It is a service for a future authenticated CLI installation orchestrator, not a standalone setup endpoint. The caller must hold the installation mutex, explicitly confirm provisioning, and use a newly migrated database.

Required input: `tenant_name`, `tenant_slug`, `owner_email`, `owner_first_name`, `owner_last_name`, `owner_password`. Optional `timezone` defaults to `America/New_York`.

Safeguards:
- CLI-only, explicit confirmation, and an already-held installation mutex
- MySQL/MariaDB only; verifies required migration and identity tables
- Refuses provisioning when any tenant, user, or membership exists
- Uses a database advisory lock to serialize first-account creation
- Hashes passwords using PHP `password_hash`; no plaintext storage
- Creates tenant, user, and owner membership in one transaction; rolls back on failure
- Returns only newly created numeric IDs, never the password or hash

## Validation

```bash
/opt/lampp/bin/php -l app/Installation/InitialOwnerProvisioner.php
/opt/lampp/bin/php tests/cycle92_initial_owner_provisioning_test.php
```

Before merge, perform a disposable MariaDB end-to-end test after all migrations. Confirm one owner membership, password verification, and that a second call refuses to provision; then drop the disposable database. Do not run provisioning against the existing `britescheduler` database.

The installer workflow, setup completion marker, and secure CLI orchestration remain future integration work. Do not treat this service as a completed installer.
