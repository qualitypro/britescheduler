<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();Auth::requireRole('contractor');
$pageTitle='Quote Requests';
require __DIR__.'/partials/app_header.php';
?>
<section class="card" style="max-width:1050px;margin:auto">
<h1>Quote Requests</h1>
<p class="muted">Choose a request to review its job details, photographs and conversation. Reply to clients and request additional details without leaving your inbox. Formal quote pricing is not yet available.</p>
<label for="contractorQuoteSearch" style="display:block;margin:12px 0">Find a request
<input id="contractorQuoteSearch" type="search" placeholder="Search by client, request number, service, or job description" style="display:block;width:100%;box-sizing:border-box;max-width:680px"></label>
<div id="contractorQuoteFilters" role="group" aria-label="Filter quote requests" style="display:flex;flex-wrap:wrap;gap:8px;margin:16px 0">
 <button type="button" data-quote-filter="all" aria-pressed="true">All</button>
 <button type="button" data-quote-filter="new" aria-pressed="false">New</button>
 <button type="button" data-quote-filter="waiting" aria-pressed="false">Waiting on client</button>
 <button type="button" data-quote-filter="closed" aria-pressed="false">Completed / closed</button>
</div>
<p id="contractorQuoteStatus" role="status" aria-live="polite">Loading quote requests...</p>
<div id="contractorQuoteInbox" style="display:grid;gap:16px"></div>
</section>
<script src="<?=htmlspecialchars(app_url('/assets/js/quote-conversation.js'),ENT_QUOTES,'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-formal-quote.js'),ENT_QUOTES,'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/contractor-quote-inbox.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
