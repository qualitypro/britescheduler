<?php
if(!defined('BRITE_MODULE_DISPATCH')){http_response_code(403);exit;}
?>
<style>
.msg-workspace{display:grid;grid-template-columns:minmax(250px,340px) minmax(0,1fr);gap:16px;min-height:520px}
.msg-pane{border:1px solid #cbd5e1;border-radius:12px;padding:16px;min-width:0}
.msg-thread{display:grid;gap:4px;text-align:left;width:100%;padding:12px;margin-bottom:8px;border:1px solid #cbd5e1;background:transparent;border-radius:10px;cursor:pointer}
.msg-thread[aria-current="true"]{border-color:#2563eb;box-shadow:inset 3px 0 #2563eb}
.msg-thread-name{font-size:1rem}.msg-thread-subject,.msg-thread-preview{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
.msg-thread-preview,.msg-thread-meta{color:#64748b;font-size:.85rem}.msg-thread-meta{display:flex;justify-content:space-between;gap:8px}
.msg-unread{background:#1d4ed8;color:#fff;padding:2px 7px;border-radius:20px}
.msg-history{display:flex;flex-direction:column;gap:12px;max-height:440px;min-height:220px;overflow-y:auto;padding:12px;background:rgba(128,128,128,.06);border-radius:10px}
.msg-bubble{align-self:flex-start;max-width:85%;border:1px solid #cbd5e1;border-radius:12px;padding:12px;background:var(--brite-surface,#fff);overflow-wrap:anywhere}
.msg-bubble.msg-mine{align-self:flex-end;background:#e0edff;color:#172033}
.msg-bubble p{margin:8px 0}.msg-bubble small{opacity:.75}
.msg-review-item{display:grid;gap:5px;width:100%;text-align:left;margin:8px 0;padding:12px;border:1px solid #cbd5e1;border-radius:9px;background:transparent}.msg-review-item small{color:#64748b}.msg-form{display:grid;gap:12px}.msg-form input,.msg-form select,.msg-form textarea{width:100%;box-sizing:border-box}

/* Messaging forms: conventional rectangular fields instead of global pill styling. */
.brite-app .brite-content form#msgReply.msg-inline-reply textarea#msgReplyBody{display:block!important;width:100%!important;min-height:120px!important;border-radius:0!important;border:1px solid #94a3b8!important;padding:12px!important;background:#fff!important;box-shadow:none!important;transform:none!important;clip-path:none!important;appearance:none!important;-webkit-appearance:none!important;resize:vertical!important}
.brite-app .brite-content form#msgReply.msg-inline-reply button[type="submit"]{border-radius:4px!important;min-height:42px}
.brite-app .brite-content #msgReply #msgReplyBody{border-radius:4px!important;appearance:none!important;-webkit-appearance:none!important;clip-path:none!important;background-image:none!important;}
#msgCreate label,#msgReply label,#msgOwnerReview>label{display:grid;gap:6px;font-weight:600}
#msgCreate input,#msgCreate select,#msgCreate textarea,
#msgReply textarea,#msgReviewSearch{
 appearance:auto!important;border-radius:6px!important;
 border:1px solid var(--brite-border-strong,#cbd5e1)!important;
 background:var(--brite-surface,#fff)!important;color:var(--brite-text,#0f172a)!important;
 box-shadow:none!important;padding:10px 12px!important;
 min-height:42px;width:100%;max-width:100%;box-sizing:border-box;
 font:inherit;line-height:1.4;
}
#msgCreate textarea,#msgReply textarea{min-height:105px;resize:vertical}
#msgCreate input:focus-visible,#msgCreate select:focus-visible,
#msgCreate textarea:focus-visible,#msgReply textarea:focus-visible,
#msgReviewSearch:focus-visible{outline:2px solid #2563eb!important;outline-offset:2px}
#msgCreate button[type=submit],#msgReply button[type=submit]{
 border-radius:6px!important;justify-self:start;padding:10px 18px!important;
}
@media(max-width:760px){.msg-workspace{grid-template-columns:1fr;min-height:0}.msg-history{max-height:360px}}

/* Cycle 75L: compact, task-focused messaging workspace. */
.msg-workspace{grid-template-columns:minmax(220px,290px) minmax(0,1fr);gap:10px;min-height:430px}
.msg-pane{padding:12px;border-radius:9px}
.msg-pane h2{font-size:1.05rem;line-height:1.3;margin:0 0 8px}
.msg-pane h3{font-size:.98rem;margin:0 0 8px}
.msg-thread{gap:2px;padding:9px 10px;margin-bottom:5px;border-radius:7px}
.msg-thread[hidden]{display:none!important}
.msg-thread:hover{background:rgba(100,116,139,.08)}
.msg-thread:focus-visible{outline:2px solid #2563eb;outline-offset:1px}
.msg-thread-name{font-size:.94rem}
.msg-thread-subject{font-size:.85rem}
.msg-thread-preview,.msg-thread-meta{font-size:.77rem}
.msg-history{gap:8px;min-height:180px;max-height:360px;padding:10px;border-radius:7px}
.msg-bubble{padding:8px 10px;border-radius:9px;max-width:88%;font-size:.91rem}
.msg-bubble p{margin:5px 0}
.msg-form{gap:8px}
#msgCreate label,#msgReply label{gap:3px;font-size:.88rem}
#msgCreate input,#msgCreate select,#msgCreate textarea,#msgReply textarea{min-height:36px;padding:7px 9px!important;border-radius:5px!important}
#msgCreate textarea{min-height:88px}
.brite-app .brite-content form#msgReply.msg-inline-reply textarea#msgReplyBody{min-height:76px!important;padding:8px 10px!important;border-radius:5px!important;background:var(--brite-surface,#fff)!important;color:var(--brite-text,#0f172a)!important}
#msgCreate button[type=submit],#msgReply button[type=submit]{min-height:36px!important;padding:7px 13px!important}
.msg-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.msg-actions button{min-height:34px;padding:6px 10px;border-radius:6px}
.msg-actions .msg-primary{background:#1d4ed8;color:#fff;border-color:#1d4ed8;font-weight:600}
.msg-actions .msg-secondary{background:transparent;color:inherit}
.msg-toolbar{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:7px}
.msg-toolbar h2{margin:0}
.msg-help{font-size:.78rem;color:var(--brite-muted,#64748b);margin:0}
#msgStatus:empty{display:none}
#msgStatus:not(:empty){margin:0;padding:5px 9px;border-left:3px solid #64748b;font-size:.84rem}
#msgThreadSearch{width:100%;min-height:34px;box-sizing:border-box;margin:0 0 8px;border-radius:6px!important;padding:6px 9px!important}
#msgCompose .muted{font-size:.82rem;margin:0 0 10px}
#msgDetail>h2{margin-bottom:9px}
#msgReply{margin-top:9px!important}
#msgReply .msg-actions{justify-content:flex-end}
@media(max-width:760px){.msg-workspace{grid-template-columns:1fr;min-height:0}.msg-history{max-height:310px}.msg-pane{padding:10px}}

.msg-tabs{display:flex;gap:4px;border-bottom:1px solid #cbd5e1}.msg-tab{border:0!important;border-bottom:3px solid transparent!important;border-radius:6px 6px 0 0!important;background:transparent!important;color:inherit!important;padding:9px 14px!important;font-weight:600}.msg-tab[aria-selected="true"]{border-bottom-color:#2563eb!important;background:rgba(37,99,235,.08)!important}.msg-tab:focus-visible{outline:2px solid #2563eb}.msg-tabpanel[hidden]{display:none!important}#msgOwnerReview{border:0;padding:4px 0}

/* Cycle 90AL: compact messaging module without horizontal page scrolling. */
.msg-workspace{grid-template-columns:minmax(190px,280px) minmax(0,1fr);gap:10px;max-width:100%;min-width:0}
.msg-pane{padding:10px;min-width:0;max-width:100%;overflow-wrap:anywhere}
.msg-thread{padding:8px;margin-bottom:5px;font-size:.8rem}
.msg-thread-name{font-size:.85rem}
.msg-thread-preview,.msg-thread-meta{font-size:.75rem}
.msg-form{gap:8px}
.msg-workspace input,.msg-workspace select,.msg-workspace textarea{max-width:100%;min-width:0}
@media(max-width:700px){.msg-workspace{grid-template-columns:minmax(0,1fr)}}
/* Cycle 98E — vertical workspace navigation and compact message surfaces. */
.msg-module-layout{display:grid;grid-template-columns:174px minmax(0,1fr);gap:10px;align-items:start;min-width:0}
.msg-module-nav{display:flex;flex-direction:column;gap:4px;padding:7px;border:1px solid var(--brite-border,#cbd5e1);border-radius:8px;background:var(--brite-surface,#fff)}
.msg-module-nav .msg-tab{display:flex;align-items:center;justify-content:flex-start;width:100%;min-height:31px;padding:6px 9px!important;text-align:left;font-size:.79rem;line-height:1.25;border:0!important;border-left:3px solid transparent!important;border-radius:5px!important}
.msg-module-nav .msg-tab[aria-selected="true"]{border-left-color:#2563eb!important;background:rgba(37,99,235,.09)!important}
.msg-module-body{min-width:0}
.msg-module-body .msg-tabpanel[hidden]{display:none!important}
.msg-module-body .msg-workspace{grid-template-columns:minmax(175px,250px) minmax(0,1fr);gap:7px;min-height:330px}
.msg-module-body .msg-pane{padding:8px;border-radius:7px}
.msg-module-body .msg-pane h2{font-size:.91rem;margin:0 0 5px!important}
.msg-module-body .msg-pane h3{font-size:.85rem;margin:0 0 5px!important}
.msg-module-body .msg-toolbar{gap:5px;margin-bottom:5px}
.msg-module-body .msg-toolbar button,.msg-module-body #msgNew{min-height:29px;padding:4px 7px;font-size:.77rem;border-radius:5px}
.msg-module-body #msgNew{margin-bottom:6px!important}
.msg-module-body #msgThreadSearch{min-height:29px;margin-bottom:6px;padding:4px 7px!important;font-size:.79rem}
.msg-module-body .msg-thread{gap:1px;padding:6px 8px;margin-bottom:4px;border-radius:6px}
.msg-module-body .msg-thread-name{font-size:.8rem}
.msg-module-body .msg-thread-subject{font-size:.76rem}
.msg-module-body .msg-thread-preview,.msg-module-body .msg-thread-meta{font-size:.7rem}
.msg-module-body .msg-unread{padding:1px 5px;font-size:.68rem}
.msg-module-body .msg-history{gap:6px;min-height:170px;max-height:340px;padding:7px;border-radius:6px}
.msg-module-body .msg-bubble{max-width:90%;padding:6px 8px;border-radius:7px;font-size:.8rem}
.msg-module-body .msg-bubble p{margin:3px 0;line-height:1.35}
.msg-module-body .msg-bubble small{font-size:.7rem}
.msg-module-body .msg-form{gap:6px}
.msg-module-body #msgCreate label,.msg-module-body #msgReply label,.msg-module-body #msgOwnerReview>label{gap:3px;font-size:.78rem}
.msg-module-body #msgCreate :is(input,select,textarea),.msg-module-body #msgReviewSearch{min-height:30px;padding:5px 7px!important;font-size:.8rem}
.msg-module-body #msgCreate textarea{min-height:75px}
.brite-app .brite-content .msg-module-body form#msgReply.msg-inline-reply textarea#msgReplyBody{min-height:66px!important;padding:6px 8px!important;font-size:.8rem!important}
.msg-module-body .msg-actions{gap:5px}
.msg-module-body .msg-actions button,.msg-module-body #msgCreate button[type=submit],.msg-module-body #msgReply button[type=submit]{min-height:30px!important;padding:5px 9px!important;font-size:.78rem}
.msg-module-body .msg-help{font-size:.71rem}
.msg-module-body #msgOwnerReview{padding:7px 9px}
.msg-module-body #msgOwnerReview>.muted{font-size:.76rem;line-height:1.35}
.msg-module-body .msg-review-item{gap:3px;margin:4px 0;padding:7px 8px;border-radius:6px;font-size:.78rem}
.msg-module-body .msg-review-item small{font-size:.7rem}
.msg-preferences{padding:10px 12px}
.msg-preferences h2{margin:0 0 8px!important;font-size:.96rem}
.msg-preferences label{display:flex;align-items:center;gap:7px;font-size:.83rem}
.msg-preferences p{font-size:.77rem;line-height:1.4;margin:7px 0}
@media(max-width:850px){.msg-module-layout{grid-template-columns:142px minmax(0,1fr)}.msg-module-body .msg-workspace{grid-template-columns:minmax(145px,205px) minmax(0,1fr)}}
@media(max-width:650px){.msg-module-layout{grid-template-columns:minmax(0,1fr)}.msg-module-nav{flex-direction:row;flex-wrap:wrap}.msg-module-nav .msg-tab{width:auto;flex:1 1 auto;justify-content:center;border-left:0!important;border-bottom:3px solid transparent!important}.msg-module-nav .msg-tab[aria-selected="true"]{border-left:0!important;border-bottom-color:#2563eb!important}.msg-module-body .msg-workspace{grid-template-columns:minmax(0,1fr);min-height:0}.msg-module-body .msg-history{max-height:290px}}

/* Cycle 98F — high-volume conversation table with selected-row detail. */
.msg-module-body .msg-inbox-table-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(300px,1fr);gap:8px}
.msg-module-body .msg-inbox-table-layout>.msg-pane:first-child{min-width:0}
.msg-table-tools{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin:6px 0;font-size:.72rem}
.msg-table-tools select{min-height:29px;padding:3px 5px;font-size:.75rem;max-width:170px}
.msg-table-scroll{overflow-x:auto;max-width:100%;border:1px solid var(--brite-border,#cbd5e1);border-radius:7px}
.msg-conversation-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:.76rem}
.msg-conversation-table th{position:sticky;top:0;text-align:left;background:var(--brite-surface-muted,#f1f5f9);font-size:.71rem;font-weight:700;padding:7px 6px;border-bottom:1px solid var(--brite-border,#cbd5e1)}
.msg-conversation-table th:nth-child(1){width:25%}.msg-conversation-table th:nth-child(2){width:39%}.msg-conversation-table th:nth-child(3){width:23%}.msg-conversation-table th:nth-child(4){width:13%}
.msg-conversation-table td{padding:0!important;border-bottom:1px solid var(--brite-border,#e2e8f0);vertical-align:top;overflow:hidden}
.msg-conversation-table tbody tr:last-child td{border-bottom:0}
.msg-conversation-table tbody tr[aria-selected="true"]{background:rgba(37,99,235,.09)}
.msg-conversation-table tbody tr:hover{background:var(--brite-surface-muted,#f1f5f9)}
.msg-conversation-table .msg-cell-select{display:block;width:100%;min-height:35px;border:0!important;background:transparent!important;color:inherit!important;text-align:left!important;padding:7px 6px!important;font-size:.76rem!important;font-weight:inherit;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:0!important}
.msg-conversation-table .msg-cell-select:focus-visible{outline:2px solid #2563eb!important;outline-offset:-2px}
.msg-conversation-table .msg-table-subject{font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.msg-conversation-table .msg-table-preview{font-size:.7rem;color:var(--brite-muted,#64748b);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.msg-conversation-table .msg-table-date{display:block;padding:8px 5px;font-size:.7rem;overflow-wrap:anywhere}
.msg-conversation-table .msg-table-unread{display:inline-block;margin:7px 3px;padding:2px 5px;border-radius:10px;background:#1d4ed8;color:#fff;font-size:.69rem}
.msg-table-footer{display:flex;align-items:center;flex-wrap:wrap;gap:5px;margin-top:7px;font-size:.72rem}
.msg-table-footer #msgThreadCount{margin-right:auto}
.msg-table-footer select,.msg-table-footer button{min-height:27px;padding:3px 6px;font-size:.72rem}
.msg-table-footer button:disabled{opacity:.45;cursor:default}
@media(max-width:1120px){.msg-module-body .msg-inbox-table-layout{grid-template-columns:minmax(0,1fr)}}
@media(max-width:650px){.msg-conversation-table th:nth-child(3),.msg-conversation-table td:nth-child(3){display:none}.msg-conversation-table th:nth-child(1){width:32%}.msg-conversation-table th:nth-child(2){width:53%}.msg-conversation-table th:nth-child(4){width:15%}}

/* Cycle 98G — readable full-width Messages workspace, without nested scrollbars.
   Compact means efficient spacing, not constrained content or clipped columns. */
.msg-module-layout{grid-template-columns:156px minmax(0,1fr);align-items:start}
.msg-module-body{width:100%;min-width:0}
.msg-module-body .msg-inbox-table-layout,
.msg-module-body .msg-workspace{display:grid;grid-template-columns:minmax(0,1fr);gap:10px;min-height:0;max-width:none;width:100%}
.msg-module-body .msg-inbox-table-layout>.msg-pane,
.msg-module-body .msg-workspace>.msg-pane{min-width:0;width:100%;max-width:none;box-sizing:border-box}
.msg-table-scroll{overflow:visible;max-width:none;width:100%;border-radius:7px}
.msg-conversation-table{width:100%;table-layout:auto}
.msg-conversation-table th:nth-child(1){width:24%}
.msg-conversation-table th:nth-child(2){width:48%}
.msg-conversation-table th:nth-child(3){width:20%}
.msg-conversation-table th:nth-child(4){width:8%}
.msg-conversation-table td{overflow:visible;word-break:normal}
.msg-conversation-table .msg-cell-select{overflow-wrap:anywhere;white-space:normal;overflow:visible;text-overflow:clip;min-height:34px}
.msg-conversation-table .msg-table-subject,
.msg-conversation-table .msg-table-preview{white-space:normal;overflow:visible;text-overflow:clip;overflow-wrap:anywhere}
.msg-conversation-table .msg-table-date{white-space:normal;overflow-wrap:anywhere}
.msg-module-body .msg-history{max-height:none!important;min-height:90px;overflow:visible!important;height:auto;display:flex;flex-direction:column}
.msg-module-body .msg-bubble{max-width:min(90%,850px)}
.msg-module-body #msgOwnerReview .msg-workspace{grid-template-columns:minmax(0,1fr)}
.msg-module-body #msgOwnerReview .msg-workspace>div{min-width:0}
.msg-module-body #msgReviewThreads{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:6px}
.msg-module-body #msgReviewThreads .msg-review-item{margin:0}
@media(max-width:850px){.msg-module-layout{grid-template-columns:132px minmax(0,1fr)}.msg-module-body .msg-inbox-table-layout,.msg-module-body .msg-workspace{grid-template-columns:minmax(0,1fr)}}
@media(max-width:650px){.msg-module-layout{grid-template-columns:minmax(0,1fr)}.msg-conversation-table th:nth-child(3),.msg-conversation-table td:nth-child(3){display:table-cell}.msg-conversation-table th:nth-child(1){width:25%}.msg-conversation-table th:nth-child(2){width:45%}.msg-conversation-table th:nth-child(3){width:22%}.msg-conversation-table th:nth-child(4){width:8%}.msg-conversation-table th,.msg-conversation-table .msg-cell-select{font-size:.7rem!important;padding:5px 3px!important}}
.msg-conversation-table .msg-date-sort{display:inline-flex;align-items:center;gap:4px;padding:2px 3px;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;text-align:left}.msg-conversation-table .msg-date-sort:hover{text-decoration:underline}.msg-conversation-table .msg-date-sort:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
.msg-module-body .msg-review-table th:nth-child(1){width:34%}.msg-module-body .msg-review-table th:nth-child(2){width:38%}.msg-module-body .msg-review-table th:nth-child(3){width:20%}.msg-module-body .msg-review-table th:nth-child(4){width:8%}.msg-review-detail{margin-top:12px;padding-top:10px;border-top:1px solid var(--brite-border,#cbd5e1)}.msg-review-detail h3{margin:0 0 8px}.msg-review-table td:last-child{text-align:center;padding:7px 4px!important}.msg-review-table tr[aria-selected="true"]{background:rgba(37,99,235,.09)}
</style>
<section class="card msg-module" data-brite-pills="off" style="display:grid;gap:8px">
 <div><h1 style="margin:0 0 4px">Messages</h1><p class="muted" style="margin:0;font-size:.85rem">Private conversations within your organization. Clients and contractors can contact the tenant owner by default; direct client–contractor messaging becomes available after a quote request or scheduled assignment.</p></div>
 <p id="msgStatus" role="status" aria-live="polite"></p>
<div class="msg-module-layout">
 <nav class="msg-module-nav" role="tablist" aria-label="Messages sections" aria-orientation="vertical">
 <button type="button" class="msg-tab" id="msgTabInbox" role="tab" aria-controls="msgPanelInbox" aria-selected="true" tabindex="0">My messages</button>
 <?php if($tenant['role']==='owner'): ?>
 <button type="button" class="msg-tab" id="msgTabReview" role="tab" aria-controls="msgOwnerReview" aria-selected="false" tabindex="-1">Tenant review</button>
 <?php endif; ?>
 <button type="button" class="msg-tab" id="msgTabPreferences" role="tab" aria-controls="msgPreferences" aria-selected="false" tabindex="-1">Preferences</button>
 </nav>
 <div class="msg-module-body">
 <div id="msgPanelInbox" class="msg-workspace msg-tabpanel msg-inbox-table-layout" role="tabpanel" aria-labelledby="msgTabInbox">
  <aside class="msg-pane" aria-label="Conversations">
   <div class="msg-toolbar"><h2>Inbox</h2><button id="msgRefresh" type="button" class="msg-secondary" title="Refresh conversations">↻ Refresh</button></div>
   <button id="msgNew" type="button" class="msg-primary" style="width:100%;margin-bottom:8px">+ New conversation</button><label for="msgThreadSearch" class="brite-sr-only">Search conversations</label><input id="msgThreadSearch" type="search" placeholder="Find a conversation…" autocomplete="off">
   <div class="msg-table-tools"><label for="msgUnreadFilter">Show</label><select id="msgUnreadFilter"><option value="all">All conversations</option><option value="unread">Unread only</option><option value="read">Read only</option></select><label for="msgThreadSort">Sort</label><select id="msgThreadSort"><option value="recent">Date/time: newest first</option><option value="oldest">Date/time: oldest first</option><option value="unread">Unread first</option><option value="participant">Participant A–Z</option><option value="subject">Subject A–Z</option></select></div>
   <div class="msg-table-scroll"><table class="msg-conversation-table" aria-label="Conversations"><thead><tr><th scope="col">Participant</th><th scope="col">Subject / latest message</th><th scope="col"><button type="button" id="msgSortDate" class="msg-date-sort" title="Sort by date and time" aria-label="Sort by date and time, newest first">Updated <span id="msgSortDateArrow" aria-hidden="true">↓</span></button></th><th scope="col">Unread</th></tr></thead><tbody id="msgThreads"></tbody></table></div>
   <div class="msg-table-footer"><span id="msgThreadCount" role="status" aria-live="polite"></span><label for="msgPageSize">Rows <select id="msgPageSize"><option>10</option><option selected>25</option><option>50</option><option>100</option></select></label><button type="button" id="msgPagePrev">Previous</button><span id="msgPageInfo"></span><button type="button" id="msgPageNext">Next</button></div>
  </aside>
  <div class="msg-pane">
   <div id="msgCompose" hidden>
    <div class="msg-toolbar"><h2>New conversation</h2><button id="msgCancelCompose" type="button" class="msg-secondary">Cancel</button></div><p class="muted">Only eligible recipients appear below. Client–contractor messaging requires a previous quote request or appointment assignment.</p>
    <form id="msgCreate" class="msg-form">
     <label>Send to <select name="recipient_id" id="msgRecipients" required></select></label>
     <label>Subject <input name="subject" maxlength="180" required></label>
     <label>Message <textarea name="body" rows="5" maxlength="5000" required></textarea></label>
     <div class="msg-actions"><button type="submit" class="msg-primary">Send message</button></div>
    </form>
   </div>
   <div id="msgDetail">
    <h2 id="msgHeading">Select a conversation</h2>
    <div id="msgMessages" class="msg-history" aria-live="polite"><p>Select a conversation from your inbox.</p></div>
    <form id="msgReply" class="msg-form msg-inline-reply" hidden style="margin-top:14px">
     <label for="msgReplyBody">Write a reply</label><textarea id="msgReplyBody" name="body" maxlength="5000" rows="4" required placeholder="Write your message here. Ctrl+Enter to send."></textarea>
     <div class="msg-actions"><span class="msg-help">Ctrl/⌘ + Enter to send</span><button type="submit" class="msg-primary">Send reply</button></div>
    </form>
   </div>
  </div>
 </div>
 <?php if($tenant['role']==='owner'): ?>
 <section class="msg-pane msg-tabpanel" id="msgOwnerReview" role="tabpanel" aria-labelledby="msgTabReview" aria-label="Tenant conversation review" hidden>
  <h2>Tenant conversation review</h2>
  <p class="muted">Owner-only, read-only review of conversations in this tenant. Reviews are recorded in an internal audit log and do not mark participants' messages as read.</p>
  <div class="msg-toolbar"><label for="msgReviewSearch">Search conversations</label><button type="button" id="msgReviewRefresh" class="msg-secondary">↻ Refresh</button></div>
  <input id="msgReviewSearch" type="search" placeholder="Find participants or subject…" autocomplete="off" style="width:100%;box-sizing:border-box">
  <div class="msg-table-tools"><label for="msgReviewSort">Sort</label><select id="msgReviewSort"><option value="recent">Date/time: newest first</option><option value="oldest">Date/time: oldest first</option><option value="participants">Participant A–Z</option><option value="subject">Subject A–Z</option><option value="count">Most messages first</option></select></div>
  <div class="msg-table-scroll"><table class="msg-conversation-table msg-review-table" aria-label="Tenant conversations"><thead><tr><th scope="col">Participants</th><th scope="col">Subject</th><th scope="col"><button type="button" id="msgReviewSortDate" class="msg-date-sort" aria-label="Sort by date and time, oldest first">Updated <span id="msgReviewSortArrow" aria-hidden="true">↓</span></button></th><th scope="col">Messages</th></tr></thead><tbody id="msgReviewThreads"></tbody></table></div>
  <div class="msg-table-footer"><span id="msgReviewCount" role="status" aria-live="polite"></span><label for="msgReviewPageSize">Rows <select id="msgReviewPageSize"><option>10</option><option selected>25</option><option>50</option><option>100</option></select></label><button type="button" id="msgReviewPrev">Previous</button><span id="msgReviewPageInfo"></span><button type="button" id="msgReviewNext">Next</button></div>
  <div class="msg-review-detail"><h3 id="msgReviewHeading">Select a conversation</h3><div id="msgReviewMessages" class="msg-history"><p>Select a conversation to inspect the exchange.</p></div></div>
 </section>

 <script defer src="<?=htmlspecialchars(app_url('/modules/messaging/owner-review.js?v=98l'),ENT_QUOTES,'UTF-8')?>"></script>
 <?php endif; ?>
 <section class="msg-preferences msg-tabpanel msg-pane" id="msgPreferences" role="tabpanel" aria-labelledby="msgTabPreferences" hidden>
  <h2>Notification preferences</h2>
  <label><input type="checkbox" id="msgInApp"> Enable in-app message notifications</label>
  <p class="muted">This setting is saved for your account and tenant. Email and SMS delivery are not enabled in this version.</p>
 </section>
 </div>
</div>
</section>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const tabs=[...document.querySelectorAll('.msg-module-nav [role="tab"]')];
 const panels=tabs.map(tab=>document.getElementById(tab.getAttribute('aria-controls')));
 function activate(index,focus){
  tabs.forEach((tab,i)=>{tab.setAttribute('aria-selected',i===index?'true':'false');tab.tabIndex=i===index?0:-1;panels[i].hidden=i!==index;});
  if(focus)tabs[index].focus();
 }
 tabs.forEach((tab,index)=>{
  tab.addEventListener('click',()=>activate(index,false));
  tab.addEventListener('keydown',event=>{
   let next;
   if(event.key==='ArrowDown'||event.key==='ArrowRight')next=(index+1)%tabs.length;
   else if(event.key==='ArrowUp'||event.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;
   else if(event.key==='Home')next=0;
   else if(event.key==='End')next=tabs.length-1;
   else return;
   event.preventDefault();activate(next,true);
  });
 });
 activate(0,false);
});
</script>
<script defer src="<?=htmlspecialchars(app_url('/modules/messaging/messaging.js?v=98k'),ENT_QUOTES,'UTF-8')?>"></script>
