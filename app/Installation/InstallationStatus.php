<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use PDO;
use PDOException;

/** Read-only database installation status; does not execute SQL migrations. */
final class InstallationStatus
{
    public static function inspect(string $root, PDO $pdo): array
    {
        $inventory = MigrationInventory::list($root);
        $pdo->query('SELECT 1');
        $hasTable = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'")->fetchColumn();
        if (!$hasTable) {
            return ['installed' => false, 'applied' => [], 'pending' => $inventory, 'unknown' => []];
        }
        $applied = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $known = array_column($inventory, 'version');
        $pending = array_values(array_filter($inventory, static fn(array $row): bool => !in_array($row['version'], $applied, true)));
        return [
            'installed' => count($applied) > 0,
            'applied' => $applied,
            'pending' => $pending,
            'unknown' => array_values(array_diff($applied, $known)),
        ];
    }
}
