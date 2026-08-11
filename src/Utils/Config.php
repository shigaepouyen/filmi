<?php
declare(strict_types=1);

namespace App\Utils;

final class Config
{
    private const DEFAULTS = [
        'tmdb_api_key' => '',
        'tmdb_language' => 'fr-FR',
        'tmdb_region' => 'FR',
        'default_start_time' => '19:15',
        'low_pool_threshold' => 5,
    ];

    private static ?array $cache = null;

    /**
     * La variable d'environnement FILMI_CONFIG permet de pointer une autre
     * configuration, par exemple une base de travail locale, sans jamais deplacer
     * ni echanger le config/config.php reel qui porte la cle TMDb.
     */
    public static function load(?string $path = null): array
    {
        $path ??= (getenv('FILMI_CONFIG') ?: null);
        $path ??= dirname(__DIR__, 2) . '/config/config.php';
        $user = is_file($path) ? (array) require $path : [];

        $config = array_merge(self::DEFAULTS, $user);
        $config['db_path'] ??= dirname(__DIR__, 2) . '/data/filmi.sqlite';

        return $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= self::load();

        return self::$cache[$key] ?? $default;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
