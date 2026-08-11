<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VoteRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{voted: bool, count: int} état après bascule */
    public function toggle(int $movieId, int $profileId): array
    {
        if ($this->hasVoted($movieId, $profileId)) {
            $this->db->prepare('DELETE FROM votes WHERE movie_id = ? AND profile_id = ?')
                     ->execute([$movieId, $profileId]);
            $voted = false;
        } else {
            $this->db->prepare(
                'INSERT OR IGNORE INTO votes (movie_id, profile_id) VALUES (?, ?)'
            )->execute([$movieId, $profileId]);
            $voted = true;
        }

        return ['voted' => $voted, 'count' => $this->count($movieId)];
    }

    public function hasVoted(int $movieId, int $profileId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM votes WHERE movie_id = ? AND profile_id = ? LIMIT 1'
        );
        $stmt->execute([$movieId, $profileId]);

        return (bool) $stmt->fetchColumn();
    }

    public function count(int $movieId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM votes WHERE movie_id = ?');
        $stmt->execute([$movieId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<int> */
    public function votedMovieIds(int $profileId): array
    {
        $stmt = $this->db->prepare('SELECT movie_id FROM votes WHERE profile_id = ? ORDER BY movie_id');
        $stmt->execute([$profileId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Profils ayant voté pour ce film, pour l'affichage sur la fiche détaillée. */
    public function voters(int $movieId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.name, p.avatar, p.color
               FROM votes v JOIN profiles p ON p.id = v.profile_id
              WHERE v.movie_id = ?
              ORDER BY p.id'
        );
        $stmt->execute([$movieId]);

        return $stmt->fetchAll();
    }
}
