<?php
declare(strict_types=1);
/**
 * Cycle 89 Sprint 2 source-level regression checks.
 * Run: php tests/cycle89_regression_test.php
 * No database connection or application bootstrap required.
 */
$root=dirname(__DIR__);
$crm=file_get_contents($root.'/modules/crm/customer360.php');
$api=file_get_contents($root.'/api/user_notifications.php');
$ui=file_get_contents($root.'/assets/js/owner-notifications.js');
$checks=[
 'CRM tenant clock'=>str_contains($crm,'TenantClock::now($db,$tid)'),
 'CRM upcoming bound clock'=>str_contains($crm,'starts_at >= ?') && str_contains($crm,'[$tenantNow,$tid,$customerId]'),
 'CRM timeline filter'=>str_contains($crm,'crmTimelineType') && str_contains($crm,'data-event-type'),
 'Notification tenant isolation'=>str_contains($api,'WHERE n.tenant_id = ?') && str_contains($api,'AND n.user_id = ?'),
 'Notification unread filter'=>str_contains($api,'AND n.read_at IS NULL'),
 'Notification parameterized type'=>str_contains($api,'AND n.type = ?'),
 'Notification invalid query rejection'=>str_contains($api,"Invalid notification filter"),
 'Owner notification controls'=>str_contains($ui,'Unread only') && str_contains($ui,'renderNotifications'),
 'Owner notification safe rendering'=>str_contains($ui,'heading.textContent = notification.title') && str_contains($ui,'body.textContent = notification.body'),
];
$fail=0;
foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;if(!$ok)$fail++;}
exit($fail?1:0);
