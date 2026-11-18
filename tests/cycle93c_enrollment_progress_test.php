<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=(string)file_get_contents($root.'/enrollment.php');
$activate=(string)file_get_contents($root.'/enrollment-activate.php');
$schema=(string)file_get_contents($root.'/migrations/040_enrollment_invitations.sql');
$checks=[
 'Enrollment requires both migrations'=>str_contains($page,"SELECT id FROM enrollment_invitations LIMIT 0"),
 'Tenant-filtered invitation progress'=>str_contains($page,"i.application_id=a.id AND i.tenant_id=a.tenant_id"),
 'Invitation progress states'=>array_reduce(['activated','revoked','expired','invited'],fn($ok,$v)=>$ok&&str_contains($page,"'".$v."'"),true),
 'Submission notifications tenant scoped'=>str_contains($page,"m.tenant_id=? AND m.role IN ('owner','admin')"),
 'Review notification recorded'=>str_contains($page,"'enrollment.reviewed'"),
 'No invitation token in notification'=>!str_contains($page,"$"."rawToken.' submitted")&&!str_contains($page,'$invitationUrl,$tenantId'),
 'Activation token hash stored'=>str_contains($schema,'token_hash CHAR(64)'),
 'Activation consumes invitation'=>str_contains($activate,'accepted_at=UTC_TIMESTAMP()'),
 'Activation creates membership'=>str_contains($activate,'INSERT INTO tenant_memberships'),
 'Activation uses transaction'=>str_contains($activate,'beginTransaction()')&&str_contains($activate,'rollBack()'),
];
$failed=0;
foreach($checks as $name=>$ok){echo ($ok?'PASS ':'FAIL ').$name.PHP_EOL;if(!$ok)$failed++;}
exit($failed?1:0);
