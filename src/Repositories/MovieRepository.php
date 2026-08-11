<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Utils\Providers;
use InvalidArgumentException;
use PDO;

final class MovieRepository
{
    private const POOLS = ['adult', 'kid'];
    private const BET_TYPES = ['safe', 'discovery'];

    private const COLUMNS = [
        'tmdb_id', 'title', 'original_title', 'year', 'runtime', 'poster_url',
        'overview', 'genres', 'director', 'tmdb_rating', 'certification',
        'providers', 'providers_at', 'trailer_url', 'pool', 'bet_type', 'memo', 'added_by',
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function add(array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Un film sans titre ne peut pas être ajouté.');
        }

        $pool = (string) ($data['pool'] ?? '');
        if (!in_array($pool, self::POOLS, true)) {
            throw new InvalidArgumentException('Pool inconnu : ' . $pool);
        }

        $betType = $data['bet_type'] ?? null;
        if ($pool === 'kid' || !in_array($betType, self::BET_TYPES, true)) {
            $betType = null;
        }

        $row = array_fill_keys(self::COLUMNS, null);
        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $row[$column] = $data[$column];
            }
        }
        $row['title'] = $title;
        $row['pool'] = $pool;
        $row['bet_type'] = $betType;

        $placeholders = implode(', ', array_map(static fn ($c) => ':' . $c, self::COLUMNS));
        $stmt = $this->db->prepare(
            'INSERT INTO movies (' . implode(', ', self::COLUMNS) . ") VALUES ({$placeholders})"
        );
        $stmt->execute($row);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM movies WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function pool(string $pool, string $sort = 'votes'): array
    {
        $order = match ($sort) {
            'recent' => 'm.created_at DESC, m.id DESC',
            'runtime' => 'CASE WHEN m.runtime IS NULL THEN 1 ELSE 0 END, m.runtime ASC, m.id',
            default => 'vote_count DESC, m.created_at DESC, m.id DESC',
        };

        $stmt = $this->db->prepare(
            'SELECT m.*,
                    p.name AS proposer_name,
                    p.avatar AS proposer_avatar,
                    p.color AS proposer_color,
                    (SELECT COUNT(*) FROM votes v WHERE v.movie_id = m.id) AS vote_count,
                    (SELECT GROUP_CONCAT(vp.name, \', \')
                       FROM votes v2
                       JOIN profiles vp ON vp.id = v2.profile_id
                      WHERE v2.movie_id = m.id) AS voter_names
               FROM movies m
               JOIN profiles p ON p.id = m.added_by
              WHERE m.pool = ? AND m.status = \'pool\'
              ORDER BY ' . $order
        );
        $stmt->execute([$pool]);

        return array_map(static function (array $row): array {
            $row['vote_count'] = (int) $row['vote_count'];
            $row['voter_names'] = (string) ($row['voter_names'] ?? '');

            return $row;
        }, $stmt->fetchAll());
    }

    public function drawCandidates(): array
    {
        return $this->db->query(
            'SELECT m.id, m.bet_type, m.title, m.year, m.runtime, m.poster_url,
                    m.overview, m.certification, m.providers, m.memo,
                    p.name AS proposer_name
               FROM movies m
               JOIN profiles p ON p.id = m.added_by
              WHERE m.pool = \'adult\' AND m.status = \'pool\''
        )->fetchAll();
    }

    public function findDuplicate(?int $tmdbId, string $title, ?int $year): ?array
    {
        if ($tmdbId !== null) {
            $stmt = $this->db->prepare($this->duplicateSelect() . ' WHERE m.tmdb_id = ? LIMIT 1');
            $stmt->execute([$tmdbId]);
            if ($found = $stmt->fetch()) {
                return $found;
            }
        }

        $needle = self::normaliseTitle($title);
        if ($needle === '') {
            return null;
        }

        $stmt = $this->db->prepare($this->duplicateSelect());
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            if (self::normaliseTitle((string) $row['title']) !== $needle) {
                continue;
            }
            if ($year !== null && $row['year'] !== null && (int) $row['year'] !== $year) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public function markWatched(int $id): void
    {
        $this->db->prepare("UPDATE movies SET status = 'watched' WHERE id = ?")->execute([$id]);
    }

    public function returnToPool(int $id): void
    {
        $this->db->prepare("UPDATE movies SET status = 'pool' WHERE id = ?")->execute([$id]);
    }

    /** @return array{safe: int, discovery: int} */
    public function countByBetType(): array
    {
        $counts = ['safe' => 0, 'discovery' => 0];

        $rows = $this->db->query(
            'SELECT bet_type, COUNT(*) AS total
               FROM movies
              WHERE pool = \'adult\' AND status = \'pool\' AND bet_type IS NOT NULL
              GROUP BY bet_type'
        )->fetchAll();

        foreach ($rows as $row) {
            $counts[$row['bet_type']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countPool(string $pool): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM movies WHERE pool = ? AND status = 'pool'");
        $stmt->execute([$pool]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Films dont le cache de plateformes est absent ou périmé.
     * Les plus périmés d'abord, pour qu'une exécution interrompue reprenne au bon endroit.
     */
    public function staleProviders(int $olderThanDays = 7, int $limit = 25): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, tmdb_id, title, providers_at
               FROM movies
              WHERE status = 'pool'
                AND tmdb_id IS NOT NULL
                AND (providers_at IS NULL
                     OR providers_at < datetime('now', ?))
              ORDER BY providers_at IS NOT NULL, providers_at ASC
              LIMIT ?"
        );
        $stmt->execute(['-' . max(0, $olderThanDays) . ' days', $limit]);

        return $stmt->fetchAll();
    }

    public function updateProviders(
        int $id,
        string $providersJson,
        ?string $certification = null,
        ?string $trailerUrl = null
    ): void {
        $this->db->prepare(
            'UPDATE movies
                SET providers = ?,
                    providers_at = CURRENT_TIMESTAMP,
                    certification = COALESCE(?, certification),
                    trailer_url = COALESCE(?, trailer_url)
              WHERE id = ?'
        )->execute([$providersJson, $certification, $trailerUrl, $id]);
    }

    /**
     * Archivage réversible : préserve l'historique et le palmarès, contrairement à
     * une suppression physique. Aucun contrôle d'accès ici, c'est à la page d'agir.
     */
    public function archive(int $id): void
    {
        $this->db->prepare("UPDATE movies SET status = 'archived' WHERE id = ?")->execute([$id]);
    }

    public function unarchive(int $id): void
    {
        $this->db->prepare("UPDATE movies SET status = 'pool' WHERE id = ?")->execute([$id]);
    }

    /** Films archivés d'un camp, les plus récemment ajoutés d'abord. */
    public function archivedList(string $pool): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*,
                    p.name AS proposer_name,
                    p.avatar AS proposer_avatar,
                    p.color AS proposer_color
               FROM movies m
               JOIN profiles p ON p.id = m.added_by
              WHERE m.pool = ? AND m.status = \'archived\'
              ORDER BY m.id DESC'
        );
        $stmt->execute([$pool]);

        return $stmt->fetchAll();
    }

    /**
     * Modifie le camp et le type de pari d'un film. Aucun contrôle d'accès ici
     * (c'est le rôle de la page), mais refuse une classification incohérente :
     * un pari sur la liste enfant, ou son absence sur la liste adulte.
     */
    public function updateClassification(int $id, string $pool, ?string $betType): void
    {
        if (!in_array($pool, self::POOLS, true)) {
            throw new InvalidArgumentException('Pool inconnu : ' . $pool);
        }
        // Deplacer un film vers la liste enfant retire son pari, sans erreur : le
        // pari ne sert qu'au tirage des parents, et refuser le deplacement pour
        // cette raison n'aurait aucun sens pour la personne qui le demande.
        // C'est le meme comportement que add(), qui force deja bet_type a null.
        if ($pool === 'kid') {
            $betType = null;
        }
        if ($pool === 'adult' && !in_array($betType, self::BET_TYPES, true)) {
            throw new InvalidArgumentException('La liste adulte exige un pari (sûr ou découverte).');
        }

        $this->db->prepare('UPDATE movies SET pool = ?, bet_type = ? WHERE id = ?')
            ->execute([$pool, $betType, $id]);
    }

    /**
     * Marques de plateformes distinctes réellement présentes sur les films non
     * archivés, pour peupler les réglages d'abonnement.
     *
     * @return list<array{brand: string, logo: ?string}>
     */
    public function providerBrands(): array
    {
        $rows = $this->db->query(
            "SELECT providers FROM movies
              WHERE providers IS NOT NULL AND status != 'archived'"
        )->fetchAll();

        $all = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['providers'], true);
            if (is_array($decoded)) {
                array_push($all, ...Providers::normalise($decoded));
            }
        }

        return Providers::brands($all);
    }

    private function duplicateSelect(): string
    {
        return 'SELECT m.*, (SELECT s.date FROM seances s WHERE s.movie_id = m.id
                              ORDER BY s.date DESC LIMIT 1) AS watched_on
                  FROM movies m';
    }

    /** Minuscules, accents repliés, ponctuation et espaces retirés. */
    private static function normaliseTitle(string $title): string
    {
        $lower = mb_strtolower(trim($title), 'UTF-8');
        $folded = strtr($lower, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);

        return (string) preg_replace('/[^a-z0-9]+/u', '', $folded);
    }
}
