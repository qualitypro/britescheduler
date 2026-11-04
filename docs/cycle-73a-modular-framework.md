# Cycle 73A — Modular Framework Foundation

## Installation
1. Deploy the latest repository files to the Brite Scheduler application directory.
2. Apply `migrations/026_tenant_modules.sql` to the `britescheduler` database.
3. Sign in as an owner or administrator and open **Settings → Installed Modules**.
4. Enable **Sample Test Module**, then reload the page.
5. The **Modules → Test Module** menu entry appears for authenticated users of the activated tenant.
6. Disable the module and reload to remove the menu entry; direct requests to `module.php?module=sample-test` will then return 404.

## Module contract
Each trusted, server-installed module is a subdirectory of `modules/` containing:
- `module.json` with `key`, `name`, `menu_label`, `version`, `description`, `roles` and `page`.
- A single entrypoint PHP page, e.g. `page.php`.

The registry discovers directory names with conservative slugs, verifies manifest keys and a simple PHP entrypoint filename, and exposes only enabled modules whose role allowlist matches the active tenant membership. Modules are **installed by a trusted server deployer**, not uploaded or installed from the web UI. Enabling a module executes its trusted PHP code within the application; do not install untrusted modules.

## Access control
- Activation API: owner/admin only, CSRF-protected POST, parameterized tenant-scoped database update.
- Navigation: authenticated user + active tenant + role allowlist + enabled module.
- Dispatcher: authenticated user + active tenant + role allowlist + enabled module; unavailable modules return 404.
- Module PHP entrypoint refuses direct requests outside the dispatcher.

## Important operational notes
- This cycle does not automatically run database migrations.
- The first implementation supports one page/menu item per module; hooks, dependency resolution, migrations, asset registration and multiple menu items are future framework extensions.
- The module directory must be writable only by trusted deployers, not application users.
- Module activation changes appear in the sidebar after page reload.
- Browser-based QA and defect mitigation remain scheduled for a separate cycle.
