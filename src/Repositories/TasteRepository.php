<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Ce que la famille aime, extrait de la base pour nourrir Madame Pop-Corn.
 *
 * Aucun prenom ne sort d'ici : chaque profil devient une etiquette neutre
 * (« Parent 1 », « Fille 2 »). Les memos et les raisons de veto, qui sont du texte
 * libre, ne sortent que si $includeMemos est vrai.
 */
final class TasteRepository
{
    private const SEEN_LIMIT = 60;
    private const LIST_LIMIT = 80;
    private const KNOWN_LIMIT = 300;
    private const FREE_TEXT_MAX = 160;

    public function __construct(private PDO $db, private ProfileRepository $profiles)
    {
    }

    /**
     * @return array{
     *   seen: list<array<string, mixed>>,
     *   lists: array{adult: list<array<string, mixed>>, kid: list<array<string, mixed>>},
     *   vetoes: list<array<string, mixed>>,
     *   known: list<string>,
     *   memos: bool
     * }
     */
    public function taste(bool $includeMemos): array
    {
        $labels = $this->labels();

        return [
            'seen' => $this->seen($labels),
            'lists' => [
                'adult' => $this->pool('adult', $includeMemos),
                'kid' => $this->pool('kid', $includeMemos),
            ],
            'vetoes' => $this->vetoes($labels, $includeMemos),
            'known' => $this->known(),
            'memos' => $includeMemos,
        ];
    }

    /** @return array<int, string> identifiant de profil => etiquette neutre */
    private function labels(): array
    {
        $counts = ['adult' => 0, 'kid' => 0];
        $labels = [];
        foreach ($this->profiles->all() as $profile) {
            $side = (string) $profile['side'];
            $counts[$side]++;
            $labels[(int) $profile['id']] = ($side === 'adult' ? 'Parent ' : 'Fille ') . $counts[$side];
        }

        return $labels;
    }

    /**
     * Les oeuvres vues, la plus recente d'abord, avec la note de chacun.
     *
     * @param array<int, string> $labels
     * @return list<array<string, mixed>>
     */
    private function seen(array $labels): array
    {
        $rows = $this->db->query(
            "SELECT m.id, m.title, m.year, m.kind, MAX(s.date) AS last_date
               FROM seances s JOIN movies m ON m.id = s.movie_id
              WHERE s.status = 'done'
              GROUP BY m.id
              ORDER BY last_date DESC
              LIMIT " . self::SEEN_LIMIT
        )->fetchAll();

        if ($rows === []) {
            return [];
        }

        // Une serie ne se note qu'a son dernier episode : on rassemble donc les
        // notes de toutes les seances d'une meme oeuvre.
        $scores = [];
        $stmt = $this->db->query(
            "SELECT s.movie_id, r.profile_id, r.score
               FROM ratings r JOIN seances s ON s.id = r.seance_id
              WHERE s.status = 'done'"
        );
        foreach ($stmt as $row) {
            $who = $labels[(int) $row['profile_id']] ?? null;
            if ($who !== null) {
                $scores[(int) $row['movie_id']][$who] = (int) $row['score'];
            }
        }

        $seen = [];
        foreach ($rows as $row) {
            $seen[] = [
                'title' => (string) $row['title'],
                'year' => $row['year'] === null ? null : (int) $row['year'],
                'kind' => (string) $row['kind'],
                'ratings' => $scores[(int) $row['id']] ?? [],
            ];
        }

        return $seen;
    }

    /** @return list<array<string, mixed>> */
    private function pool(string $pool, bool $includeMemos): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.title, m.year, m.kind, m.memo,
                    (SELECT COUNT(*) FROM votes v WHERE v.movie_id = m.id) AS votes
               FROM movies m
              WHERE m.status = 'pool' AND m.pool = ?
              ORDER BY votes DESC, m.id DESC
              LIMIT " . self::LIST_LIMIT
        );
        $stmt->execute([$pool]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $item = [
                'title' => (string) $row['title'],
                'year' => $row['year'] === null ? null : (int) $row['year'],
                'kind' => (string) $row['kind'],
                'votes' => (int) $row['votes'],
            ];
            if ($includeMemos && trim((string) $row['memo']) !== '') {
                $item['memo'] = mb_substr(trim((string) $row['memo']), 0, self::FREE_TEXT_MAX);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<int, string> $labels
     * @return list<array<string, mixed>>
     */
    private function vetoes(array $labels, bool $includeMemos): array
    {
        $rows = $this->db->query(
            "SELECT m.title, m.year, sp.by_profile_id, sp.reason
               FROM seance_picks sp JOIN movies m ON m.id = sp.movie_id
              WHERE sp.role = 'vetoed'
              ORDER BY sp.id DESC LIMIT 20"
        )->fetchAll();

        $vetoes = [];
        foreach ($rows as $row) {
            $veto = [
                'title' => (string) $row['title'],
                'year' => $row['year'] === null ? null : (int) $row['year'],
                'by' => $labels[(int) $row['by_profile_id']] ?? 'Parent',
            ];
            if ($includeMemos && trim((string) $row['reason']) !== '') {
                $veto['reason'] = mb_substr(trim((string) $row['reason']), 0, self::FREE_TEXT_MAX);
            }
            $vetoes[] = $veto;
        }

        return $vetoes;
    }

    /**
     * Tout ce qui existe deja dans les listes, y compris archive : le modele ne
     * doit rien reproposer de cela.
     *
     * @return list<string>
     */
    private function known(): array
    {
        $stmt = $this->db->query(
            'SELECT title, year FROM movies ORDER BY id DESC LIMIT ' . self::KNOWN_LIMIT
        );

        return array_map(
            static fn (array $r): string => $r['title'] . ($r['year'] !== null ? ' (' . (int) $r['year'] . ')' : ''),
            $stmt->fetchAll()
        );
    }
}
