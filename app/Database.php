<?php
declare(strict_types=1);

final class Database {
    private static ?PDO $pdo = null;

    public static function connection(): PDO {
        if (self::$pdo) return self::$pdo;
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'britescheduler';
        $user = getenv('DB_USER') ?: 'britescheduler';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
