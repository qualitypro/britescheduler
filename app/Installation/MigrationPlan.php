<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;

/** Read-only migration planning with drift and unknown-version refusal. */
final class MigrationPlan
{
    public static function build(string $root, PDO $pdo): array
    {
        $status = InstallationStatus::inspect($root, $pdo);
        if ($status['unknown']) {
            throw new DomainException('Unknown applied migration versions; manual review required');
        }
        $inventory = MigrationInventory::list($root);
        $names = array_column($inventory, 'version');
        if (count($names) !== count(array_unique($names))) {
            throw new DomainException('Duplicate migration versions');
        }
        $applied = array_flip($status['applied']);
        $pending = [];
        $seenPending = false;
        foreach ($inventory as $migration) {
            $isApplied = isset($applied[$migration['version']]);
            if (!$isApplied) {
                $seenPending = true;
                $pending[] = $migration;
            } elseif ($seenPending) {
                throw new DomainException('Migration history has a gap; manual review required');
            }
        }
        return [
            'applied_count' => count($status['applied']),
            'pending' => $pending,
            'requires_bootstrap' => !$status['installed'],
        ];
    }
}
