<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use RuntimeException;

/** Single-request, authorized browser setup. Never exposes database credentials in output. */
final class BrowserInstaller
{
    public static function complete(string $root, array $configuration, array $owner): array
    {
        if (PHP_SAPI === 'cli' || !SetupAuthorization::isAuthorized($root)) {
            throw new DomainException('Browser installation authorization required');
        }
        if (!InstallerGate::inspect($root)['setup_may_begin']) {
            throw new DomainException('Existing installation detected');
        }
        // The configuration file is create-only; if DB connection or migration
        // fails, retain it for trusted CLI recovery, not repeated browser writes.
        $mutex = new InstallationMutex();
        $mutex->acquire($root);
        try {
            if (!SetupAuthorization::isAuthorized($root)) throw new DomainException('Setup session expired');
            ConfigurationWriter::write($root, $configuration);
        } finally {
            $mutex->release();
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $configuration['DB_HOST'], $configuration['DB_PORT'], $configuration['DB_NAME']
        );
        $pdo = new PDO($dsn, $configuration['DB_USER'], $configuration['DB_PASS'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>10,
        ]);
        $result = FirstRunInstaller::install($root, $pdo, $owner, true, true);
        unset($_SESSION['brite_install_authorized'], $_SESSION['brite_install_authorized_until']);
        return $result;
    }
}
