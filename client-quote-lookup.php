<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();
Auth::requireRole('client');
$pageTitle='My Quote Requests';
require __DIR__.'/partials/app_header.php';
?><link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-quote-ux.css'),ENT_QUOTES,'UTF-8')?>"><?php
?>
<section class="card" style="max-width:1050px;margin:auto">
 <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
  <div><h1>My Quote Requests</h1><p class="muted">Find requests you sent, check their status, and continue conversations with contractors.</p></div>
  <a href="<?=htmlspecialchars(app_url('/client-contractor-directory.php'),ENT_QUOTES,'UTF-8')?>">Find Contractors</a>
 </div>
 <div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0">
  <div style="flex:2;min-width:200px"><label for="clientQuoteSearch">Search by contractor, request number, or description</label><input id="clientQuoteSearch" type="search" placeholder="Search your requests" style="display:block;width:100%;box-sizing:border-box"></div>
  <div style="flex:1;min-width:170px"><label for="clientQuoteFilter">Status</label><select id="clientQuoteFilter" style="display:block;width:100%"><option value="all">All requests</option><option value="submitted">Awaiting response</option><option value="more_info_requested">Needs my attention</option><option value="quoted">Quote received</option><option value="closed">Accepted / closed</option></select></div>
 </div>
 <p id="clientQuoteLookupStatus" role="status" aria-live="polite">Loading your requests...</p>
 <div id="clientQuoteLookup" style="display:grid;gap:12px"></div>
</section>
<script src="<?=htmlspecialchars(app_url('/assets/js/quote-conversation.js'),ENT_QUOTES,'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/client-quote-lookup.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
