<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;

/**
 * Migrations de schéma versionnées et idempotentes.
 *
 * La version courante est stockée dans settings.schema_version ; son absence
 * signifie version 1 (l'état de production avant l'introduction de ce mécanisme).
 * Chaque migration vérifie l'état réel de la base avant d'agir (PRAGMA table_info)
 * en plus du garde-fou de version, pour rester sans effet si elle est rejouée.
 */
final class Migrations
{
    private const SCHEMA_VERSION_KEY = 'schema_version';

    /**
     * @return array<int, array{description: string, up: callable(PDO): void}>
     *         indexé par numéro de version cible, dans l'ordre croissant
     */
    private static function definitions(): array
    {
        return [
            2 => [
                'description' => "Ajoute movies.trailer_url",
                'up' => static function (PDO $db): void {
                    $columns = array_column(
                        $db->query('PRAGMA table_info(movies)')->fetchAll(PDO::FETCH_ASSOC),
                        'name'
                    );
                    if (!in_array('trailer_url', $columns, true)) {
                        $db->exec('ALTER TABLE movies ADD COLUMN trailer_url TEXT');
                    }
                },
            ],
        ];
    }

    public static function currentVersion(PDO $db): int
    {
        $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([self::SCHEMA_VERSION_KEY]);
        $value = $stmt->fetchColumn();

        return $value === false ? 1 : (int) $value;
    }

    /**
     * Applique les migrations manquantes, une transaction par migration.
     * Ne rejoue jamais une migration dont le numéro est déjà atteint.
     *
     * @return list<int> les numéros de version effectivement appliqués, dans l'ordre
     */
    public static function run(PDO $db): array
    {
        $applied = [];
        $current = self::currentVersion($db);

        foreach (self::definitions() as $version => $migration) {
            if ($version <= $current) {
                continue;
            }

            $db->beginTransaction();
            try {
                ($migration['up'])($db);

                $stmt = $db->prepare(
                    'INSERT INTO settings (key, value) VALUES (?, ?)
                     ON CONFLICT(key) DO UPDATE SET value = excluded.value'
                );
                $stmt->execute([self::SCHEMA_VERSION_KEY, (string) $version]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            $applied[] = $version;
            $current = $version;
        }

        return $applied;
    }
}
