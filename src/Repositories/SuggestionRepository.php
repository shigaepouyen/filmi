<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * La liste de Madame Pop-Corn. Rien ici n'est dans movies : une suggestion ne
 * rejoint une liste de la famille que par un transfert explicite.
 */
final class SuggestionRepository
{
    public const STATUSES = ['new', 'kept', 'rejected', 'transferred'];

    private const COLUMNS = [
        'tmdb_id', 'kind', 'title', 'original_title', 'year', 'runtime', 'poster_url',
        'overview', 'genres', 'director', 'tmdb_rating', 'certification', 'providers',
        'providers_at', 'trailer_url', 'season_count', 'episode_count', 'audience',
        'source', 'bet_type', 'why',
    ];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Ajoute une idee, sauf si cette oeuvre a deja ete suggeree (quel que soit son
     * sort depuis : une idee ecartee ne doit pas revenir).
     *
     * @param array<string, mixed> $data
     * @return int|null l'identifiant, ou null si l'oeuvre etait deja connue
     */
    public function add(array $data): ?int
    {
        $row = array_fill_keys(self::COLUMNS, null);
        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $row[$column] = $data[$column];
            }
        }

        $row['kind'] = ($row['kind'] ?? 'film') === 'series' ? 'series' : 'film';
        $row['audience'] = in_array($row['audience'], ['all', 'kid', 'adult'], true) ? $row['audience'] : 'all';
        // NOT NULL : une valeur absente doit retomber sur « all », pas sur NULL (l'INSERT OR IGNORE
        // avalerait alors l'erreur et l'idee serait perdue sans un mot).
        $row['source'] = in_array($row['source'], self::SOURCES, true) ? $row['source'] : 'all';
        $row['bet_type'] = in_array($row['bet_type'], ['safe', 'discovery'], true) ? $row['bet_type'] : null;
        $row['title'] = mb_substr(trim((string) ($row['title'] ?? '')), 0, MovieRepository::TITLE_MAX);
        $row['why'] = $row['why'] === null ? null : mb_substr(trim((string) $row['why']), 0, 300);

        if ($row['title'] === '' || empty($row['tmdb_id'])) {
            return null;
        }

        $placeholders = implode(', ', array_map(static fn ($c) => ':' . $c, self::COLUMNS));
        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO suggestions (' . implode(', ', self::COLUMNS) . ") VALUES ({$placeholders})"
        );
        $stmt->execute($row);

        return $stmt->rowCount() === 0 ? null : (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM suggestions WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> les plus recentes d'abord */
    public const SOURCES = ['all', 'adult', 'kid'];

    /**
     * Filtre de la page Pop-Corn : « Tout le monde » montre toutes les idees ; « Parents » (ou
     * « Filles ») celles qui sont nees de la liste des parents (ou des filles).
     */
    public static function matchesFilter(?string $source, string $filter): bool
    {
        return in_array($filter, ['adult', 'kid'], true) ? $source === $filter : true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function filtered(array $rows, string $filter): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => self::matchesFilter($row['source'] ?? 'all', $filter)
        ));
    }

    public function byStatus(string $status): array
    {
        $stmt = $this->db->prepare('SELECT * FROM suggestions WHERE status = ? ORDER BY id DESC');
        $stmt->execute([$status]);

        return $stmt->fetchAll();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->query('SELECT status, COUNT(*) AS n FROM suggestions GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Garder, ecarter ou rouvrir une idee. Une idee deja transferee ne bouge plus. */
    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['new', 'kept', 'rejected'], true)) {
            throw new \InvalidArgumentException('Statut inconnu : ' . $status);
        }

        $this->db->prepare(
            "UPDATE suggestions SET status = ? WHERE id = ? AND status <> 'transferred'"
        )->execute([$status, $id]);
    }

    public function markTransferred(int $id, int $movieId): void
    {
        $this->db->prepare(
            "UPDATE suggestions SET status = 'transferred', movie_id = ? WHERE id = ?"
        )->execute([$movieId, $id]);
    }

    /** Une oeuvre deja suggeree, quel que soit son sort. */
    public function exists(int $tmdbId, string $kind): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM suggestions WHERE tmdb_id = ? AND kind = ?');
        $stmt->execute([$tmdbId, $kind === 'series' ? 'series' : 'film']);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Titres deja suggeres, pour que le modele ne les redonne pas.
     *
     * @return list<string>
     */
    public function knownTitles(int $limit = 150): array
    {
        $stmt = $this->db->prepare('SELECT title FROM suggestions ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
