<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$management=(string)file_get_contents($root.'/enrollment.php');
$activation=(string)file_get_contents($root.'/enrollment-activate.php');
$checks=[
 'Invitation revocation action'=>str_contains($management,"$"."action === 'revoke'"),
 'Revocation tenant scoped'=>str_contains($management,'WHERE tenant_id=? AND application_id=? AND accepted_at IS NULL AND revoked_at IS NULL'),
 'Revocation audited'=>str_contains($management,"'enrollment.revoke'"),
 'Revocation owner/admin only'=>str_contains($management,"$"."action === 'revoke' && $"."canReview"),
 'Revocation form protected'=>str_contains($management,'name="action" value="revoke"')&&str_contains($management,'name="_csrf"'),
 'Intake serialized with tenant lock'=>str_contains($management,"SELECT id FROM tenants WHERE id=? FOR UPDATE"),
 'Intake transaction committed'=>str_contains($management,'$db->commit()'),
 'Activation serialized with tenant lock'=>str_contains($activation,"SELECT id FROM tenants WHERE id=? FOR UPDATE"),
 'Activation token single use'=>str_contains($activation,'i.accepted_at IS NULL')&&str_contains($activation,'accepted_at=UTC_TIMESTAMP()'),
 'Activation responses not cached'=>str_contains($activation,"Cache-Control: no-store"),
 'Activation referrer blocked'=>str_contains($activation,"Referrer-Policy: no-referrer"),
];
$failed=0;
foreach($checks as $label=>$ok){echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;}
exit($failed?1:0);
