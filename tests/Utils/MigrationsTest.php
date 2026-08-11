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

        $this->assertSame([2], $applied);
        $this->assertContains('trailer_url', $this->columns($db, 'movies'));
        $this->assertSame(2, Migrations::currentVersion($db));

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
        $this->assertSame([2], $first);

        // Une deuxième exécution ne doit ni lever (ALTER TABLE sur colonne existante
        // lèverait), ni changer quoi que ce soit.
        $second = Migrations::run($db);
        $this->assertSame([], $second, 'La deuxième exécution ne doit rejouer aucune migration');
        $this->assertSame(2, Migrations::currentVersion($db));

        $trailerColumns = array_filter(
            $this->columns($db, 'movies'),
            static fn (string $c): bool => $c === 'trailer_url'
        );
        $this->assertCount(1, $trailerColumns, 'La colonne trailer_url ne doit exister qu une seule fois');
    }

    public function testRunOnAFreshSchemaThatAlreadyHasTheColumnJustRecordsTheVersion(): void
    {
        // Le schema.sql courant crée déjà trailer_url pour les installations neuves.
        // La migration doit s'appliquer sans lever malgré la colonne déjà présente,
        // et sans qu'on ait besoin de rejouer la logique métier de la migration.
        $db = $this->oldStateDatabase();
        $db->exec('ALTER TABLE movies ADD COLUMN trailer_url TEXT');

        $applied = Migrations::run($db);

        $this->assertSame([2], $applied);
        $this->assertSame(2, Migrations::currentVersion($db));
    }
}
