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
    public function taste(bool $includeMemos, string $source = 'all'): array
    {
        $labels = $this->labels();
        // La source dit de quelle liste viennent les films qui nourrissent les idees : celle des
        // parents, celle des filles, ou les deux. Rien d'autre ne change (meme prompt, memes
        // notes de tout le monde) ; « deja connues » et les vetos portent toujours sur tout.
        $pools = in_array($source, ['adult', 'kid'], true) ? [$source] : ['adult', 'kid'];

        $lists = [];
        foreach ($pools as $pool) {
            $lists[$pool] = $this->pool($pool, $includeMemos);
        }

        return [
            'seen' => $this->seen($labels, $pools),
            'lists' => $lists,
            'vetoes' => $this->vetoes($labels, $includeMemos),
            'known' => $this->known(),
            'memos' => $includeMemos,
        ];
    }

    /**
     * Pour remettre les prenoms dans ce que le modele a ecrit : il ne connait que
     * « Parent 1 » ou « Fille 2 », jamais un prenom (voir labels()).
     *
     * @return array<string, string> etiquette en minuscules ("parent 1") => prenom
     */
    public function namesByLabel(): array
    {
        $names = [];
        foreach ($this->profiles->all() as $profile) {
            $label = $this->labels()[(int) $profile['id']] ?? null;
            if ($label !== null) {
                $names[mb_strtolower($label)] = (string) $profile['name'];
            }
        }

        return $names;
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
     * Les oeuvres vues, la plus recente d'abord, avec la note de chacun. Seules celles qui
     * viennent des listes demandees : un film vu garde la liste dont il est issu.
     *
     * @param array<int, string> $labels
     * @param list<string> $pools
     * @return list<array<string, mixed>>
     */
    private function seen(array $labels, array $pools): array
    {
        $placeholders = implode(', ', array_fill(0, count($pools), '?'));
        $stmt = $this->db->prepare(
            "SELECT m.id, m.title, m.year, m.kind, MAX(s.date) AS last_date
               FROM seances s JOIN movies m ON m.id = s.movie_id
              WHERE s.status = 'done' AND m.pool IN ({$placeholders})
              GROUP BY m.id
              ORDER BY last_date DESC
              LIMIT " . self::SEEN_LIMIT
        );
        $stmt->execute($pools);
        $rows = $stmt->fetchAll();

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
