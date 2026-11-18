<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Finalization gate for a fresh CLI installation.
 * Requires the caller to hold the installation mutex and to have completed
 * migrations, owner provisioning, and configuration creation.
 */
final class InstallationCompletion
{
    public static function finalize(string $root, PDO $pdo, bool $confirmed, bool $alreadyLocked): void
    {
        if (PHP_SAPI !== 'cli' || !$confirmed || !$alreadyLocked) {
            throw new DomainException('CLI confirmation and installation mutex required');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new DomainException('MySQL/MariaDB required');
        }
        $root = rtrim($root, '/');
        if (!is_dir($root) || is_link($root) || !is_file($root . '/.env') ||
            is_link($root . '/.env') || file_exists($root . '/.brite-migration-recovery-required') ||
            is_link($root . '/.brite-migration-recovery-required')) {
            throw new DomainException('Installation configuration or migration recovery state invalid');
        }
        $marker = $root . '/.brite-installed.lock';
        if (file_exists($marker) || is_link($marker)) {
            throw new DomainException('Installation already finalized');
        }
        $inventory = MigrationInventory::list($root);
        if (!$inventory) throw new DomainException('Migration inventory is empty');
        $plan = MigrationPlan::build($root, $pdo);
        if ($plan['pending'] || $plan['applied_count'] !== count($inventory)) {
            throw new DomainException('Database migrations are incomplete');
        }
        $q = $pdo->query("SELECT COUNT(*) FROM tenant_memberships m
            INNER JOIN tenants t ON t.id = m.tenant_id
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.role = 'owner' AND m.status = 'active'");
        if ((int)$q->fetchColumn() < 1) {
            throw new DomainException('Active owner membership required');
        }
        $handle = @fopen($marker, 'x');
        if (!is_resource($handle)) {
            throw new RuntimeException('Could not create installation completion marker');
        }
        try {
            @chmod($marker, 0600);
            if (fwrite($handle, "installed\n") !== 10 || !fflush($handle)) {
                throw new RuntimeException('Could not write installation completion marker');
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($marker);
            throw $e;
        }
        fclose($handle);
    }
}
