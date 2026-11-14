<?php
declare(strict_types=1);
// CLI-only wizard preview. Does not open an installer or accept credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__).'/app/Installation/InstallerGate.php';
require_once dirname(__DIR__).'/app/Installation/MigrationInventory.php';
use BriteScheduler\Installation\InstallerGate;
use BriteScheduler\Installation\MigrationInventory;
$root=dirname(__DIR__);
$state=InstallerGate::inspect($root);
$inventory=MigrationInventory::list($root);
$h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Brite Scheduler — Installation Preview</title>
<style>
:root{font-family:system-ui,sans-serif;color-scheme:light}
body{margin:0;background:#f3f6fb;color:#17263d}
main{max-width:920px;margin:40px auto;padding:24px}
header{margin-bottom:24px}
h1{margin:0 0 8px;font-size:2rem}
p{line-height:1.55}
.panel{background:white;border:1px solid #d8e0ec;border-radius:14px;padding:24px;margin:18px 0}
.steps{display:flex;flex-wrap:wrap;gap:8px}
.step{background:#e8eef8;border-radius:999px;padding:8px 14px;font-weight:600}
.notice{border-left:4px solid #bd6a00;padding:12px 16px;background:#fff7e7}
dl{display:grid;grid-template-columns:minmax(170px,1fr) 2fr;gap:12px}
dt{font-weight:600}
dd{margin:0}
button{padding:12px 18px;border:0;border-radius:8px;background:#617189;color:white;cursor:not-allowed}
small{color:#566780}
</style></head><body><main>
<header><h1>Brite Scheduler — Setup Wizard</h1>
<p>Cycle 92B preview · Read-only, generated locally from the command line</p></header>
<div class="notice" role="status"><strong>Preview only.</strong> Installation, database connection testing, owner creation, and configuration saving are disabled.</div>
<section class="panel"><h2>Setup stages</h2><div class="steps">
<span class="step">1. Prerequisites</span><span class="step">2. Database</span>
<span class="step">3. Migrations</span><span class="step">4. Owner &amp; Tenant</span>
<span class="step">5. Review</span></div></section>
<section class="panel"><h2>Installation readiness</h2><dl>
<dt>Existing configuration</dt><dd><?= $state['existing_configuration']?'Detected — setup blocked':'Not detected' ?></dd>
<dt>Installation lock</dt><dd><?= $state['installed_lock']?'Detected — setup blocked':'Not detected' ?></dd>
<dt>Setup token</dt><dd><?= $state['setup_token_configured']?'Configured (value hidden)':'Not configured' ?></dd>
<dt>Setup eligibility</dt><dd><?= $state['setup_may_begin']?'Preflight prerequisites met':'Not eligible' ?></dd>
<dt>Migration files</dt><dd><?= count($inventory) ?> files available for future review</dd>
</dl><small>Eligibility is only a preliminary check, not authorization to install.</small></section>
<section class="panel"><h2>Database configuration</h2><p>Database connectivity and permissions will be checked in a later, authenticated implementation. Credentials are never included in this preview.</p><button disabled>Test connection — coming soon</button></section>
<section class="panel"><h2>Migration preview</h2><p><?= count($inventory) ?> migration files detected. This preview does not inspect applied database migrations or execute SQL.</p>
<details><summary>Show migration versions</summary><ol>
<?php foreach($inventory as $item): ?><li><code><?= $h($item['version']) ?></code></li><?php endforeach; ?>
</ol></details></section>
<section class="panel"><h2>First owner and tenant</h2><p>Account creation will require an authorized first-run installation against a disposable, empty database. Existing tenants will not be modified.</p><button disabled>Create tenant — coming soon</button></section>
</main></body></html>
