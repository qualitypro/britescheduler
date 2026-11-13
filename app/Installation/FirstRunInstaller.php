<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

/** First-run CLI installation orchestration; never use against an existing tenant database. */
final class FirstRunInstaller
{
    public static function install(string $root, PDO $pdo, array $owner, bool $confirmed, bool $trustedBrowser = false): array
    {
        if ((PHP_SAPI !== 'cli' && !$trustedBrowser) || !$confirmed || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new DomainException('Explicit CLI MySQL installation confirmation required');
        }
        $root = rtrim($root, '/');
        $mutex = new InstallationMutex();
        $mutex->acquire($root);
        try {
            if (is_file($root . '/.brite-installed.lock') || is_link($root . '/.brite-installed.lock') ||
                !is_file($root . '/.env') || is_link($root . '/.env')) {
                throw new DomainException('Installation already completed or configuration unavailable');
            }
            if (file_exists($root . '/.brite-migration-recovery-required')) {
                throw new RuntimeException('Migration recovery required before installation can continue');
            }
            $email = strtolower(trim((string)($owner['email'] ?? '')));
            $first = trim((string)($owner['first_name'] ?? ''));
            $last = trim((string)($owner['last_name'] ?? ''));
            $password = (string)($owner['password'] ?? '');
            $tenant = trim((string)($owner['tenant_name'] ?? ''));
            $slug = trim((string)($owner['tenant_slug'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 ||
                !$first || strlen($first) > 100 || !$last || strlen($last) > 100 ||
                !$tenant || strlen($tenant) > 150 ||
                !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 150 ||
                strlen($password) < 12 || strlen($password) > 1024) {
                throw new DomainException('Invalid initial tenant or owner details');
            }
            $plan = MigrationPlan::build($root, $pdo);
            if ($plan['applied_count'] === 0) {
                $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
                if ($tables !== 0) throw new DomainException('Initial installation requires a completely empty database');
            } else {
                // Resume only after a successful, recorded migration sequence;
                // never provision an owner into an existing user/tenant dataset.
                if ((int)$pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn() !== 0 ||
                    (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    throw new DomainException('Existing tenant or user data; refusing first-run installation');
                }
            }
            if (!$plan['pending'] && $plan['applied_count'] === 0) {
                throw new DomainException('No installation migrations found');
            }
            MigrationPreflight::inspect($root, $plan['pending']);
            $applied = MigrationRunner::run($root, $pdo, true, true, $trustedBrowser);
            $pdo->beginTransaction();
            try {
                if ((int)$pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn() !== 0 ||
                    (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    throw new DomainException('First-run database no longer empty');
                }
                $q = $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (?, ?)');
                $q->execute([$tenant, $slug]);
                $tenantId = (int)$pdo->lastInsertId();
                $q = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)');
                $q->execute([$email, password_hash($password, PASSWORD_DEFAULT), $first, $last]);
                $userId = (int)$pdo->lastInsertId();
                $q = $pdo->prepare("INSERT INTO tenant_memberships (tenant_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')");
                $q->execute([$tenantId, $userId]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
            // Do not expose installation secrets or owner identifiers in this marker.
            $handle = @fopen($root . '/.brite-installed.lock', 'x');
            if (!is_resource($handle)) throw new RuntimeException('Owner created but installation lock failed; manual recovery required');
            @chmod($root . '/.brite-installed.lock', 0600);
            fwrite($handle, "installed\n");
            fflush($handle);
            fclose($handle);
            return ['migrations_applied'=>count($applied), 'installed'=>true];
        } finally {
            $mutex->release();
        }
    }
}
