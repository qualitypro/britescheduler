<?php
// Included from the CRM module list views.
$from=$crmListTotal===0?0:(($crmListPage-1)*$crmListSize+1);
$to=min($crmListTotal,$crmListPage*$crmListSize);
?>
<nav aria-label="CRM list pages" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:12px">
 <span class="crm-muted"><?= (int)$from ?>–<?= (int)$to ?> of <?= (int)$crmListTotal ?></span>
 <?php if($crmListPage>1): ?><a href="<?=$esc($crmListUrl($crmListPage-1))?>">Previous</a><?php endif; ?>
 <span>Page <?= (int)$crmListPage ?> of <?= (int)$crmListPages ?></span>
 <?php if($crmListPage<$crmListPages): ?><a href="<?=$esc($crmListUrl($crmListPage+1))?>">Next</a><?php endif; ?>
</nav>
