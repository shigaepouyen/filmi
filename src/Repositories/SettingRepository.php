<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SettingRepository
{
    public const DEFAULT_START_TIME = '19:15';

    public function __construct(private PDO $db)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
    }

    public function startTime(): string
    {
        $value = (string) $this->get('default_start_time', self::DEFAULT_START_TIME);

        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)
            ? $value
            : self::DEFAULT_START_TIME;
    }
}
