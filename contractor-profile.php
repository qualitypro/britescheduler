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
  <div style="margin-top:24px;border-top:1px solid #e5e7eb;padding-top:20px">
    <h2>My Work Portfolio</h2>
    <p class="muted">Upload up to 20 examples of your best work. JPG, JPEG, or PNG only; maximum 5 MB per image. Photos are visible to authorized members of your tenant network.</p>
    <label for="portfolioUpload">Add portfolio photos</label>
    <input id="portfolioUpload" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple data-brite-pills="off">
    <p id="portfolioStatus" role="status" aria-live="polite"></p>
    <div id="portfolioGallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px"></div>
  </div>
</section>
<script src="<?=htmlspecialchars(app_url('/assets/js/contractor-portfolio.js'),ENT_QUOTES,'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/contractor-profile.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
