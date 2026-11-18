# Sprint 92G — Installation completion gate (phase 1)

`InstallationCompletion::finalize($root, $pdo, $confirmed, $alreadyLocked)` is a CLI-only finalization primitive.

The caller must hold the installer filesystem mutex. The gate checks that the local configuration file exists and is not a symlink, no migration recovery marker is present, the migration inventory has been completely applied, and at least one active owner membership exists. It then creates `.brite-installed.lock` using exclusive create and owner-only permissions. It refuses to overwrite an existing completion marker.

This component is **not yet a complete installation workflow**. It does not collect input, create a database, run migrations, create an owner, or authenticate an interactive setup request. The installer orchestrator must call those independently and in the correct order. Never use this on an existing live tenant database.

Validation:

```
/opt/lampp/bin/php -l app/Installation/InstallationCompletion.php
/opt/lampp/bin/php tests/cycle92_installation_completion_test.php
```

Next: disposable MariaDB end-to-end test for finalization and duplicate refusal, then CLI orchestration, error recovery, and a separately security-reviewed guided setup UI.
