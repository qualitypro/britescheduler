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
@media(max-width:760px){.msg-workspace{grid-template-columns:1fr;min-height:0}.msg-history{max-height:360px}}
</style>
<section class="card" style="display:grid;gap:16px">
 <div><h1>Messages</h1><p class="muted">Your private conversations with clients, contractors and team members. Select a conversation to see the full exchange.</p></div>
 <p id="msgStatus" role="status" aria-live="polite"></p>
 <div class="msg-workspace">
  <aside class="msg-pane" aria-label="Conversations">
   <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap"><h2>Inbox</h2><button id="msgRefresh" type="button">Refresh</button></div>
   <button id="msgNew" type="button" style="width:100%;margin-bottom:12px">+ New conversation</button>
   <div id="msgThreads" aria-label="Conversation list"></div>
  </aside>
  <div class="msg-pane">
   <div id="msgCompose" hidden>
    <h2>New conversation</h2>
    <form id="msgCreate" class="msg-form">
     <label>Send to <select name="recipient_id" id="msgRecipients" required></select></label>
     <label>Subject <input name="subject" maxlength="180" required></label>
     <label>Message <textarea name="body" rows="5" maxlength="5000" required></textarea></label>
     <button type="submit">Send message</button>
    </form>
   </div>
   <div id="msgDetail">
    <h2 id="msgHeading">Select a conversation</h2>
    <div id="msgMessages" class="msg-history" aria-live="polite"><p>Select a conversation from your inbox.</p></div>
    <form id="msgReply" class="msg-form" hidden style="margin-top:14px">
     <label>Reply <textarea name="body" maxlength="5000" rows="3" required placeholder="Type your reply…"></textarea></label>
     <button type="submit">Send reply</button>
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
<script defer src="<?=htmlspecialchars(app_url('/modules/messaging/messaging.js?v=75b'),ENT_QUOTES,'UTF-8')?>"></script>
