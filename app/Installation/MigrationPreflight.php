<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

require_once __DIR__ . '/SqlStatements.php';

use DomainException;

/**
 * Migration execution safety gate. No database writes.
 * Rejects unsupported SQL constructs and prevents accidental execution
 * of a multi-statement file as a single PDO statement.
 */
final class MigrationPreflight
{
    public static function inspect(string $root, array $pending): array
    {
        $results = [];
        foreach ($pending as $migration) {
            $version = (string)($migration['version'] ?? '');
            $checksum = (string)($migration['sha256'] ?? '');
            if (!preg_match('/^[0-9]{3}_[a-zA-Z0-9_-]+$/D', $version) ||
                !preg_match('/^[a-f0-9]{64}$/D', $checksum)) {
                throw new DomainException('Invalid migration inventory entry');
            }
            $path = $root . '/migrations/' . $version . '.sql';
            if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $checksum) {
                throw new DomainException('Migration file changed after inventory was generated');
            }
            $sql = file_get_contents($path);
            if (!is_string($sql) || trim($sql) === '') {
                throw new DomainException('Empty migration file');
            }
            if (preg_match('/\b(?:DELIMITER|SOURCE|LOAD\s+DATA|INTO\s+OUTFILE|INTO\s+DUMPFILE)\b/i', $sql)) {
                throw new DomainException('Migration requires manual SQL review: ' . $version);
            }
            $results[] = ['version' => $version, 'sha256' => $checksum, 'bytes' => strlen($sql)];
        }
        return $results;
    }
}            foreach (SqlStatements::split($sql) as $statement) {
                if (preg_match('/^(?:DELIMITER|SOURCE|LOAD\s+DATA)\b/i', $statement) ||
                    preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i', $statement)) {
                    throw new DomainException('Migration requires manual SQL review: ' . $version);
                }
            }
            $results[] = ['version' => $version, 'sha256' => $checksum, 'bytes' => strlen($sql)];
        }
        return $results;
    }
}
