<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use Throwable;

final class SeanceRepository
{
    public function __construct(private PDO $db, private MovieRepository $movies)
    {
    }

    public function findByDate(string $date): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seances WHERE date = ?');
        $stmt->execute([$date]);

        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seances WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** Crée la séance du jour si besoin, sans jamais écraser un camp déjà fixé. */
    public function ensure(string $date, string $chooserSide): array
    {
        $existing = $this->findByDate($date);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $this->db->prepare(
                "INSERT INTO seances (date, chooser_side, status) VALUES (?, ?, 'planned')"
            )->execute([$date, $chooserSide]);
        } catch (\PDOException $e) {
            // Course perdue contre un autre appareil : la séance existe déjà,
            // la contrainte UNIQUE sur date a fait son travail. On relit.
            if ($this->findByDate($date) === null) {
                throw $e;
            }
        }

        return $this->findByDate($date);
    }

    public function setChooserSide(int $id, string $side, bool $derogation, ?string $note): void
    {
        $this->db->prepare(
            "UPDATE seances SET chooser_side = ?, derogation = ?, derogation_note = ? WHERE id = ? AND status = 'planned'"
        )->execute([$side, $derogation ? 1 : 0, $note, $id]);
    }

    public function skip(int $id): void
    {
        $this->db->prepare(
            "UPDATE seances SET status = 'skipped', movie_id = NULL WHERE id = ? AND status = 'planned'"
        )->execute([$id]);
    }

    public function unskip(int $id): void
    {
        $this->db->prepare(
            "UPDATE seances SET status = 'planned' WHERE id = ? AND status = 'skipped'"
        )->execute([$id]);
    }

    /**
     * Écrit la shortlist, le film retenu, le statut de la séance et le passage
     * du film en 'watched' en une seule transaction.
     *
     * Une shortlist vide est légitime : elle signifie qu'aucun tirage n'a eu
     * lieu (semaine des filles, qui choisissent dans tout le pool). Dans ce
     * cas le film retenu n'a pas besoin d'y figurer. Une shortlist non vide
     * reste, elle, contraignante : le film retenu doit en faire partie.
     *
     * @param list<int> $shortlistIds
     */
    public function recordChoice(int $seanceId, array $shortlistIds, int $chosenId): void
    {
        if ($shortlistIds !== [] && !in_array($chosenId, $shortlistIds, true)) {
            throw new InvalidArgumentException('Le film retenu doit figurer dans la shortlist.');
        }

        $this->db->beginTransaction();
        try {
            $previous = $this->find($seanceId);
            $previousMovieId = $previous !== null ? $previous['movie_id'] : null;

            $this->db->prepare('DELETE FROM seance_picks WHERE seance_id = ? AND role IN (?, ?)')
                     ->execute([$seanceId, 'shortlist', 'chosen']);

            $insert = $this->db->prepare(
                'INSERT INTO seance_picks (seance_id, movie_id, role) VALUES (?, ?, ?)'
            );
            foreach ($shortlistIds as $movieId) {
                $insert->execute([$seanceId, $movieId, 'shortlist']);
            }
            $insert->execute([$seanceId, $chosenId, 'chosen']);

            $this->db->prepare(
                "UPDATE seances SET status = 'done', movie_id = ? WHERE id = ?"
            )->execute([$chosenId, $seanceId]);

            $this->db->prepare("UPDATE movies SET status = 'watched' WHERE id = ?")
                     ->execute([$chosenId]);

            if ($previousMovieId !== null && (int) $previousMovieId !== $chosenId) {
                // Un second choix remplace le premier : le film abandonné retourne
                // au pool au lieu de rester orphelin en 'watched', et les notes
                // données au premier film ne doivent pas se retrouver créditées
                // au second.
                $this->movies->returnToPool((int) $previousMovieId);
                $this->db->prepare('DELETE FROM ratings WHERE seance_id = ?')->execute([$seanceId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Le film veto retourne au pool et la séance redevient sans film retenu. */
    public function recordVeto(int $seanceId, int $movieId, int $profileId, ?string $reason): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO seance_picks (seance_id, movie_id, role, by_profile_id, reason)
                 VALUES (?, ?, 'vetoed', ?, ?)"
            )->execute([$seanceId, $movieId, $profileId, $reason]);

            $this->db->prepare(
                "DELETE FROM seance_picks WHERE seance_id = ? AND movie_id = ? AND role = 'chosen'"
            )->execute([$seanceId, $movieId]);

            $this->db->prepare(
                "UPDATE seances SET status = 'planned', movie_id = NULL WHERE id = ? AND movie_id = ?"
            )->execute([$seanceId, $movieId]);

            // Le film vetoté se détache : les notes qu'il avait reçues ne doivent pas
            // se retrouver créditées au film qui le remplacera.
            $this->db->prepare('DELETE FROM ratings WHERE seance_id = ?')->execute([$seanceId]);

            $this->movies->returnToPool($movieId);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rate(int $seanceId, int $profileId, int $score): void
    {
        if ($score < 1 || $score > 5) {
            throw new InvalidArgumentException('La note doit aller de 1 à 5.');
        }

        $this->db->prepare(
            'INSERT INTO ratings (seance_id, profile_id, score) VALUES (?, ?, ?)
             ON CONFLICT(seance_id, profile_id) DO UPDATE SET score = excluded.score'
        )->execute([$seanceId, $profileId, $score]);
    }

    public function ratings(int $seanceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.profile_id, p.name, r.score
               FROM ratings r JOIN profiles p ON p.id = r.profile_id
              WHERE r.seance_id = ?
              ORDER BY p.id'
        );
        $stmt->execute([$seanceId]);

        return $stmt->fetchAll();
    }

    public function vetoCounts(): array
    {
        return $this->db->query(
            "SELECT p.name, COUNT(*) AS total
               FROM seance_picks sp JOIN profiles p ON p.id = sp.by_profile_id
              WHERE sp.role = 'vetoed'
              GROUP BY p.id
              ORDER BY total DESC, p.name"
        )->fetchAll();
    }

    /**
     * @return list<int> films en shortlist des dernières séances qui en avaient une
     *
     * Une séance « possède une shortlist » dès qu'elle a au moins une ligne de
     * rôle 'shortlist'. Une semaine des filles ne produit aucun tirage : elles
     * choisissent dans tout le pool, donc recordChoice() y est appelé avec une
     * shortlist vide et n'écrit aucune ligne 'shortlist'. Ces semaines ne
     * peuvent donc pas consommer un créneau de cooldown.
     */
    public function cooldownMovieIds(int $lastNDraws = 2): array
    {
        // $lastNDraws est typé int par la signature : jamais une chaîne venant d'une requête.
        // L'interpolation directe dans LIMIT ne peut donc pas être exploitée pour une injection SQL.
        $seanceIds = $this->db->query(
            "SELECT DISTINCT s.id
               FROM seances s
               JOIN seance_picks sp ON sp.seance_id = s.id AND sp.role = 'shortlist'
              ORDER BY s.date DESC
              LIMIT " . max(0, $lastNDraws)
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($seanceIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($seanceIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT movie_id FROM seance_picks
              WHERE role = 'shortlist' AND seance_id IN ({$placeholders})"
        );
        $stmt->execute($seanceIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function history(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*,
                    m.title AS movie_title,
                    m.poster_url AS movie_poster,
                    m.year AS movie_year,
                    p.name AS proposer_name,
                    (SELECT ROUND(AVG(r.score), 2) FROM ratings r WHERE r.seance_id = s.id) AS avg_score,
                    (SELECT COUNT(*) FROM seance_picks sp
                      WHERE sp.seance_id = s.id AND sp.role = \'vetoed\') AS veto_count
               FROM seances s
               LEFT JOIN movies m ON m.id = s.movie_id
               LEFT JOIN profiles p ON p.id = m.added_by
              ORDER BY s.date DESC
              LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }

    /** @return list<array{status:string,chooser_side:string}> */
    public function recentForSchedule(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, chooser_side FROM seances ORDER BY date DESC LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }
}
