<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler','accounting');
$pageTitle='Clients';
require __DIR__.'/partials/app_header.php';
?>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-directory-accordion.css'),ENT_QUOTES,'UTF-8')?>">
<main class="brite-directory" data-directory="clients">
  <div class="brite-directory-header"><div><h1>Clients</h1><p class="muted">Select a record to review and edit its details.</p></div><button type="button" data-directory-refresh>Refresh</button></div>
  <details class="brite-directory-create"><summary>Add client</summary>
    <form data-directory-create>
      <div class="brite-directory-fields"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="company_name" placeholder="Company"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"></div>
      <div class="brite-directory-actions"><button type="submit">Create client</button></div>
    </form>
  </details>
  <p data-directory-status role="status" aria-live="polite">Loading…</p>
  <section class="brite-directory-list" data-directory-list aria-label="Clients directory"></section>
</main>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-directory-accordion.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-single-active-records.css'),ENT_QUOTES,'UTF-8')?>">
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-owner-single-active.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
