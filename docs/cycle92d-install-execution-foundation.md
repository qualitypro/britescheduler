# Cycle 92D — Installation execution foundation

Adds the first write-capable installation primitive without activating the installer.

## Implemented
- `ConfigurationWriter::write()` writes a local `.env` using an exclusive temporary file and a create-only hard link, with owner-only permissions.
- Refuses to overwrite existing configuration or run after an installation lock exists.
- Validates supported configuration keys, connection identifiers, URL and port, and refuses control characters.
- `tests/cycle92_configuration_writer_test.php` exercises creation, permissions, overwrite protection, installed-directory refusal and invalid inputs against a disposable temporary directory.

## Validation required before merge
```bash
/opt/lampp/bin/php -l app/Installation/ConfigurationWriter.php
/opt/lampp/bin/php -l tests/cycle92_configuration_writer_test.php
/opt/lampp/bin/php tests/cycle92_configuration_writer_test.php
```

## Not activated
No browser installer endpoint, migration execution, tenant creation or owner provisioning is enabled. Existing Brite Scheduler databases and configuration are not touched. The unfinished historical installer branch contains executable migration logic but must not be merged wholesale; it needs SQL review, partial-failure recovery tests and disposable-empty-database integration tests before activation.

This PR is stacked on draft 92C, which depends on 92B and 92A. Do not merge it independently or test first-run installation against the live `britescheduler` database.
