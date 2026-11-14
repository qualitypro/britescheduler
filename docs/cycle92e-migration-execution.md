# Cycle 92E — Migration execution safety and recovery

Introduces guarded migration execution code for **fresh, empty MySQL/MariaDB databases only**. No endpoint, command or first-run entry point invokes the runner; it is not activated.

- `SqlStatements` splits migration SQL while respecting strings and comments and rejects executable comments.
- `MigrationPreflight` verifies filenames, hashes, nonempty SQL and refuses selected unsafe commands.
- `MigrationRunner` requires explicit confirmation, CLI execution (unless separately trusted by a future caller), MySQL/MariaDB, an exclusive installation mutex, no pre-existing recovery marker, and **zero existing database tables**.
- Creates a recovery marker **before** each migration's DDL and refuses subsequent execution after an interrupted migration. MySQL DDL cannot be safely rolled back as a transaction.
- `tests/cycle92_migration_execution_guards_test.php` tests parsing, checksum drift and unsafe SQL against temporary files; it does not connect to a database.

**Important limitations:** The SQL preflight is not a complete SQL security analyzer. A disposable empty-database integration test, review of all actual migration files, marker recovery procedure, bootstrap/schema history verification, owner and tenant provisioning, and explicit installer authorization are required before the runner can be invoked. The historical FirstRunInstaller is not activated or included. Never point this at the live `britescheduler` database.

Validation (not yet executed):
```bash
/opt/lampp/bin/php -l app/Installation/SqlStatements.php
/opt/lampp/bin/php -l app/Installation/MigrationPreflight.php
/opt/lampp/bin/php -l app/Installation/MigrationRunner.php
/opt/lampp/bin/php -l tests/cycle92_migration_execution_guards_test.php
/opt/lampp/bin/php tests/cycle92_migration_execution_guards_test.php
```

This is stacked on draft 92D and the prior 92A–92C branches; do not merge independently.
