<?php
// Temporary owner-only PHP diagnostics. Never expose phpinfo to other roles.
if (!defined('BRITE_MODULE_DISPATCH')) { http_response_code(403); exit; }
if (($tenant['role'] ?? '') !== 'owner') {
    http_response_code(403);
    ?>
    <section class="card" role="alert">
      <h1>Owner access required</h1>
      <p>PHP configuration diagnostics are available only to the tenant owner.</p>
    </section>
    <?php
    return;
}
// Exclude environment variables, request headers, and PHP credits by design.
// Even the remaining configuration can reveal sensitive server details.
ob_start();
phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
$phpInfoHtml = (string)ob_get_clean();
?>
<section class="card" data-brite-pills="off" style="display:grid;gap:10px">
  <div>
    <h1 style="margin:0">PHP configuration</h1>
    <p class="muted" style="margin:0">Temporary owner-only diagnostics. Contains server configuration details; do not share screenshots or output publicly.</p>
  </div>
  <iframe title="PHP configuration diagnostics" sandbox=""
    style="width:100%;height:min(75vh,900px);min-height:460px;border:1px solid #cbd5e1;border-radius:8px;background:#fff"
    srcdoc="<?=htmlspecialchars($phpInfoHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>"></iframe>
</section>
