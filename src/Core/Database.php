<?php

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Returns a singleton PDO connection to the SQLite file.
     * PRAGMAs are applied on first connect only.
     */
    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $config = require dirname(__DIR__, 2) . '/config.php';
            $path   = $config['db_path'];
            $dir    = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA journal_mode=WAL;');
            self::$pdo->exec('PRAGMA busy_timeout=5000;');
            self::$pdo->exec('PRAGMA foreign_keys=ON;');
        }
        return self::$pdo;
    }

    /**
     * Runs a PDO query and returns a statement.
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
