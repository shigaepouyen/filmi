<?php
namespace App\Tests\Support;

use App\Utils\Database;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        Database::reset();
        $this->db = Database::connect(':memory:');
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    protected function seedProfiles(): void
    {
        $rows = [
            ['JC', 'jc', 'adult', 'blob', 'slate'],
            ['Élodie', 'elodie', 'adult', 'soucoupe', 'rose'],
            ['Zoé', 'zoe', 'kid', 'meduse', 'violet'],
            ['Soline', 'soline', 'kid', 'chauve', 'emerald'],
        ];
        $stmt = $this->db->prepare(
            'INSERT INTO profiles (name, slug, side, avatar, color) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }
}
