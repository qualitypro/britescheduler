<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();Auth::requireRole('client');
$pageTitle='Request a Quote';
require __DIR__.'/partials/app_header.php';
?>
<section class="card" style="max-width:850px;margin:auto">
<h1>Request a Quote</h1>
<p class="muted">Tell a contractor about your job and include 2–10 photos. Your request will be saved privately. Contractors will be able to respond in the next release.</p>
<form id="quoteWizard" enctype="multipart/form-data" style="display:grid;gap:16px">
 <div><label for="quoteContractor">Contractor</label><select id="quoteContractor" name="contractor_id" required style="display:block;width:100%"><option value="">Choose a contractor</option></select></div>
 <div><label for="quoteService">Service</label><select id="quoteService" name="service_id" style="display:block;width:100%"><option value="">Choose a service (optional)</option></select></div>
 <div><label for="quoteLocation">Your service location</label><select id="quoteLocation" name="client_location_id" required style="display:block;width:100%"><option value="">Choose your location</option></select><a href="<?=htmlspecialchars(app_url('/client-locations.php'),ENT_QUOTES,'UTF-8')?>">Manage service locations</a></div>
 <div><label for="quoteDescription">Job description (at least 20 characters)</label><textarea id="quoteDescription" name="description" minlength="20" maxlength="5000" rows="6" required style="display:block;width:100%;box-sizing:border-box" placeholder="Describe the work, size of the job, and any special requirements"></textarea></div>
 <div><label for="quoteDate">Preferred date (optional)</label><input id="quoteDate" name="preferred_date" type="date" style="display:block"></div>
 <div><label for="quotePhotos">Job photographs (2–10 JPG or PNG, max 5 MB each)</label><input id="quotePhotos" name="photos[]" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple required data-brite-pills="off" style="display:block"></div>
 <button id="quoteSubmit" type="submit">Submit Quote Request</button>
 <p id="quoteStatus" role="status" aria-live="polite"></p>
</form>
</section>
<section class="card" style="max-width:850px;margin:20px auto"><h2>My Quote Requests</h2><div id="quoteHistory">Loading...</div></section>
<script src="<?=htmlspecialchars(app_url('/assets/js/client-quote-wizard.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
