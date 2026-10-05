<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}
load_env($root . '/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(getenv('SESSION_NAME') ?: 'britescheduler');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Csrf.php';

/**
 * Build an absolute URL inside the BriteScheduler installation.
 *
 * APP_URL example:
 * http://localhost/dashboard
 */
function app_url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost/dashboard', '/');

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}
