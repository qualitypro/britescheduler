<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/enrollment.php');
$nav=file_get_contents($root.'/partials/app_header.php');
$sql=file_get_contents($root.'/migrations/039_enrollment_management.sql');
$checks=[
 'Enrollment main-menu link'=>str_contains($nav,"app_url('/enrollment.php')"),
 'Enrollment navigation label'=>str_contains($nav,"Enrollment\n</a>"),
 'Tenant-scoped application queue'=>str_contains($page,'FROM enrollment_applications WHERE tenant_id=?'),
 'Tenant-scoped review mutation'=>str_contains($page,"WHERE id=? AND tenant_id=? AND status='pending'"),
 'Review restricted to owner/admin'=>str_contains($page,"$"."canReview = in_array("),
 'Review audit recorded'=>str_contains($page,"'enrollment.review'"),
 'Review audit transaction'=>str_contains($page,'$db->beginTransaction()')&&str_contains($page,'$db->rollBack()'),
 'CSRF protected forms'=>str_contains($page,"hash_equals(csrf_token()"),
 'No automatic membership grant'=>!str_contains($page,'INSERT INTO tenant_memberships'),
 'Migration contains both applicant types'=>str_contains($sql,"ENUM('contractor','client')"),
 'Migration scoped to tenant'=>str_contains($sql,'idx_enrollment_tenant_status'),
];
$failed=0;
foreach($checks as $label=>$ok){echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;if(!$ok)++$failed;}
exit($failed?1:0);
