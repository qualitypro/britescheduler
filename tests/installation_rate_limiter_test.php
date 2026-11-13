<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Installation/SetupRateLimiter.php';
use BriteScheduler\Installation\SetupRateLimiter;
$root = sys_get_temp_dir() . '/brite-throttle-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
try {
    for ($i = 0; $i < 5; $i++) {
        if (!SetupRateLimiter::attempt($root, '192.0.2.5', false)) {
            throw new RuntimeException('Throttle blocked too early');
        }
    }
    if (SetupRateLimiter::attempt($root, '192.0.2.5', false)) {
        throw new RuntimeException('Throttle did not block repeated failures');
    }
    if (!SetupRateLimiter::attempt($root, '192.0.2.6', false)) {
        throw new RuntimeException('Independent address was blocked');
    }
    if (SetupRateLimiter::attempt($root, 'not-an-ip', false)) {
        throw new RuntimeException('Invalid address accepted');
    }
    echo "PASS: installer persistent IP rate limit and invalid address rejection\n";
} finally {
    foreach (glob($root . '/.brite-setup-throttle/*') ?: [] as $path) unlink($path);
    @rmdir($root . '/.brite-setup-throttle');
    @rmdir($root);
}
