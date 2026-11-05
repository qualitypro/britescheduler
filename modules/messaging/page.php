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
</style>
<section class="card" data-brite-pills="off" style="display:grid;gap:10px">
 <div><h1 style="margin:0 0 4px">Messages</h1><p class="muted" style="margin:0;font-size:.85rem">Private conversations within your organization. Clients and contractors can contact the tenant owner by default; direct client–contractor messaging becomes available after a quote request or scheduled assignment.</p></div>
 <p id="msgStatus" role="status" aria-live="polite"></p>
 <div class="msg-workspace">
  <aside class="msg-pane" aria-label="Conversations">
   <div class="msg-toolbar"><h2>Inbox</h2><button id="msgRefresh" type="button" class="msg-secondary" title="Refresh conversations">↻ Refresh</button></div>
   <button id="msgNew" type="button" class="msg-primary" style="width:100%;margin-bottom:8px">+ New conversation</button><label for="msgThreadSearch" class="brite-sr-only">Search conversations</label><input id="msgThreadSearch" type="search" placeholder="Find a conversation…" autocomplete="off">
   <div id="msgThreads" aria-label="Conversation list"></div>
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
 <section class="msg-pane" id="msgOwnerReview" aria-label="Tenant conversation review">
  <h2>Tenant conversation review</h2>
  <p class="muted">Owner-only, read-only review of conversations in this tenant. Reviews are recorded in an internal audit log and do not mark participants' messages as read.</p>
  <label>Find a conversation <input id="msgReviewSearch" type="search" placeholder="Search participants or subject" style="width:100%;box-sizing:border-box"></label>
  <div class="msg-workspace" style="margin-top:12px;min-height:0">
   <div><h3>All tenant conversations</h3><div id="msgReviewThreads"></div></div>
   <div><h3 id="msgReviewHeading">Select a conversation</h3><div id="msgReviewMessages" class="msg-history"><p>Select a thread to inspect the exchange.</p></div></div>
  </div>
 </section>
 <script defer src="<?=htmlspecialchars(app_url('/modules/messaging/owner-review.js?v=75c'),ENT_QUOTES,'UTF-8')?>"></script>
 <?php endif; ?>
 <details><summary>Notification preferences</summary><label style="display:flex;gap:10px;align-items:center;margin-top:12px"><input type="checkbox" id="msgInApp"> Enable in-app message notifications</label><p class="muted">This setting is saved for your account and tenant. Email and SMS delivery are not enabled in this version.</p></details>
</section>
<script defer src="<?=htmlspecialchars(app_url('/modules/messaging/messaging.js?v=75l'),ENT_QUOTES,'UTF-8')?>"></script>
