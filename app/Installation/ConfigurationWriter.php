<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use RuntimeException;

/**
 * Atomic, create-only local .env writer. Never log supplied values.
 * Caller must separately authenticate setup and hold the installation mutex.
 */
final class ConfigurationWriter
{
    private const REQUIRED = ['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'];

    public static function write(string $root, array $values): void
    {
        $root = rtrim($root, '/');
        if (!is_dir($root) || is_link($root) || !is_file($root . '/.env.example')) {
            throw new RuntimeException('Invalid installation root');
        }
        if (file_exists($root . '/.env') || is_link($root . '/.env') ||
            file_exists($root . '/.brite-installed.lock')) {
            throw new RuntimeException('Configuration already exists');
        }
        foreach (self::REQUIRED as $key) {
            if (!isset($values[$key]) || !is_string($values[$key]) ||
                $values[$key] === '' || strlen($values[$key]) > 2048) {
                throw new DomainException('Invalid configuration field: ' . $key);
            }
        }
        if (!filter_var($values['APP_URL'], FILTER_VALIDATE_URL) ||
            !in_array(strtolower((string)parse_url($values['APP_URL'], PHP_URL_SCHEME)), ['https','http'], true) ||
            !ctype_digit($values['DB_PORT']) || (int)$values['DB_PORT'] < 1 ||
            (int)$values['DB_PORT'] > 65535) {
            throw new DomainException('Invalid application URL or database port');
        }
        $template = file_get_contents($root . '/.env.example');
        if (!is_string($template)) throw new RuntimeException('Configuration template unreadable');
        foreach ($values as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) ||
                !is_string($value) || strlen($value) > 2048 ||
                preg_match('/[\r\n\x00-\x1f\x7f]/', $value)) {
                throw new DomainException('Invalid configuration value');
            }
            if (!preg_match('/^' . preg_quote($key, '/') . '=/m', $template)) {
                throw new DomainException('Unsupported configuration key: ' . $key);
            }
            // Encode values safely for the existing line-based dotenv parser.
            if (preg_match('/[\s#"\x27\\\\]/', $value)) {
                throw new DomainException('Configuration values must not contain whitespace or quotes');
            }
            $template = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m',
                $key . '=' . str_replace('$', '\\$', $value), $template, 1);
        }
        $temp = $root . '/.env.' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temp, 'x');
        if (!is_resource($handle)) throw new RuntimeException('Unable to prepare configuration');
        try {
            @chmod($temp, 0600);
            $length = strlen($template);
            if (fwrite($handle, $template) !== $length || !fflush($handle)) {
                throw new RuntimeException('Configuration write failed');
            }
            fclose($handle);
            $handle = null;
            // Hard link is create-only: it fails if .env appeared concurrently.
            if (!@link($temp, $root . '/.env')) {
                throw new RuntimeException('Configuration already exists or cannot be created');
            }
        } finally {
            if (is_resource($handle)) fclose($handle);
            @unlink($temp);
        }
    }
}
