<?php
namespace App\Tests\Utils;

use App\Utils\Database;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testInMemoryConnectionHasSchemaApplied(): void
    {
        $db = Database::connect(':memory:');

        $tables = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(
            ['movies', 'profiles', 'rating_skips', 'ratings', 'seance_picks', 'seances', 'settings', 'votes'],
            array_values(array_filter($tables, fn ($t) => !str_starts_with($t, 'sqlite_')))
        );
    }

    public function testForeignKeysAndBusyTimeoutAreEnabled(): void
    {
        $db = Database::connect(':memory:');

        $this->assertSame(1, (int) $db->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame(5000, (int) $db->query('PRAGMA busy_timeout')->fetchColumn());
    }

    public function testFileConnectionUsesWalJournal(): void
    {
        $path = sys_get_temp_dir() . '/filmi_wal_test.sqlite';
        @unlink($path);

        $db = Database::connect($path);

        $this->assertSame('wal', strtolower((string) $db->query('PRAGMA journal_mode')->fetchColumn()));

        Database::reset();
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
            @unlink($file);
        }
    }

    public function testConnectIsMemoizedPerPath(): void
    {
        $this->assertSame(Database::connect(':memory:'), Database::connect(':memory:'));
    }
}
