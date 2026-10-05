<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_method('POST'); verify_csrf();
$_SESSION=[]; session_destroy(); json_response(['ok'=>true]);
