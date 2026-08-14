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

    private const KINDS = ['film', 'series'];

    private const COLUMNS = [
        'tmdb_id', 'title', 'original_title', 'year', 'runtime', 'poster_url',
        'overview', 'genres', 'director', 'tmdb_rating', 'certification',
        'providers', 'providers_at', 'trailer_url', 'kind', 'season_count',
        'episode_count', 'episodes_per_evening', 'episodes_watched', 'episodes',
        'pool', 'bet_type', 'memo', 'added_by',
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

        // Chaque colonne est nommée explicitement dans l'INSERT ci-dessous : les
        // DEFAULT de schéma ne s'appliquent qu'aux colonnes omises, jamais à une
        // valeur explicitement NULL. Il faut donc les reproduire ici.
        $kind = $data['kind'] ?? 'film';
        $row['kind'] = in_array($kind, self::KINDS, true) ? $kind : 'film';
        $row['episodes_per_evening'] = isset($data['episodes_per_evening'])
            ? (int) $data['episodes_per_evening']
            : 2;
        $row['episodes_watched'] = isset($data['episodes_watched'])
            ? (int) $data['episodes_watched']
            : 0;

        $placeholders = implode(', ', array_map(static fn ($c) => ':' . $c, self::COLUMNS));
        $stmt = $this->db->prepare(
            'INSERT INTO movies (' . implode(', ', self::COLUMNS) . ") VALUES ({$placeholders})"
        );
        $stmt->execute($row);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Ajoute une série au pool. Accepte le tableau retourné par
     * TmdbService::seriesDetails() (episodes déjà encodé en JSON) ou un
     * tableau où 'episodes' est encore une liste PHP.
     *
     * Une série n'est jamais tirée au sort (drawCandidates() filtre sur
     * kind = 'film'), donc un pari n'aurait aucun sens : bet_type est toujours
     * forcé à null, quoi que le formulaire ait pu transmettre.
     *
     * @param array<string, mixed> $data
     */
    public function addSeries(array $data): int
    {
        $episodes = $data['episodes'] ?? [];
        if (is_array($episodes)) {
            $episodes = json_encode($episodes, JSON_UNESCAPED_UNICODE);
        }

        return $this->add(array_merge($data, [
            'kind' => 'series',
            'bet_type' => null,
            'episodes' => $episodes,
        ]));
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

    /**
     * Le tirage reste réservé aux films : tirer une série engagerait la
     * famille sur des mois de télévision sans l'avoir décidé.
     */
    public function drawCandidates(): array
    {
        return $this->db->query(
            'SELECT m.id, m.bet_type, m.title, m.year, m.runtime, m.poster_url,
                    m.overview, m.certification, m.providers, m.memo,
                    p.name AS proposer_name
               FROM movies m
               JOIN profiles p ON p.id = m.added_by
              WHERE m.pool = \'adult\' AND m.status = \'pool\' AND m.kind = \'film\''
        )->fetchAll();
    }

    /**
     * Avance la progression d'une série jusqu'à l'épisode $episodesTo inclus
     * dans sa suite continue, et la passe en 'watched' quand cet épisode est
     * le dernier. Sinon elle reste au pool : c'est ainsi qu'on reconnaît une
     * série en cours, la contrainte CHECK sur status ne pouvant pas accueillir
     * un nouveau statut.
     */
    public function advanceSeries(int $id, int $episodesTo): void
    {
        $movie = $this->find($id);
        if ($movie === null) {
            throw new InvalidArgumentException('Série inconnue : ' . $id);
        }

        $episodeCount = (int) ($movie['episode_count'] ?? 0);
        $status = $episodeCount > 0 && $episodesTo >= $episodeCount ? 'watched' : 'pool';

        $this->db->prepare('UPDATE movies SET episodes_watched = ?, status = ? WHERE id = ?')
            ->execute([$episodesTo, $status, $id]);
    }

    /** Réglage par série du nombre d'épisodes pris par soirée. */
    public function setEpisodesPerEvening(int $id, int $episodesPerEvening): void
    {
        if ($episodesPerEvening < 1) {
            throw new InvalidArgumentException('Le nombre d\'épisodes par soirée doit être au moins 1.');
        }

        $this->db->prepare('UPDATE movies SET episodes_per_evening = ? WHERE id = ?')
            ->execute([$episodesPerEvening, $id]);
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
     * Modifie le camp, le type de pari et, optionnellement, le proposeur d'un
     * film. Aucun contrôle d'accès ici (c'est le rôle de la page), mais refuse
     * une classification incohérente : un pari sur la liste enfant, ou son
     * absence sur la liste adulte, ou un proposeur qui n'existe pas.
     *
     * Une série n'a jamais de pari, quel que soit son camp : elle ne sort jamais
     * au tirage (drawCandidates() filtre kind = 'film'), donc l'exigence d'un
     * pari sur la liste adulte ne doit pas lui être opposée. Même logique que
     * add()/addSeries(), qui forcent déjà bet_type à null pour une série.
     *
     * Le proposeur n'est volontairement pas restreint au camp de la liste : un
     * parent ajoute légitimement un film pour une de ses filles, et doit pouvoir
     * le faire apparaître comme proposé par elle plutôt que par lui-même.
     */
    public function updateClassification(int $id, string $pool, ?string $betType, ?int $proposerId = null): void
    {
        if (!in_array($pool, self::POOLS, true)) {
            throw new InvalidArgumentException('Pool inconnu : ' . $pool);
        }

        $movie = $this->find($id);
        $isSeries = $movie !== null && ($movie['kind'] ?? 'film') === 'series';

        // Deplacer un film vers la liste enfant retire son pari, sans erreur : le
        // pari ne sert qu'au tirage des parents, et refuser le deplacement pour
        // cette raison n'aurait aucun sens pour la personne qui le demande.
        // C'est le meme comportement que add(), qui force deja bet_type a null.
        if ($pool === 'kid' || $isSeries) {
            $betType = null;
        }
        if ($pool === 'adult' && !$isSeries && !in_array($betType, self::BET_TYPES, true)) {
            throw new InvalidArgumentException('La liste adulte exige un pari (sûr ou découverte).');
        }

        if ($proposerId === null) {
            $this->db->prepare('UPDATE movies SET pool = ?, bet_type = ? WHERE id = ?')
                ->execute([$pool, $betType, $id]);

            return;
        }

        $stmt = $this->db->prepare('SELECT 1 FROM profiles WHERE id = ?');
        $stmt->execute([$proposerId]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('Profil inconnu : ' . $proposerId);
        }

        $this->db->prepare('UPDATE movies SET pool = ?, bet_type = ?, added_by = ? WHERE id = ?')
            ->execute([$pool, $betType, $proposerId, $id]);
    }

    /**
     * Série en cours dans ce pool, s'il y en a une. Une série en cours reste en
     * statut 'pool' (cf. advanceSeries()) et se reconnaît à episodes_watched > 0.
     * Sert à tonight.php pour la proposer en premier sur les samedis de son camp.
     */
    public function seriesInProgress(string $pool): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM movies
              WHERE pool = ? AND kind = 'series' AND status = 'pool' AND episodes_watched > 0
              ORDER BY id
              LIMIT 1"
        );
        $stmt->execute([$pool]);

        return $stmt->fetch() ?: null;
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
