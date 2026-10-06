<?php
declare(strict_types=1);

final class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $q = Database::connection()->prepare(
            "SELECT id,email,first_name,last_name,status FROM users WHERE id=? AND status='active'"
        );
        $q->execute([(int)$_SESSION['user_id']]);
        return $q->fetch() ?: null;
    }

    public static function requireUser(): array {
        $u = self::user();
        if (!$u) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) json_response(['error'=>'Unauthenticated'], 401);
            header('Location: '.app_url('/sign-in.v2.php')); exit;
        }
        return $u;
    }

    public static function memberships(int $userId): array {
        $q = Database::connection()->prepare(
            "SELECT m.tenant_id,m.role,t.name,t.slug
             FROM tenant_memberships m JOIN tenants t ON t.id=m.tenant_id
             WHERE m.user_id=? AND m.status='active' AND t.status='active' ORDER BY t.name"
        );
        $q->execute([$userId]);
        return $q->fetchAll();
    }

    public static function tenant(): array {
        $u = self::requireUser();
        $memberships = self::memberships((int)$u['id']);
        if (!$memberships) json_response(['error'=>'No active tenant membership'], 403);

        $requested = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : (int)$memberships[0]['tenant_id'];
        foreach ($memberships as $m) {
            if ((int)$m['tenant_id'] === $requested) {
                $_SESSION['tenant_id'] = $requested;
                $_SESSION['tenant_role'] = $m['role'];
                return $m;
            }
        }
        $_SESSION['tenant_id'] = (int)$memberships[0]['tenant_id'];
        $_SESSION['tenant_role'] = $memberships[0]['role'];
        return $memberships[0];
    }

    public static function tenantId(): int { return (int)self::tenant()['tenant_id']; }

    public static function homeUrl(?string $role = null): string {
        if ($role === null) {
            $role = (string)self::tenant()['role'];
        }

        return match ($role) {
            'client' =>
                app_url('/client-dashboard.php'),

            'contractor' =>
                app_url('/contractor-dashboard.php'),

            default =>
                app_url('/dashboard.v2.php'),
        };
    }

    public static function requireRole(string ...$roles): array {
        $t = self::tenant();
        if (!in_array($t['role'], $roles, true)) json_response(['error'=>'Forbidden'], 403);
        return $t;
    }
}
