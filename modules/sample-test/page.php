<?php
// Loaded only through module.php after authenticated tenant/module checks.
if (!defined('BRITE_MODULE_DISPATCH')) { http_response_code(403); exit; }
$displayName=trim((string)$user['first_name'].' '.(string)$user['last_name']);
?>
<section class="card" style="max-width:850px;margin:auto">
<h1>Sample Test Module</h1>
<p>This page is supplied by an installed module, not the core application. Your current role has been explicitly granted access for this tenant.</p>
<div class="card" role="status">
<h2>Welcome, <?=htmlspecialchars($displayName,ENT_QUOTES,'UTF-8')?>!</h2>
<p>You are signed in as <strong><?=htmlspecialchars((string)$tenant['role'],ENT_QUOTES,'UTF-8')?></strong> for <strong><?=htmlspecialchars((string)$tenant['name'],ENT_QUOTES,'UTF-8')?></strong>.</p>
<?php if(in_array($tenant['role'],['owner','admin'],true)): ?>
<p>Administrator message: You can enable or disable this module for your tenant in Business Settings.</p>
<?php elseif($tenant['role']==='contractor'): ?>
<p>Contractor message: Your module workspace is ready for future contractor tools.</p>
<?php elseif($tenant['role']==='client'): ?>
<p>Client message: Your personalized module experience is active.</p>
<?php else: ?>
<p>Team member message: This module is available to your account.</p>
<?php endif; ?>
</div>
<p class="muted">Module: sample-test · Version 1.1.0 · Role-controlled demonstration</p>
</section>
