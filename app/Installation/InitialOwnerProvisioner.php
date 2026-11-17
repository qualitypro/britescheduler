<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Cycle 92F: CLI-only, first-tenant provisioning after a complete fresh install.
 * The caller must hold the installation mutex and authenticate setup separately.
 * Never log or persist plaintext passwords.
 */
final class InitialOwnerProvisioner
{
    public static function provision(PDO $pdo, array $input, bool $confirmed, bool $alreadyLocked = false): array
    {
        if (PHP_SAPI !== 'cli' || !$confirmed || !$alreadyLocked) {
            throw new DomainException('CLI confirmation and an existing installation mutex are required');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new DomainException('MySQL/MariaDB is required');
        }
        foreach (['tenant_name','tenant_slug','owner_email','owner_first_name','owner_last_name','owner_password'] as $key) {
            if (!isset($input[$key]) || !is_string($input[$key]) || trim($input[$key]) === '') {
                throw new DomainException('Missing provisioning field: ' . $key);
            }
        }
        $name = trim($input['tenant_name']);
        $slug = trim($input['tenant_slug']);
        $email = strtolower(trim($input['owner_email']));
        $first = trim($input['owner_first_name']);
        $last = trim($input['owner_last_name']);
        $password = $input['owner_password'];
        $timezone = $input['timezone'] ?? 'America/New_York';
        if (strlen($name) > 150 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 ||
            strlen($first) > 100 || strlen($last) > 100 ||
            strlen($password) < 12 || strlen($password) > 1024 ||
            !is_string($timezone) || !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new DomainException('Invalid initial owner or tenant details');
        }
        if ($pdo->inTransaction()) {
            throw new DomainException('Provisioning requires its own transaction');
        }

        // Serialize initial provisioning even when separate workers connect concurrently.
        $lockName = 'brite92f:' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 48);
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lockStmt->execute([$lockName]);
        if ((int)$lockStmt->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire initial provisioning database lock');
        }
        try {
            foreach (['schema_migrations','tenants','users','tenant_memberships'] as $table) {
                $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                $q->execute([$table]);
                if ((int)$q->fetchColumn() !== 1) {
                    throw new DomainException('Required schema table missing: ' . $table);
                }
            }
            // A fresh installation has migrations applied, but no tenant/user records.
            $versions = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            if ($versions < 1) {
                throw new DomainException('Database migrations must run before provisioning');
            }
            foreach (['tenants','users','tenant_memberships'] as $table) {
                if ((int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
                    throw new DomainException('Initial provisioning refuses existing accounts or tenants');
                }
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash)) {
                throw new RuntimeException('Unable to hash owner password');
            }
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare("INSERT INTO tenants (name, slug, timezone, status) VALUES (?, ?, ?, 'active')");
                $q->execute([$name, $slug, $timezone]);
                $tenantId = (int)$pdo->lastInsertId();

                $q = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, status) VALUES (?, ?, ?, ?, 'active')");
                $q->execute([$email, $hash, $first, $last]);
                $userId = (int)$pdo->lastInsertId();

                $q = $pdo->prepare("INSERT INTO tenant_memberships (tenant_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')");
                $q->execute([$tenantId, $userId]);
                $pdo->commit();
                return ['tenant_id' => $tenantId, 'owner_user_id' => $userId];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } finally {
            $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$lockName]);
        }
    }
}
