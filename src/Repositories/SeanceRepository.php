<?php
declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
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

    /**
     * Le film veto retourne au pool et la séance redevient sans film retenu.
     *
     * Si la séance portait une soirée de série (episodes_from renseigné), cette
     * soirée n'a pas eu lieu : la progression doit revenir à ce qu'elle était
     * juste avant, en utilisant la borne figée sur la séance plutôt qu'en la
     * recalculant depuis la suite d'épisodes. Sans ce rattrapage, des épisodes
     * jamais regardés resteraient comptés vus, et si la soirée vetoée était la
     * dernière, la série resterait bloquée en 'watched' pour toujours.
     */
    public function recordVeto(int $seanceId, int $movieId, int $profileId, ?string $reason): void
    {
        $this->db->beginTransaction();
        try {
            $seance = $this->find($seanceId);
            $movie = $this->movies->find($movieId);

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

            if ($seance !== null
                && $seance['episodes_from'] !== null
                && $movie !== null
                && ($movie['kind'] ?? 'film') === 'series'
            ) {
                // episodes_from - 1 est la progression telle qu'elle était avant
                // cette soirée précise. advanceSeries() remet elle-même le statut
                // à 'pool' puisque cette valeur est nécessairement inférieure au
                // total d'épisodes, y compris quand la soirée vetoée finissait la
                // série.
                $this->movies->advanceSeries($movieId, max(0, (int) $seance['episodes_from'] - 1));

                $this->db->prepare(
                    'UPDATE seances SET episodes_from = NULL, episodes_to = NULL, episodes_label = NULL WHERE id = ?'
                )->execute([$seanceId]);
            }

            $this->movies->returnToPool($movieId);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Enregistre la soirée d'une série : la séance prend sa plage d'épisodes et
     * son libellé figé, la série avance sa progression et passe en 'watched'
     * quand c'était son dernier épisode. Tout ou rien, comme recordChoice().
     *
     * @param array{from:int, to:int, label:string, finishes?:bool} $evening
     *        typiquement le résultat de SeriesService::nextEvening()
     */
    public function recordSeriesEvening(int $seanceId, int $movieId, array $evening): void
    {
        $this->db->beginTransaction();
        try {
            // Avancer la série d'abord : si elle est inconnue, l'exception lève
            // avant toute écriture sur la séance, mais la transaction couvre
            // les deux de toute façon.
            $this->movies->advanceSeries($movieId, $evening['to']);

            $this->db->prepare(
                "UPDATE seances
                    SET status = 'done', movie_id = ?, episodes_from = ?, episodes_to = ?, episodes_label = ?
                  WHERE id = ?"
            )->execute([$movieId, $evening['from'], $evening['to'], $evening['label'], $seanceId]);

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
            "SELECT p.id AS profile_id, p.name, COUNT(*) AS total
               FROM seance_picks sp JOIN profiles p ON p.id = sp.by_profile_id
              WHERE sp.role = 'vetoed'
              GROUP BY p.id
              ORDER BY total DESC, p.name"
        )->fetchAll();
    }

    /**
     * Chaque note individuelle donnée, avec la date de la séance et le profil
     * qui note. Sert au palmarès pour distinguer qui note généreusement de qui
     * est plus sévère : history() ne renvoie qu'une moyenne par séance, jamais
     * le détail par personne.
     */
    public function ratingsHistory(): array
    {
        return $this->db->query(
            'SELECT s.date, r.profile_id, p.name, p.avatar, p.color, r.score
               FROM ratings r
               JOIN seances s ON s.id = r.seance_id
               JOIN profiles p ON p.id = r.profile_id
              ORDER BY s.date DESC'
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

    /**
     * Dernière séance réellement jouée, indépendamment de la date de « ce soir ».
     * Sert de repli le dimanche matin, quand la prochaine séance calculée vise
     * déjà samedi prochain et que la soirée d'hier n'a nulle part où être notée.
     */
    public function mostRecentDone(): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM seances WHERE status = 'done' ORDER BY date DESC LIMIT 1"
        );

        return $stmt->fetch() ?: null;
    }

    public function history(int $limit = 1000): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*,
                    m.title AS movie_title,
                    m.poster_url AS movie_poster,
                    m.year AS movie_year,
                    m.runtime AS movie_runtime,
                    m.kind AS movie_kind,
                    m.episodes AS movie_episodes,
                    m.added_by AS proposer_id,
                    p.name AS proposer_name,
                    p.avatar AS proposer_avatar,
                    p.color AS proposer_color,
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

    /** Date de la séance la plus récente ayant retenu ce film, ou null s'il n'a jamais été vu. */
    public function watchedDateForMovie(int $movieId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT date FROM seances WHERE movie_id = ? AND status = 'done' ORDER BY date DESC LIMIT 1"
        );
        $stmt->execute([$movieId]);
        $date = $stmt->fetchColumn();

        return $date === false ? null : (string) $date;
    }

    /**
     * @return list<array{status:string,chooser_side:string}>
     *
     * Filtre backfilled = 0 : un rattrapage est une réécriture du passé, pas une
     * vraie séance, et ne doit jamais entrer dans le calcul de l'alternance. Voir
     * ScheduleService::defaultChooserSide(), qui parcourt ce résultat par date
     * décroissante en supposant que la première ligne est la dernière vraie
     * séance jouée.
     */
    public function recentForSchedule(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, chooser_side FROM seances WHERE backfilled = 0 ORDER BY date DESC LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }

    /**
     * Enregistre une œuvre comme déjà vue à une date passée : une seule ligne,
     * `status = 'done'`, `backfilled = 1`, le camp pris du pool de l'œuvre plutôt
     * que saisi. Aucune ligne de shortlist n'est écrite, donc aucun créneau de
     * cooldown n'est consommé, exactement comme une semaine des filles.
     *
     * Pour une série, la totalité de la suite continue passe vue en un seul
     * geste : pas de saisie épisode par épisode pour du rattrapage.
     *
     * Lève BackfillException plutôt que de laisser filer une PDOException ou de
     * renvoyer un booléen ignorable : les pages l'attrapent pour afficher un
     * message clair au lieu d'une 500. Toute la validation vit ici, pas dans les
     * pages, pour n'avoir qu'un seul endroit qui décide ce qui est refusé.
     */
    public function recordBackfill(int $movieId, string $date): array
    {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsedDate === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new BackfillException('Choisis une date valide.');
        }

        if ($parsedDate > new DateTimeImmutable('today')) {
            throw new BackfillException("« Déjà vu » ne peut pas être daté dans le futur.");
        }

        $movie = $this->movies->find($movieId);
        if ($movie === null) {
            throw new BackfillException('Œuvre introuvable.');
        }
        if ($movie['status'] !== 'pool') {
            throw new BackfillException(sprintf(
                '« %s » a déjà sa séance, il ne peut pas être rattrapé une deuxième fois.',
                $movie['title']
            ));
        }

        $existing = $this->findByDate($date);
        if ($existing !== null) {
            throw new BackfillException(sprintf(
                'Il y a déjà une séance le %s : %s.',
                $date,
                $this->describeSeance($existing)
            ));
        }

        $isSeries = ($movie['kind'] ?? 'film') === 'series';
        $pool = (string) $movie['pool'];

        $this->db->beginTransaction();
        try {
            try {
                if ($isSeries) {
                    $episodeCount = max(0, (int) ($movie['episode_count'] ?? 0));
                    $label = $episodeCount . ' épisode' . ($episodeCount > 1 ? 's' : '');

                    $this->db->prepare(
                        "INSERT INTO seances (date, chooser_side, status, movie_id, backfilled, episodes_from, episodes_to, episodes_label)
                         VALUES (?, ?, 'done', ?, 1, ?, ?, ?)"
                    )->execute([
                        $date,
                        $pool,
                        $movieId,
                        $episodeCount > 0 ? 1 : null,
                        $episodeCount > 0 ? $episodeCount : null,
                        $label,
                    ]);
                } else {
                    $this->db->prepare(
                        "INSERT INTO seances (date, chooser_side, status, movie_id, backfilled)
                         VALUES (?, ?, 'done', ?, 1)"
                    )->execute([$date, $pool, $movieId]);
                }
            } catch (\PDOException $e) {
                // Course perdue contre un autre appareil entre la vérification et
                // l'écriture : la contrainte UNIQUE sur date a fait son travail.
                throw new BackfillException(sprintf('Il y a déjà une séance le %s.', $date), 0, $e);
            }

            if ($isSeries) {
                $this->movies->advanceSeries($movieId, max(0, (int) ($movie['episode_count'] ?? 0)));
            } else {
                $this->movies->markWatched($movieId);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findByDate($date);
    }

    private function describeSeance(array $seance): string
    {
        if ($seance['movie_id'] !== null) {
            $movie = $this->movies->find((int) $seance['movie_id']);
            if ($movie !== null) {
                return $movie['title'];
            }
        }

        return $seance['status'] === 'skipped' ? 'pas de ciné ce soir-là' : 'une séance déjà prévue';
    }
}
