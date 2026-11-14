# Cycle 92B — Safe setup wizard preview

This sprint builds on Cycle 92A's isolated read-only gate and migration inventory.

- `setup/preview.php` is **CLI-only** and emits a responsive HTML wizard preview. HTTP access returns 404.
- Shows prerequisite checks, migration file inventory, and the planned stages for database setup, migrations, owner/tenant creation, and review.
- All installation actions are disabled. No credentials are collected or exposed, no connection is attempted, and no SQL is executed.
- `InstallationStatus` and `MigrationPlan` provide read-only DB history inspection and strict migration-prefix validation for future authenticated setup.
- `tests/cycle92_migration_plan_test.php` covers fresh, partially applied, fully applied, duplicate, unknown, and gapped histories.

## Generate a preview locally

```bash
/opt/lampp/bin/php setup/preview.php > /tmp/brite-setup-preview.html
```

Open the generated file locally in a browser. Do not publish the preview or use it to initialize an existing database.

## Validation

```bash
/opt/lampp/bin/php -l setup/preview.php
/opt/lampp/bin/php -l app/Installation/InstallationStatus.php
/opt/lampp/bin/php -l app/Installation/MigrationPlan.php
/opt/lampp/bin/php tests/cycle92_migration_plan_test.php
/opt/lampp/bin/php tests/cycle92_migration_plan_test.php
```

Future activation requires setup-token/session authorization, CSRF, TLS, safe DB connectivity testing, credential handling, concurrent-install lock, migration recovery, and disposable-empty-database integration tests. No existing Brite Scheduler tenant database may be used for first-run testing.
