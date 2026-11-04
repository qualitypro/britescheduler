<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();Auth::requireRole('contractor');
$pageTitle='New Quote Requests';
require __DIR__.'/partials/app_header.php';
?>
<section class="card" style="max-width:1050px;margin:auto">
<h1>New Quote Requests</h1>
<p class="muted">Review requests addressed to your contractor profile, including job details and private photographs. Quote responses and follow-up messaging are coming in the next update.</p>
<p id="contractorQuoteStatus" role="status" aria-live="polite">Loading quote requests...</p>
<div id="contractorQuoteInbox" style="display:grid;gap:16px"></div>
</section>
<script src="<?=htmlspecialchars(app_url('/assets/js/quote-conversation.js'),ENT_QUOTES,'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/contractor-quote-inbox.js'),ENT_QUOTES,'UTF-8')?>"></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
