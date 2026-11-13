<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

/** Read-only installation diagnostics; never reveals environment secrets. */
final class RequirementsChecker
{
    public static function inspect(string $root): array
    {
        $checks = [];
        $checks['php_version'] = ['ok' => PHP_VERSION_ID >= 80200, 'detail' => 'PHP 8.2 or newer'];
        foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo'] as $extension) {
            $checks['extension_' . $extension] = ['ok' => extension_loaded($extension), 'detail' => $extension . ' extension'];
        }
        $checks['migrations_directory'] = ['ok' => is_dir($root . '/migrations'), 'detail' => 'SQL migration directory exists'];
        $checks['config_template'] = ['ok' => is_file($root . '/.env.example'), 'detail' => 'Configuration template exists'];
        $checks['new_install_configuration'] = ['ok' => !file_exists($root . '/.env') && !is_link($root . '/.env'), 'detail' => 'New browser installation requires no existing .env file'];
        $checks['env_not_web_root'] = ['ok' => is_file($root . '/.htaccess') || !is_file($root . '/.env'), 'detail' => 'Review web-server protection of .env (Apache .htaccess presence only)'];
        return $checks;
    }
}
