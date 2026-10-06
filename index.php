<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (!Auth::user()) {
    header('Location: ' . app_url('/sign-in.v2.php'));
    exit;
}

header('Location: ' . Auth::homeUrl());
exit;
