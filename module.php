<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/ModuleRegistry.php';
$user=Auth::requireUser();$tenant=Auth::tenant();
$key=(string)($_GET['module']??'');
$module=ModuleRegistry::activeFor($tenant)[$key]??null;
if(!$module){http_response_code(404);$pageTitle='Module unavailable';require __DIR__.'/partials/app_header.php';echo '<section class="card"><h1>Module unavailable</h1><p>This module is not installed, enabled, or available to your role.</p></section>';require __DIR__.'/partials/app_footer.php';exit;}
$pageTitle=$module['name'];
require __DIR__.'/partials/app_header.php';
define('BRITE_MODULE_DISPATCH',true);
require $module['page_path'];
require __DIR__.'/partials/app_footer.php';
