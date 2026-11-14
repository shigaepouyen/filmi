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

    /**
     * Les memos libres partent-ils chez le modele ? Non par defaut : ils peuvent
     * contenir des details de famille, et rien ne les envoie sans un choix explicite.
     */
    public function aiIncludeMemos(): bool
    {
        return $this->get('ai_include_memos', '0') === '1';
    }

    public function setAiIncludeMemos(bool $include): void
    {
        $this->set('ai_include_memos', $include ? '1' : '0');
    }

    /**
     * La derniere demande a Madame Pop-Corn, la meme pour toute la famille : quand,
     * par qui, combien d'idees gardees, lesquelles ecartees et pourquoi, ou l'erreur
     * rencontree. Sans elle, une demande qui ne donne rien ne laisse aucune trace.
     *
     * @return array<string, mixed>|null
     */
    public function lastAiAttempt(): ?array
    {
        $raw = $this->get('ai_last_attempt');
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $attempt */
    public function setLastAiAttempt(array $attempt): void
    {
        $this->set('ai_last_attempt', json_encode($attempt, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Avancement de la demande en cours a Madame Pop-Corn, lisible par tous les telephones :
     * la page l'interroge pour afficher les etapes, et un second clic est refuse tant qu'elle
     * travaille. Ecrit a chaque etape par AiSuggestionService.
     *
     * @return array<string, mixed>|null
     */
    public function aiProgress(): ?array
    {
        $raw = $this->get('ai_progress');
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $progress */
    public function setAiProgress(array $progress): void
    {
        $this->set('ai_progress', json_encode($progress, JSON_UNESCAPED_UNICODE));
    }

    /** Nombre d'appels au modele deja faits aujourd'hui. */
    public function aiCallsToday(): int
    {
        return (int) $this->get('ai_calls_' . date('Y-m-d'), '0');
    }

    public function countAiCall(): void
    {
        // Une seule cle par jour : les anciennes sont nettoyees au passage pour
        // ne pas laisser une ligne de reglage par jour d'utilisation.
        $this->db->exec("DELETE FROM settings WHERE key LIKE 'ai_calls_%' AND key <> 'ai_calls_" . date('Y-m-d') . "'");
        $this->set('ai_calls_' . date('Y-m-d'), (string) ($this->aiCallsToday() + 1));
    }
}
