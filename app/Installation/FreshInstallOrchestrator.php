<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;

/**
 * One-shot CLI orchestration for a NEW, EMPTY MySQL/MariaDB database.
 * Database creation and interactive credential collection are deliberately out
 * of scope. Never invoke against an existing tenant database.
 */
final class FreshInstallOrchestrator
{
    public static function install(string $root, PDO $pdo, array $configuration, array $owner, bool $confirmed): array
    {
        if (PHP_SAPI !== 'cli' || !$confirmed) {
            throw new DomainException('Explicit CLI confirmation required');
        }
        $root = rtrim($root, '/');
        if (!is_dir($root) || is_link($root) ||
            file_exists($root . '/.env') || is_link($root . '/.env') ||
            file_exists($root . '/.brite-installed.lock') || is_link($root . '/.brite-installed.lock') ||
            file_exists($root . '/.brite-migration-recovery-required') ||
            is_link($root . '/.brite-migration-recovery-required')) {
            throw new DomainException('Fresh installer requires an unconfigured, unrecovered root');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new DomainException('MySQL/MariaDB required');
        }
        $mutex = new InstallationMutex();
        $mutex->acquire($root);
        try {
            // Recheck under the lock; never upgrade or reuse a partially installed schema.
            if (file_exists($root . '/.env') || is_link($root . '/.env') ||
                file_exists($root . '/.brite-installed.lock') || is_link($root . '/.brite-installed.lock') ||
                file_exists($root . '/.brite-migration-recovery-required') ||
                is_link($root . '/.brite-migration-recovery-required')) {
                throw new DomainException('Installer state changed while acquiring mutex');
            }
            $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            if ($tables !== 0) {
                throw new DomainException('Refusing to install into nonempty database');
            }
            // Validate owner fields before irreversible DDL by using the same
            // syntactic constraints as provisioning (without storing secrets).
            foreach (['tenant_name','tenant_slug','owner_email','owner_first_name','owner_last_name','owner_password'] as $key) {
                if (!isset($owner[$key]) || !is_string($owner[$key]) || trim($owner[$key]) === '') {
                    throw new DomainException('Missing owner field: ' . $key);
                }
            }
            if (!filter_var($owner['owner_email'], FILTER_VALIDATE_EMAIL) ||
                !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $owner['tenant_slug']) ||
                strlen($owner['owner_password']) < 12 ||
                strlen($owner['owner_password']) > 1024) {
                throw new DomainException('Invalid initial owner details');
            }
            // Validate configuration without writing it before schema operations.
            foreach (['APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'] as $key) {
                if (!isset($configuration[$key]) || !is_string($configuration[$key])) {
                    throw new DomainException('Missing configuration field: ' . $key);
                }
            }
            if (!filter_var($configuration['APP_URL'], FILTER_VALIDATE_URL) ||
                !in_array(parse_url($configuration['APP_URL'], PHP_URL_SCHEME), ['http','https'], true) ||
                !preg_match('/^[A-Za-z0-9.-]+$/D', $configuration['DB_HOST']) ||
                !preg_match('/^[A-Za-z0-9_]+$/D', $configuration['DB_NAME']) ||
                !preg_match('/^[A-Za-z0-9_.-]+$/D', $configuration['DB_USER']) ||
                !ctype_digit($configuration['DB_PORT']) ||
                (int)$configuration['DB_PORT'] < 1 || (int)$configuration['DB_PORT'] > 65535) {
                throw new DomainException('Invalid database configuration');
            }
            $actualDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($configuration['DB_NAME'] !== $actualDatabase) {
                throw new DomainException('Configuration database does not match connected database');
            }
            $migrations = MigrationRunner::run($root, $pdo, true, true);
            $created = InitialOwnerProvisioner::provision($pdo, $owner, true, true);
            ConfigurationWriter::write($root, $configuration);
            InstallationCompletion::finalize($root, $pdo, true, true);
            return ['migrations_applied' => count($migrations), 'tenant_id' => $created['tenant_id'], 'owner_user_id' => $created['owner_user_id']];
        } finally {
            $mutex->release();
        }
    }
}
