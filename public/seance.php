<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\RatingRules;
use App\Services\ScheduleService;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();

$idParam = $_GET['id'] ?? null;

if ($idParam !== null) {
    if (!ctype_digit((string) $idParam)) {
        header('Location: /tonight.php');
        exit;
    }
    $seance = $app->seances->find((int) $idParam);
} else {
    $date = ScheduleService::nextSeanceDate()->format('Y-m-d');
    $seance = $app->seances->findByDate($date);

    if ($seance === null || $seance['movie_id'] === null) {
        // Le dimanche matin, la « prochaine séance » calculée vise déjà samedi
        // prochain : la soirée d'hier n'aurait sinon nulle part où être notée.
        $seance = $app->seances->mostRecentDone();
    }
}

if ($seance === null || $seance['movie_id'] === null) {
    header('Location: /tonight.php');
    exit;
}

$redirectTo = '/seance.php' . ($idParam !== null ? '?id=' . (int) $seance['id'] : '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'veto' && $profile['side'] === 'adult' && $seance['chooser_side'] === 'kid') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $app->seances->recordVeto(
            (int) $seance['id'],
            (int) $seance['movie_id'],
            (int) $profile['id'],
            $reason === '' ? null : $reason
        );
        header('Location: /pool.php?pool=' . ($seance['chooser_side'] === 'kid' ? 'kid' : 'adult'));
        exit;
    }

    if ($action === 'rate') {
        $score = (int) ($_POST['score'] ?? 0);
        $ratedMovie = $app->movies->find((int) $seance['movie_id']);
        // Une série n'est notée qu'à son dernier épisode, une seule note sur
        // l'œuvre entière : les soirées intermédiaires ne doivent pas pouvoir
        // en enregistrer une, même par une requête forgée directement ici.
        $ratingAllowed = $ratedMovie !== null && RatingRules::isRatable(
            $seance['status'],
            $ratedMovie['kind'] ?? null,
            $ratedMovie['status'] ?? null
        );
        if ($score >= 1 && $score <= 5 && $ratingAllowed) {
            $app->seances->rate((int) $seance['id'], (int) $profile['id'], $score);
        }
    }

    // Fermer sa ligne de note : cette personne ne notera pas cette seance.
    // Une note deja donnee compte dans la moyenne, la fermer donnerait un etat
    // faux, donc on ne l'accepte que tant que rien n'est note.
    if ($action === 'skip_rating'
        && $app->seances->ratingFor((int) $seance['id'], (int) $profile['id']) === null
    ) {
        $app->seances->skipRating((int) $seance['id'], (int) $profile['id']);
    }

    if ($action === 'reopen_rating') {
        $app->seances->reopenRating((int) $seance['id'], (int) $profile['id']);
    }

    header('Location: ' . $redirectTo);
    exit;
}

$ratings = $app->seances->ratings((int) $seance['id']);
$ratingSkips = $app->seances->ratingSkips((int) $seance['id']);
$movie = $app->movies->find((int) $seance['movie_id']);

// Même règle que pour l'écriture ci-dessus : une série n'est notée qu'à son
// dernier épisode, une seule note pour l'œuvre entière.
$ratingAllowed = $movie !== null && RatingRules::isRatable(
    $seance['status'],
    $movie['kind'] ?? null,
    $movie['status'] ?? null
);

$app->render('seance', [
    'seance' => $seance,
    'movie' => $movie,
    'startTime' => $app->settings->startTime(),
    'ratings' => $ratings,
    'ratingSkips' => $ratingSkips,
    'ratingSkipped' => $app->seances->hasSkippedRating((int) $seance['id'], (int) $profile['id']),
    'myScore' => (int) (array_column($ratings, 'score', 'profile_id')[(int) $profile['id']] ?? 0),
    'canVeto' => $profile['side'] === 'adult' && $seance['chooser_side'] === 'kid',
    'ratingAllowed' => $ratingAllowed,
], 'Filmi, la séance');
