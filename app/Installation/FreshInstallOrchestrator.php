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
            $actualDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            FreshInstallInput::validate($root, $configuration, $owner, $actualDatabase);
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
