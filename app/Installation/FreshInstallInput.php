<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;

/** Pure preflight: reject configuration/owner inputs before irreversible DDL. */
final class FreshInstallInput
{
    public static function validate(string $root, array $config, array $owner, string $database): void
    {
        $root = rtrim($root, '/');
        if (!is_file($root . '/.env.example') || is_link($root . '/.env.example')) {
            throw new DomainException('Configuration template missing or unsafe');
        }
        $template = file_get_contents($root . '/.env.example');
        if (!is_string($template)) throw new DomainException('Configuration template unreadable');
        foreach (['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'] as $key) {
            if (!array_key_exists($key, $config) || !is_string($config[$key]) ||
                ($key !== 'DB_PASS' && $config[$key] === '') ||
                strlen($config[$key]) > 2048 ||
                (bool)preg_match('/[[:cntrl:][:space:]#"\\x27\\\\]/', $config[$key]) ||
                !preg_match('/^' . preg_quote($key, '/') . '=/m', $template)) {
                throw new DomainException('Invalid configuration field: ' . $key);
            }
        }
        if (filter_var($config['APP_URL'], FILTER_VALIDATE_URL) === false ||
            !in_array(strtolower((string)parse_url($config['APP_URL'], PHP_URL_SCHEME)), ['http','https'], true) ||
            !preg_match('/^[A-Za-z0-9.-]+$/D', $config['DB_HOST']) ||
            !preg_match('/^[A-Za-z0-9_]+$/D', $config['DB_NAME']) ||
            !preg_match('/^[A-Za-z0-9_.-]+$/D', $config['DB_USER']) ||
            !ctype_digit($config['DB_PORT']) ||
            (int)$config['DB_PORT'] < 1 || (int)$config['DB_PORT'] > 65535 ||
            $config['DB_NAME'] !== $database) {
            throw new DomainException('Invalid database configuration');
        }
        foreach (['tenant_name','tenant_slug','owner_email','owner_first_name','owner_last_name','owner_password'] as $key) {
            if (!isset($owner[$key]) || !is_string($owner[$key]) || trim($owner[$key]) === '') {
                throw new DomainException('Missing owner field: ' . $key);
            }
        }
        $timezone = $owner['timezone'] ?? 'America/New_York';
        if (strlen(trim($owner['tenant_name'])) > 150 ||
            strlen(trim($owner['tenant_slug'])) > 150 ||
            !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', trim($owner['tenant_slug'])) ||
            filter_var(trim($owner['owner_email']), FILTER_VALIDATE_EMAIL) === false ||
            strlen(trim($owner['owner_email'])) > 190 ||
            strlen(trim($owner['owner_first_name'])) > 100 ||
            strlen(trim($owner['owner_last_name'])) > 100 ||
            strlen($owner['owner_password']) < 12 || strlen($owner['owner_password']) > 1024 ||
            !is_string($timezone) || !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new DomainException('Invalid initial owner details');
        }
    }
}
