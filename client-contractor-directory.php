<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();
Auth::requireRole('client');
$pageTitle='Contractor Directory';
require __DIR__.'/partials/app_header.php';
?>
<section class="card" style="margin-bottom:18px">
 <h1>Find a Contractor</h1>
 <p class="muted">Explore active contractors in your network, compare services and view their work. Quote requests are coming in the next release.</p>
 <form id="directoryFilters" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
  <label style="flex:2;min-width:180px">Search by name or specialty
   <input name="q" maxlength="100" placeholder="Cleaning, painting..." style="display:block;width:100%;box-sizing:border-box">
  </label>
  <label style="flex:1;min-width:160px">Service
   <select name="service_id" style="display:block;width:100%"><option value="">All services</option></select>
  </label>
  <label style="flex:1;min-width:130px">Service-area ZIP
   <input name="postal_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" placeholder="5-digit ZIP" style="display:block;width:100%;box-sizing:border-box">
  </label>
  <button type="submit">Search</button>
  <button type="reset">Clear</button>
 </form>
 <p class="muted" style="font-size:.85rem">ZIP filtering matches contractors who explicitly list that ZIP. Radius-based coverage is not included in this filter yet.</p>
 <p id="directoryStatus" role="status" aria-live="polite">Loading contractors...</p>
</section>
<section id="directoryResults" class="grid" aria-label="Contractor results"></section>
<dialog id="contractorDetails" style="border:0;border-radius:14px;max-width:min(760px,95vw);max-height:85vh;overflow:auto;padding:24px">
 <button type="button" id="directoryClose" style="float:right">Close</button>
 <div id="directoryDetailBody"></div>
</dialog>
<script src="<?=htmlspecialchars(app_url('/assets/js/client-contractor-directory.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
