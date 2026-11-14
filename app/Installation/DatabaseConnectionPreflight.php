<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;
use PDO;
use PDOException;

/** Explicit, read-only connectivity checks. Never return connection credentials. */
final class DatabaseConnectionPreflight
{
    public static function validate(array $config): array
    {
        $required=['host','port','database','username','password'];
        foreach($required as $key) {
            if(!isset($config[$key]) || !is_string($config[$key]) ||
                ($key!=='password' && $config[$key]==='') ||
                strlen($config[$key])>2048 || preg_match('/[\x00-\x1f\x7f]/',$config[$key])) {
                throw new DomainException('Invalid database configuration');
            }
        }
        if(!preg_match('/^[A-Za-z0-9.-]+$/D',$config['host']) ||
            !preg_match('/^[A-Za-z0-9_]+$/D',$config['database']) ||
            !preg_match('/^[A-Za-z0-9_.-]+$/D',$config['username']) ||
            !ctype_digit($config['port']) ||
            (int)$config['port']<1 || (int)$config['port']>65535) {
            throw new DomainException('Invalid database connection identifiers');
        }
        return $config;
    }

    public static function inspect(array $config): array
    {
        $c=self::validate($config);
        if(!extension_loaded('pdo_mysql')) {
            return ['connected'=>false,'reason'=>'driver_unavailable'];
        }
        try {
            $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'],(int)$c['port'],$c['database']);
            $pdo=new PDO($dsn,$c['username'],$c['password'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_TIMEOUT=>5,
            ]);
            $pdo->query('SELECT 1')->fetchColumn();
            $tables=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            return ['connected'=>true,'empty_database'=>$tables===0,'table_count'=>$tables];
        } catch(PDOException $e) {
            // Never expose DSN, usernames, passwords, server paths or SQL errors.
            return ['connected'=>false,'reason'=>'connection_failed'];
        }
    }
}
