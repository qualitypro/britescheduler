<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

final class MigrationInventory
{
    /** Inventory only: no SQL is executed or applied. */
    public static function list(string $root): array
    {
        $files = glob($root . '/migrations/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);
        $result = [];
        foreach ($files as $path) {
            if (!is_file($path) || is_link($path)) {
                continue;
            }
            $result[] = [
                'version' => basename($path, '.sql'),
                'sha256' => hash_file('sha256', $path),
            ];
        }
        return $result;
    }
}
