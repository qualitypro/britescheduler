<?php
if(!defined('BRITE_MODULE_DISPATCH')){http_response_code(403);exit;}
?>
<section class="card" style="display:grid;gap:16px">
 <div><h1>Messages</h1><p class="muted">Private conversations within your organization. Only conversation participants can read messages.</p></div>
 <p id="msgStatus" role="status" aria-live="polite"></p>
 <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:20px">
  <div>
   <h2>Conversations</h2><button id="msgNew" type="button">New conversation</button>
   <div id="msgThreads" style="display:grid;gap:8px;margin-top:12px"></div>
  </div>
  <div>
   <div id="msgCompose" hidden>
    <h2>New conversation</h2>
    <form id="msgCreate" style="display:grid;gap:10px">
     <label>Recipient <select name="recipient_id" id="msgRecipients" required></select></label>
     <label>Subject <input name="subject" maxlength="180" required></label>
     <label>Message <textarea name="body" rows="5" maxlength="5000" required></textarea></label>
     <button type="submit">Start conversation</button>
    </form>
   </div>
   <div id="msgDetail">
    <h2 id="msgHeading">Select a conversation</h2>
    <div id="msgMessages" aria-live="polite" style="display:grid;gap:10px;max-height:400px;overflow:auto"></div>
    <form id="msgReply" hidden style="display:grid;gap:8px;margin-top:14px">
     <label>Reply <textarea name="body" maxlength="5000" rows="3" required></textarea></label>
     <button type="submit">Send reply</button>
    </form>
   </div>
  </div>
 </div>
 <details><summary>Notification preferences</summary><label style="display:flex;gap:10px;align-items:center;margin-top:12px"><input type="checkbox" id="msgInApp"> Enable in-app message notifications</label><p class="muted">This preference is stored for your account and tenant. Email and SMS delivery are not enabled in this version.</p></details>
</section>
<script defer src="<?=htmlspecialchars(app_url('/modules/messaging/messaging.js'),ENT_QUOTES,'UTF-8')?>"></script>
