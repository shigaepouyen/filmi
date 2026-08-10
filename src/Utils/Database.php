<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;

final class Database
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    public static function connect(?string $path = null): PDO
    {
        $path ??= (string) Config::get('db_path');

        if (isset(self::$connections[$path])) {
            return self::$connections[$path];
        }

        $isMemory = $path === ':memory:';

        if (!$isMemory) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        $db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL est sans objet en mémoire, SQLite le refuse silencieusement.
        if (!$isMemory) {
            $db->exec('PRAGMA journal_mode = WAL');
        }
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA foreign_keys = ON');

        if ($isMemory) {
            $db->exec(self::schemaSql());
        }

        return self::$connections[$path] = $db;
    }

    public static function schemaSql(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    }

    public static function reset(): void
    {
        self::$connections = [];
    }
}
