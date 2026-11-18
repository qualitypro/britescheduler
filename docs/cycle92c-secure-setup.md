# Cycle 92C — Secure setup prerequisites

Extends draft 92A and 92B. **No browser endpoint or installation execution is enabled.**

- `SetupAuthorization`: constant-time setup-token comparison, 15-minute session grant, CSRF tokens, session regeneration and temporary lockout after repeated failures.
- `InstallationMutex`: filesystem lock to serialize installation operations.
- `DatabaseConnectionPreflight`: validates connection identifiers and, when explicitly invoked, performs read-only MySQL connection/empty-schema checks. Connection failures return generic errors without credentials or raw exception details.
- `tests/cycle92_secure_setup_test.php`: temporary-directory tests for token refusal, installed-config denial, mutex contention and validation.
- Existing tenants, tables, and configuration are untouched. Do **not** point first-run installation tests at the live `britescheduler` database.

Validation:
```bash
/opt/lampp/bin/php -l app/Installation/SetupAuthorization.php
/opt/lampp/bin/php -l app/Installation/InstallationMutex.php
/opt/lampp/bin/php -l app/Installation/DatabaseConnectionPreflight.php
/opt/lampp/bin/php -l tests/cycle92_secure_setup_test.php
/opt/lampp/bin/php tests/cycle92_secure_setup_test.php
```

Remaining before activation: authenticated HTTPS browser flow, CSRF on every mutation, secure configuration writer, empty-database-only migration runner, owner/tenant creation, rollback/recovery, end-to-end tests and deployment hardening. Tests have not yet been executed in the user's local environment.
