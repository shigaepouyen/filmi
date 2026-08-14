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
            3 => [
                'description' => "Force le rafraichissement des plateformes (changement de forme et bande-annonce)",
                'up' => static function (PDO $db): void {
                    // La colonne providers passe d'une liste de noms a une liste
                    // d'objets id/nom/logo, et trailer_url vient d'etre ajoutee. Les
                    // films existants ont un providers_at recent, donc le script de
                    // rafraichissement les ignorerait pendant une semaine et ni les
                    // logos ni les bandes-annonces n'apparaitraient. On les remet
                    // explicitement en attente de rafraichissement.
                    $db->exec('UPDATE movies SET providers_at = NULL WHERE tmdb_id IS NOT NULL');
                },
            ],
            4 => [
                'description' => "Ajoute les séries : kind et progression sur movies, plage d'épisodes sur seances",
                'up' => static function (PDO $db): void {
                    // Une série en cours reste en statut 'pool' : ALTER TABLE ne peut pas
                    // modifier la contrainte CHECK sur status, et reconstruire la table sur
                    // des données réelles serait un risque inutile. episodes_watched > 0
                    // suffit à distinguer une série en cours d'un film jamais vu.
                    $movieColumns = array_column(
                        $db->query('PRAGMA table_info(movies)')->fetchAll(PDO::FETCH_ASSOC),
                        'name'
                    );
                    $movieAlters = [
                        'kind' => "ALTER TABLE movies ADD COLUMN kind TEXT NOT NULL DEFAULT 'film'",
                        'season_count' => 'ALTER TABLE movies ADD COLUMN season_count INTEGER',
                        'episode_count' => 'ALTER TABLE movies ADD COLUMN episode_count INTEGER',
                        'episodes_per_evening' => 'ALTER TABLE movies ADD COLUMN episodes_per_evening INTEGER NOT NULL DEFAULT 2',
                        'episodes_watched' => 'ALTER TABLE movies ADD COLUMN episodes_watched INTEGER NOT NULL DEFAULT 0',
                        'episodes' => 'ALTER TABLE movies ADD COLUMN episodes TEXT',
                    ];
                    foreach ($movieAlters as $column => $sql) {
                        if (!in_array($column, $movieColumns, true)) {
                            $db->exec($sql);
                        }
                    }

                    $seanceColumns = array_column(
                        $db->query('PRAGMA table_info(seances)')->fetchAll(PDO::FETCH_ASSOC),
                        'name'
                    );
                    $seanceAlters = [
                        'episodes_from' => 'ALTER TABLE seances ADD COLUMN episodes_from INTEGER',
                        'episodes_to' => 'ALTER TABLE seances ADD COLUMN episodes_to INTEGER',
                        'episodes_label' => 'ALTER TABLE seances ADD COLUMN episodes_label TEXT',
                    ];
                    foreach ($seanceAlters as $column => $sql) {
                        if (!in_array($column, $seanceColumns, true)) {
                            $db->exec($sql);
                        }
                    }
                },
            ],
            5 => [
                'description' => "Ajoute seances.backfilled, pour le rattrapage d'historique",
                'up' => static function (PDO $db): void {
                    // Les séances existantes valent 0 par défaut : ce sont de vrais
                    // samedis, pas des rattrapages. recentForSchedule() s'appuie sur
                    // cette colonne pour ne jamais laisser un rattrapage plus récent
                    // que la dernière vraie séance rejouer l'alternance du samedi
                    // à venir.
                    $seanceColumns = array_column(
                        $db->query('PRAGMA table_info(seances)')->fetchAll(PDO::FETCH_ASSOC),
                        'name'
                    );
                    if (!in_array('backfilled', $seanceColumns, true)) {
                        $db->exec('ALTER TABLE seances ADD COLUMN backfilled INTEGER NOT NULL DEFAULT 0');
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
