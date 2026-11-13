<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

/** Installer state gate. Never treat a missing .env as proof installation is authorized. */
final class InstallerGate
{
    public static function inspect(string $root): array
    {
        $root = rtrim($root, '/');
        $lock = is_file($root . '/.brite-installed.lock');
        $env = is_file($root . '/.env');
        $token = getenv('BRITE_INSTALL_SETUP_TOKEN') ?: '';
        $configured = is_string($token) && strlen($token) >= 32 &&
            strlen($token) <= 256 && !preg_match('/\s/', $token);
        return [
            'installed_lock' => $lock,
            'existing_configuration' => $env,
            'setup_token_configured' => (bool)$configured,
            // This is only a prerequisite; future installer must authenticate
            // the token, enforce CSRF, inspect DB state and lock writes.
            'setup_may_begin' => !$lock && !$env && (bool)$configured,
        ];
    }
}
