<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireUser();
Auth::requireRole('contractor');
$pageTitle = 'My Contractor Profile';
require __DIR__ . '/partials/app_header.php';
?>
<section class="card" style="max-width:780px;margin:0 auto">
  <h1>My Contractor Profile</h1>
  <p class="muted">Introduce your business to clients reviewing your Marketplace bids and assignments. Only professional information belongs here. Profile visibility remains limited by existing appointment permissions.</p>
  <form id="contractorProfileForm" style="display:grid;gap:14px">
    <label>Professional headline
      <input name="headline" maxlength="140" style="display:block;width:100%;box-sizing:border-box" placeholder="Residential and commercial cleaning specialist">
    </label>
    <label>About my business
      <textarea name="biography" maxlength="3000" rows="6" style="display:block;width:100%;box-sizing:border-box"></textarea>
    </label>
    <label>Specialties
      <input name="specialties" maxlength="500" style="display:block;width:100%;box-sizing:border-box" placeholder="Deep cleaning, move-out cleaning">
    </label>
    <label>Years of experience
      <input name="years_experience" type="number" min="0" max="80" style="display:block;width:100%;box-sizing:border-box">
    </label>
    <label>Portfolio website (HTTPS only)
      <input name="portfolio_url" type="url" maxlength="500" placeholder="https://example.com/portfolio" style="display:block;width:100%;box-sizing:border-box">
    </label>
    <button type="submit" id="profileSave">Save profile</button>
    <p id="profileStatus" role="status" aria-live="polite"></p>
  </form>
</section>
<script src="<?=htmlspecialchars(app_url('/assets/js/contractor-profile.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
