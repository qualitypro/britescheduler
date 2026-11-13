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
        return self::fromInventory(MigrationInventory::list($root), $status['applied'], !$status['installed']);
    }

    /** Pure history validation, suitable for isolated regression tests. */
    public static function fromInventory(array $inventory, array $appliedVersions, bool $requiresBootstrap = false): array
    {
        $names = array_column($inventory, 'version');
        if (count($names) !== count(array_unique($names)) ||
            array_diff($appliedVersions, $names)) {
            throw new DomainException('Migration history contains duplicate or unknown versions');
        }
        $applied = array_flip($appliedVersions);
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
            'applied_count' => count($appliedVersions),
            'pending' => $pending,
            'requires_bootstrap' => $requiresBootstrap,
        ];
    }
}
