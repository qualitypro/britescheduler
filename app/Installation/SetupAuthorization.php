<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use RuntimeException;

/** Authenticate setup requests without ever rendering or persisting the setup secret. */
final class SetupAuthorization
{
    public static function authorize(string $root, string $provided): bool
    {
        $gate = InstallerGate::inspect($root);
        if (!$gate['setup_may_begin']) return false;
        $expected = getenv('BRITE_INSTALL_SETUP_TOKEN');
        if (!is_string($expected) || strlen($expected) < 32 || strlen($expected) > 256 ||
            !is_string($provided) || strlen($provided) < 32 || strlen($provided) > 256) {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Installer session unavailable');
        }
        if (!isset($_SESSION['brite_install_csrf']) || !is_string($_SESSION['brite_install_csrf'])) {
            $_SESSION['brite_install_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['brite_install_csrf'];
    }

    public static function verifyCsrf(string $provided): bool
    {
        return is_string($provided) && hash_equals(self::csrfToken(), $provided);
    }

    public static function isAuthorized(string $root): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return false;
        if (!InstallerGate::inspect($root)['setup_may_begin']) return false;
        return ($_SESSION['brite_install_authorized'] ?? false) === true &&
            (int)($_SESSION['brite_install_authorized_until'] ?? 0) > time();
    }

    public static function grant(string $root, string $provided): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !self::authorize($root, $provided)) return false;
        session_regenerate_id(true);
        $_SESSION['brite_install_authorized'] = true;
        $_SESSION['brite_install_authorized_until'] = time() + 900;
        unset($_SESSION['brite_install_csrf']);
        self::csrfToken();
        return true;
    }
}
