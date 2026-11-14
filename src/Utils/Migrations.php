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
     * @return array<int, array{description: string, up: callable(PDO): void, foreign_keys_off?: bool}>
     *         indexé par numéro de version cible, dans l'ordre croissant.
     *         foreign_keys_off : la migration reconstruit une table parente ; run() coupe alors
     *         les clés étrangères avant la transaction (impossible de le faire dedans), sinon le
     *         DROP TABLE supprimerait en cascade les notes et choix rattachés.
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
            6 => [
                'description' => "Migre les anciennes cles d'avatar vers le nouveau roster pixel art",
                'up' => static function (PDO $db): void {
                    // Le roster d'avatars est entierement redessine en pixel art. Onze
                    // cles survivent telles quelles (alien, robot, astronaute,
                    // scaphandrier, aviatrice, vampire, detective, cowboy, gumiho,
                    // dokkaebi, idole) : rien a faire pour elles. Les treize autres
                    // disparaissent et sont reaffectees vers leur equivalent le plus
                    // proche du nouveau roster, pour qu'aucun profil ne retombe sur
                    // l'avatar par defaut en silence. C'est le cas des quatre profils
                    // reels de production : creature (JC) -> yeti, dinosaure (Soline)
                    // -> dragon ; aviatrice (Elodie) et idole (Zoe) n'ont pas besoin
                    // de mapping puisqu'elles survivent.
                    $map = [
                        'creature' => 'yeti',
                        'dinosaure' => 'dragon',
                        'aventuriere' => 'cowboy',
                        'sorcier' => 'sorciere',
                        'gardien' => 'chevalier',
                        'traqueur' => 'fantome',
                        'haechi' => 'dragon',
                        'erudit' => 'dokkaebi',
                        'tigre' => 'gumiho',
                        'zombie' => 'momie',
                        'danseur' => 'idole',
                        'fan' => 'idole',
                        'trainee' => 'idole',
                    ];

                    $stmt = $db->prepare('UPDATE profiles SET avatar = ? WHERE avatar = ?');
                    foreach ($map as $old => $new) {
                        $stmt->execute([$new, $old]);
                    }
                },
            ],
            7 => [
                'description' => "Ajoute les sagas : collection TMDb, rang et echappatoire d'ordre",
                'up' => static function (PDO $db): void {
                    // Les films deja en base restent a NULL, donc ni bloquants ni
                    // bloques : le comportement ne change pas tant qu'aucune saga
                    // n'est renseignee. Le rafraichissement des plateformes les
                    // completera au fil de l'eau.
                    $columns = array_column(
                        $db->query('PRAGMA table_info(movies)')->fetchAll(PDO::FETCH_ASSOC),
                        'name'
                    );
                    $alters = [
                        'collection_id' => 'ALTER TABLE movies ADD COLUMN collection_id INTEGER',
                        'collection_name' => 'ALTER TABLE movies ADD COLUMN collection_name TEXT',
                        'collection_rank' => 'ALTER TABLE movies ADD COLUMN collection_rank INTEGER',
                        'ignore_order' => 'ALTER TABLE movies ADD COLUMN ignore_order INTEGER NOT NULL DEFAULT 0',
                    ];
                    foreach ($alters as $column => $sql) {
                        if (!in_array($column, $columns, true)) {
                            $db->exec($sql);
                        }
                    }
                },
            ],
            8 => [
                'description' => 'Ajoute rating_skips : les tours de note passes',
                'up' => static function (PDO $db): void {
                    // Table additive : tant que personne ne ferme sa ligne, elle
                    // reste vide et rien ne change. Volontairement separee de
                    // ratings pour que moyennes et palmares l'ignorent d'office.
                    $db->exec(
                        'CREATE TABLE IF NOT EXISTS rating_skips (
                            id         INTEGER PRIMARY KEY AUTOINCREMENT,
                            seance_id  INTEGER NOT NULL REFERENCES seances(id) ON DELETE CASCADE,
                            profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE (seance_id, profile_id)
                        )'
                    );
                },
            ],
            9 => [
                'description' => "Migre les cles d'avatar vers le roster 32x32",
                'up' => static function (PDO $db): void {
                    // Le roster passe a vingt personnages dessines en 32x32 : la
                    // grille 16x16 ne permettait pas de rendre une capuche ou des
                    // lunettes lisibles. Six cles survivent (dragon, fantome, robot,
                    // pirate, ninja, fee), les autres sont reaffectees vers le
                    // personnage le plus proche pour qu'aucun profil ne retombe en
                    // silence sur l'avatar par defaut. Les quatre profils reels sont
                    // couverts : tentacule (JC) -> slime, aviatrice (Elodie) ->
                    // exploratrice, idole (Zoe) -> fee, dragon (Soline) inchange.
                    $map = [
                        'tentacule' => 'slime',
                        'aviatrice' => 'exploratrice',
                        'idole' => 'fee',
                        'alien' => 'slime',
                        'astronaute' => 'pilote',
                        'scaphandrier' => 'pilote',
                        'squelette' => 'gardien',
                        'momie' => 'nomade',
                        'vampire' => 'corbeau',
                        'loupgarou' => 'renard',
                        'yeti' => 'panda',
                        'detective' => 'inventeur',
                        'cowboy' => 'nomade',
                        'chevalier' => 'champignon',
                        'samourai' => 'ninja',
                        'sorciere' => 'elfe',
                        'gumiho' => 'renard',
                        'dokkaebi' => 'corbeau',
                    ];

                    $stmt = $db->prepare('UPDATE profiles SET avatar = ? WHERE avatar = ?');
                    foreach ($map as $old => $new) {
                        $stmt->execute([$new, $old]);
                    }

                    // Pas de filet ici : c'est la derniere migration d'avatars qui
                    // s'en charge, en s'appuyant sur le roster courant.
                },
            ],
            10 => [
                'description' => "Migre les cles d'avatar vers le roster arcade",
                'up' => static function (PDO $db): void {
                    // Les personnages detailles laissent place a des silhouettes
                    // d'arcade : plus lisibles a petite taille, et la couleur du
                    // profil habille desormais tout le sprite. Chaque ancienne cle
                    // rejoint la forme la plus proche. Les quatre profils reels :
                    // slime (JC) -> blob, exploratrice (Elodie) -> soucoupe,
                    // fee (Zoe) -> meduse, dragon (Soline) -> chauve.
                    $map = [
                        'slime' => 'blob',
                        'exploratrice' => 'soucoupe',
                        'fee' => 'meduse',
                        'dragon' => 'chauve',
                        'ranger' => 'scarabee',
                        'nomade' => 'ver',
                        'pilote' => 'chasseur',
                        'robot' => 'sentinelle',
                        'inventeur' => 'tourelle',
                        'chat' => 'drone',
                        'panda' => 'scarabee',
                        'fantome' => 'blob',
                        'gardien' => 'mine',
                        'renard' => 'mite',
                        'elfe' => 'meduse',
                        'corbeau' => 'chauve',
                        'ninja' => 'intercepteur',
                        'viking' => 'croiseur',
                        'pirate' => 'navette',
                        // 'champignon' existe dans les deux rosters : rien a faire.
                    ];

                    $stmt = $db->prepare('UPDATE profiles SET avatar = ? WHERE avatar = ?');
                    foreach ($map as $old => $new) {
                        $stmt->execute([$new, $old]);
                    }

                    // Filet, appuye sur le roster courant plutot que sur une liste
                    // recopiee : toute cle qui ne s'affiche plus prend l'avatar par
                    // defaut, au lieu de rendre un sprite vide.
                    foreach ($db->query('SELECT id, avatar FROM profiles')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        if (!Avatars::exists((string) $row['avatar'])) {
                            $db->prepare('UPDATE profiles SET avatar = ? WHERE id = ?')
                               ->execute([Avatars::FALLBACK, (int) $row['id']]);
                        }
                    }
                },
            ],
            11 => [
                'description' => 'Ajoute suggestions : la liste de Madame Pop-Corn',
                'up' => static function (PDO $db): void {
                    // Table additive, vide tant que personne ne demande d'idees :
                    // rien ne change pour les listes existantes. Le texte du schema
                    // est celui de database.sql, repris ici pour qu'une base de
                    // production ancienne l'obtienne sans passer par init_db.
                    $db->exec(
                        'CREATE TABLE IF NOT EXISTS suggestions (
                            id             INTEGER PRIMARY KEY AUTOINCREMENT,
                            tmdb_id        INTEGER NOT NULL,
                            kind           TEXT NOT NULL DEFAULT \'film\' CHECK (kind IN (\'film\',\'series\')),
                            title          TEXT NOT NULL,
                            original_title TEXT,
                            year           INTEGER,
                            runtime        INTEGER,
                            poster_url     TEXT,
                            overview       TEXT,
                            genres         TEXT,
                            director       TEXT,
                            tmdb_rating    REAL,
                            certification  TEXT,
                            providers      TEXT,
                            providers_at   DATETIME,
                            trailer_url    TEXT,
                            season_count   INTEGER,
                            episode_count  INTEGER,
                            audience       TEXT NOT NULL DEFAULT \'all\' CHECK (audience IN (\'all\',\'kid\',\'adult\')),
                            bet_type       TEXT CHECK (bet_type IN (\'safe\',\'discovery\')),
                            why            TEXT,
                            status         TEXT NOT NULL DEFAULT \'new\'
                                           CHECK (status IN (\'new\',\'kept\',\'rejected\',\'transferred\')),
                            movie_id       INTEGER REFERENCES movies(id) ON DELETE SET NULL,
                            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE (tmdb_id, kind)
                        )'
                    );
                    $db->exec('CREATE INDEX IF NOT EXISTS idx_suggestions_status ON suggestions(status)');
                },
            ],
            12 => [
                'description' => 'Autorise plusieurs séances le même jour (une seule vraie séance par jour, rattrapages illimités)',
                'foreign_keys_off' => true,
                'up' => static function (PDO $db): void {
                    // Garde-fou : avec les cles etrangeres actives, DROP TABLE seances ferait un
                    // DELETE implicite et la cascade emporterait notes, vetos et shortlists.
                    if ((int) $db->query('PRAGMA foreign_keys')->fetchColumn() !== 0) {
                        throw new \RuntimeException('Migration 12 refusée : les clés étrangères sont actives, la cascade effacerait des notes.');
                    }

                    // Anomalies de references deja presentes (donnees anciennes) : on ne reproche a la
                    // reconstruction que celles qu'elle creerait.
                    $brokenBefore = count($db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC));

                    // Reconstruire seulement si la contrainte UNIQUE(date) d'origine est encore la.
                    $uniqueOnDate = false;
                    foreach ($db->query('PRAGMA index_list(seances)')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                        if ((int) $index['unique'] !== 1 || ($index['origin'] ?? '') !== 'u') {
                            continue;
                        }
                        $columns = array_column(
                            $db->query('PRAGMA index_info(' . $db->quote((string) $index['name']) . ')')->fetchAll(PDO::FETCH_ASSOC),
                            'name'
                        );
                        if ($columns === ['date']) {
                            $uniqueOnDate = true;
                        }
                    }

                    if ($uniqueOnDate) {
                        $oldColumns = array_column($db->query('PRAGMA table_info(seances)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                        $sequence = $db->query("SELECT seq FROM sqlite_sequence WHERE name = 'seances'")->fetchColumn();

                        $db->exec('DROP TABLE IF EXISTS seances_new');
                        $db->exec(
                            "CREATE TABLE seances_new (
                                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                                date            DATE NOT NULL,
                                chooser_side    TEXT NOT NULL CHECK (chooser_side IN ('adult','kid')),
                                derogation      INTEGER NOT NULL DEFAULT 0,
                                derogation_note TEXT,
                                status          TEXT NOT NULL DEFAULT 'planned'
                                                CHECK (status IN ('planned','done','skipped')),
                                movie_id        INTEGER REFERENCES movies(id) ON DELETE SET NULL,
                                episodes_from   INTEGER,
                                episodes_to     INTEGER,
                                episodes_label  TEXT,
                                backfilled      INTEGER NOT NULL DEFAULT 0,
                                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
                            )"
                        );

                        $newColumns = array_column($db->query('PRAGMA table_info(seances_new)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                        $shared = implode(', ', array_values(array_intersect($newColumns, $oldColumns)));
                        $db->exec("INSERT INTO seances_new ({$shared}) SELECT {$shared} FROM seances");

                        $db->exec('DROP TABLE seances');
                        $db->exec('ALTER TABLE seances_new RENAME TO seances');

                        if ($sequence !== false && $sequence !== null) {
                            $db->prepare("UPDATE sqlite_sequence SET seq = MAX(seq, ?) WHERE name = 'seances'")->execute([(int) $sequence]);
                        }
                    }

                    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_seances_real_date ON seances(date) WHERE backfilled = 0');

                    // Rien ne doit rester oriente vers une ligne disparue.
                    $brokenAfter = count($db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC));
                    if ($brokenAfter > $brokenBefore) {
                        throw new \RuntimeException('Migration 12 annulée : la reconstruction a cassé ' . ($brokenAfter - $brokenBefore) . ' référence(s).');
                    }
                },
            ],
            13 => [
                'description' => 'Ajoute suggestions.source : la liste (tous, parents, filles) qui a nourri la demande',
                'up' => static function (PDO $db): void {
                    $columns = array_column($db->query('PRAGMA table_info(suggestions)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                    if ($columns !== [] && !in_array('source', $columns, true)) {
                        // Les idees deja proposees viennent d'une demande a toute la famille.
                        $db->exec("ALTER TABLE suggestions ADD COLUMN source TEXT NOT NULL DEFAULT 'all'");
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

            // Une migration qui reconstruit une table parente coupe les cles etrangeres :
            // le PRAGMA n'a aucun effet dans une transaction, donc c'est fait avant.
            $foreignKeysOff = ($migration['foreign_keys_off'] ?? false) === true;
            if ($foreignKeysOff) {
                $db->exec('PRAGMA foreign_keys = OFF');
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
            } finally {
                if ($foreignKeysOff) {
                    $db->exec('PRAGMA foreign_keys = ON');
                }
            }

            $applied[] = $version;
            $current = $version;
        }

        return $applied;
    }
}
