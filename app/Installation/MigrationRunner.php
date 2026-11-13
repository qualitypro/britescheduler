<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Explicit CLI-only migration executor. Caller supplies already configured PDO.
 * MySQL DDL auto-commits: an interrupted migration requires manual reconciliation.
 */
final class MigrationRunner
{
    public static function run(string $root, PDO $pdo, bool $confirmed): array
    {
        if (PHP_SAPI !== 'cli' || !$confirmed) throw new DomainException('Explicit CLI confirmation required');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new DomainException('Migration runner requires MySQL/MariaDB');
        }
        $root = rtrim($root, '/');
        $mutex = new InstallationMutex();
        $mutex->acquire($root);
        try {
            $failure = $root . '/.brite-migration-recovery-required';
            if (file_exists($failure) || is_link($failure)) {
                throw new RuntimeException('Previous migration may be incomplete; manual recovery required');
            }
            $plan = MigrationPlan::build($root, $pdo);
            MigrationPreflight::inspect($root, $plan['pending']);
            $completed = [];
            foreach ($plan['pending'] as $migration) {
                $version = $migration['version'];
                $path = $root . '/migrations/' . $version . '.sql';
                if (is_link($path) || !is_file($path) ||
                    !hash_equals($migration['sha256'], hash_file('sha256', $path))) {
                    throw new DomainException('Migration changed after preflight');
                }
                $sql = file_get_contents($path);
                if (!is_string($sql)) throw new RuntimeException('Migration unreadable');
                $statements = SqlStatements::split($sql);
                // Create the recovery marker BEFORE any DDL. Never automatically clear
                // it after an exception, because MySQL DDL cannot be rolled back.
                $marker = @fopen($failure, 'x');
                if (!is_resource($marker)) throw new RuntimeException('Unable to create migration recovery marker');
                @chmod($failure, 0600);
                fwrite($marker, $version . "\n");
                fflush($marker);
                fclose($marker);
                foreach ($statements as $statement) $pdo->exec($statement);
                // 001 creates the history table; later migrations rely on it.
                $q = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
                $q->execute([$version]);
                if (!@unlink($failure)) throw new RuntimeException('Unable to clear migration recovery marker');
                $completed[] = $version;
            }
            return $completed;
        } finally {
            $mutex->release();
        }
    }
}
