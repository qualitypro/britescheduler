<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');
$pageTitle='Contractors';
require __DIR__.'/partials/app_header.php';
?>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-directory-accordion.css'),ENT_QUOTES,'UTF-8')?>">
<main class="brite-directory" data-directory="contractors">
  <div class="brite-directory-header"><div><h1>Contractors</h1><p class="muted">Select a record to review and edit its details.</p></div><button type="button" data-directory-refresh>Refresh</button></div>
  <details class="brite-directory-create"><summary>Add contractor</summary>
    <form data-directory-create>
      <div class="brite-directory-fields"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"></div>
      <div class="brite-directory-actions"><button type="submit">Create contractor</button></div>
    </form>
  </details>
  <p data-directory-status role="status" aria-live="polite">Loading…</p>
  <section class="brite-directory-list" data-directory-list aria-label="Contractors directory"></section>
</main>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-directory-accordion.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
