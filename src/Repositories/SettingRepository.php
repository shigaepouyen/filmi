<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SettingRepository
{
    public const DEFAULT_START_TIME = '19:15';
    private const SUBSCRIBED_BRANDS_KEY = 'subscribed_brands';

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

    /**
     * Périmètre d'abonnement de la famille, en noms de marque (voir Providers::brand()).
     *
     * @return list<string>
     */
    public function subscribedBrands(): array
    {
        $raw = $this->get(self::SUBSCRIBED_BRANDS_KEY);
        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    /** @param list<string> $brands */
    public function setSubscribedBrands(array $brands): void
    {
        $clean = [];
        foreach ($brands as $brand) {
            $value = trim((string) $brand);
            if ($value !== '' && !in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        $this->set(self::SUBSCRIBED_BRANDS_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE));
    }
}
