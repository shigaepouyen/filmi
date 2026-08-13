<?php
namespace App\Tests\Utils;

use App\Utils\Migrations;
use PDO;
use PHPUnit\Framework\TestCase;

class MigrationsTest extends TestCase
{
    /**
     * Schéma de production tel qu'il existait avant la v2 : pas de trailer_url,
     * pas de schema_version. Sert à prouver que la migration part bien de cet état
     * réel sans perdre de données.
     */
    private const OLD_SCHEMA_SQL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS profiles (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            name     TEXT NOT NULL,
            slug     TEXT UNIQUE NOT NULL,
            side     TEXT NOT NULL CHECK (side IN ('adult','kid')),
            avatar   TEXT NOT NULL,
            color    TEXT NOT NULL DEFAULT 'indigo'
        );

        CREATE TABLE IF NOT EXISTS movies (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            tmdb_id        INTEGER,
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
            pool           TEXT NOT NULL CHECK (pool IN ('adult','kid')),
            bet_type       TEXT CHECK (bet_type IN ('safe','discovery')),
            memo           TEXT,
            added_by       INTEGER NOT NULL REFERENCES profiles(id),
            status         TEXT NOT NULL DEFAULT 'pool'
                           CHECK (status IN ('pool','watched','archived')),
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS seances (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            date            DATE NOT NULL UNIQUE,
            chooser_side    TEXT NOT NULL CHECK (chooser_side IN ('adult','kid')),
            derogation      INTEGER NOT NULL DEFAULT 0,
            derogation_note TEXT,
            status          TEXT NOT NULL DEFAULT 'planned'
                            CHECK (status IN ('planned','done','skipped')),
            movie_id        INTEGER REFERENCES movies(id) ON DELETE SET NULL,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );
        SQL;

    private function oldStateDatabase(): PDO
    {
        $db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec(self::OLD_SCHEMA_SQL);

        return $db;
    }

    private function columns(PDO $db, string $table): array
    {
        return array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
    }

    public function testCurrentVersionDefaultsToOneWhenSettingIsAbsent(): void
    {
        $db = $this->oldStateDatabase();

        $this->assertSame(1, Migrations::currentVersion($db));
    }

    public function testRunOnAnOldProductionStateAddsTheColumnAndRecordsTheVersion(): void
    {
        $db = $this->oldStateDatabase();
        $db->prepare(
            "INSERT INTO profiles (name, slug, side, avatar) VALUES ('JC', 'jc', 'adult', 'detective')"
        )->execute();
        $db->prepare(
            "INSERT INTO movies (title, providers, pool, added_by, status)
             VALUES ('Brazil', '[\"Netflix\",\"Max\"]', 'adult', 1, 'pool')"
        )->execute();
        $db->prepare(
            "INSERT INTO movies (title, providers, pool, added_by, status)
             VALUES ('Le Voyage de Chihiro', '[\"Netflix\"]', 'kid', 1, 'watched')"
        )->execute();

        $this->assertNotContains('trailer_url', $this->columns($db, 'movies'));

        $applied = Migrations::run($db);

        $this->assertSame([2, 3, 4], $applied);
        $this->assertContains('trailer_url', $this->columns($db, 'movies'));
        $this->assertSame(4, Migrations::currentVersion($db));

        // Preuve d'absence de perte de données : les deux films survivent intacts.
        $rows = $db->query('SELECT title, pool, status, providers, trailer_url FROM movies ORDER BY id')->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame('Brazil', $rows[0]['title']);
        $this->assertSame('adult', $rows[0]['pool']);
        $this->assertSame('pool', $rows[0]['status']);
        $this->assertSame('["Netflix","Max"]', $rows[0]['providers'], 'La forme ancienne des plateformes ne doit pas être réécrite par la migration');
        $this->assertNull($rows[0]['trailer_url']);
        $this->assertSame('Le Voyage de Chihiro', $rows[1]['title']);
        $this->assertSame('kid', $rows[1]['pool']);
        $this->assertSame('watched', $rows[1]['status']);

        // Preuve supplémentaire : les films existants basculent sur kind = 'film'.
        $kinds = $db->query('SELECT kind FROM movies ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['film', 'film'], $kinds);
    }

    public function testRunTwiceInARowIsANoOpTheSecondTime(): void
    {
        $db = $this->oldStateDatabase();
        $db->prepare(
            "INSERT INTO profiles (name, slug, side, avatar) VALUES ('JC', 'jc', 'adult', 'detective')"
        )->execute();
        $db->prepare(
            "INSERT INTO movies (title, pool, added_by, status) VALUES ('Brazil', 'adult', 1, 'pool')"
        )->execute();

        $first = Migrations::run($db);
        $this->assertSame([2, 3, 4], $first);

        // Une deuxième exécution ne doit ni lever (ALTER TABLE sur colonne existante
        // lèverait), ni changer quoi que ce soit.
        $second = Migrations::run($db);
        $this->assertSame([], $second, 'La deuxième exécution ne doit rejouer aucune migration');
        $this->assertSame(4, Migrations::currentVersion($db));

        $trailerColumns = array_filter(
            $this->columns($db, 'movies'),
            static fn (string $c): bool => $c === 'trailer_url'
        );
        $this->assertCount(1, $trailerColumns, 'La colonne trailer_url ne doit exister qu une seule fois');

        $kindColumns = array_filter(
            $this->columns($db, 'movies'),
            static fn (string $c): bool => $c === 'kind'
        );
        $this->assertCount(1, $kindColumns, 'La colonne kind ne doit exister qu une seule fois');
    }

    public function testRunOnAFreshSchemaThatAlreadyHasTheColumnJustRecordsTheVersion(): void
    {
        // Le schema.sql courant crée déjà trailer_url pour les installations neuves.
        // La migration doit s'appliquer sans lever malgré la colonne déjà présente,
        // et sans qu'on ait besoin de rejouer la logique métier de la migration.
        $db = $this->oldStateDatabase();
        $db->exec('ALTER TABLE movies ADD COLUMN trailer_url TEXT');

        $applied = Migrations::run($db);

        $this->assertSame([2, 3, 4], $applied);
        $this->assertSame(4, Migrations::currentVersion($db));
    }

    public function testMigrationThreeMarksExistingFilmsForProviderRefresh(): void
    {
        $db = $this->oldStateDatabase();
        $db->exec(
            "INSERT INTO movies (title, pool, bet_type, added_by, tmdb_id, providers, providers_at)
             VALUES ('Avec tmdb', 'adult', 'safe', 1, 129, '[\"Netflix\"]', '2026-08-11 09:00:00')"
        );
        $db->exec(
            "INSERT INTO movies (title, pool, bet_type, added_by, providers_at)
             VALUES ('Saisi a la main', 'adult', 'safe', 1, '2026-08-11 09:00:00')"
        );

        Migrations::run($db);

        $avecTmdb = $db->query("SELECT providers_at FROM movies WHERE title = 'Avec tmdb'")->fetchColumn();
        $manuel = $db->query("SELECT providers_at FROM movies WHERE title = 'Saisi a la main'")->fetchColumn();

        $this->assertNull($avecTmdb, 'Un film TMDb doit repasser en attente de rafraichissement');
        $this->assertSame(
            '2026-08-11 09:00:00',
            $manuel,
            'Un film saisi a la main n a rien a rafraichir, sa date ne doit pas bouger'
        );
    }

    public function testMigrationFourAddsTheSeriesColumnsOnMoviesAndSeances(): void
    {
        $db = $this->oldStateDatabase();

        Migrations::run($db);

        $movieColumns = $this->columns($db, 'movies');
        foreach (['kind', 'season_count', 'episode_count', 'episodes_per_evening', 'episodes_watched', 'episodes'] as $column) {
            $this->assertContains($column, $movieColumns, "colonne movies.{$column} manquante");
        }

        $seanceColumns = $this->columns($db, 'seances');
        foreach (['episodes_from', 'episodes_to', 'episodes_label'] as $column) {
            $this->assertContains($column, $seanceColumns, "colonne seances.{$column} manquante");
        }
    }

    public function testMigrationFourDefaultsExistingFilmsToKindFilmAndZeroProgress(): void
    {
        $db = $this->oldStateDatabase();
        $db->prepare(
            "INSERT INTO profiles (name, slug, side, avatar) VALUES ('JC', 'jc', 'adult', 'detective')"
        )->execute();
        $db->exec(
            "INSERT INTO movies (title, pool, bet_type, added_by) VALUES ('Brazil', 'adult', 'safe', 1)"
        );

        Migrations::run($db);

        $movie = $db->query('SELECT kind, episodes_per_evening, episodes_watched, episodes FROM movies')->fetch();
        $this->assertSame('film', $movie['kind']);
        $this->assertSame(2, (int) $movie['episodes_per_evening']);
        $this->assertSame(0, (int) $movie['episodes_watched']);
        $this->assertNull($movie['episodes']);
    }

    public function testMigrationFourOnAnOldStateWithRealFilmsIsLosslessAndIdempotent(): void
    {
        // Reproduit une base de production avant migration 4 : des séances déjà
        // faites, chacune avec son film. Sert de preuve d'absence de perte de
        // données au-delà de movies, sur seances également.
        $db = $this->oldStateDatabase();
        $db->prepare(
            "INSERT INTO profiles (name, slug, side, avatar) VALUES ('JC', 'jc', 'adult', 'detective')"
        )->execute();
        $db->exec(
            "INSERT INTO movies (title, pool, bet_type, added_by, status) VALUES ('Brazil', 'adult', 'safe', 1, 'watched')"
        );
        $movieId = (int) $db->lastInsertId();
        $db->exec(
            "INSERT INTO seances (date, chooser_side, status, movie_id) VALUES ('2026-07-04', 'adult', 'done', {$movieId})"
        );

        $first = Migrations::run($db);
        $this->assertSame([2, 3, 4], $first);

        $seance = $db->query('SELECT * FROM seances')->fetch();
        $this->assertSame('2026-07-04', $seance['date']);
        $this->assertSame('done', $seance['status']);
        $this->assertSame($movieId, (int) $seance['movie_id']);
        $this->assertNull($seance['episodes_from']);
        $this->assertNull($seance['episodes_to']);
        $this->assertNull($seance['episodes_label']);

        $movie = $db->query('SELECT title, pool, status, kind FROM movies')->fetch();
        $this->assertSame('Brazil', $movie['title']);
        $this->assertSame('adult', $movie['pool']);
        $this->assertSame('watched', $movie['status']);
        $this->assertSame('film', $movie['kind']);

        // Rejouer la migration ne change plus rien.
        $second = Migrations::run($db);
        $this->assertSame([], $second);
        $this->assertSame(4, Migrations::currentVersion($db));
    }
}
