<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
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
        if ($score >= 1 && $score <= 5) {
            $app->seances->rate((int) $seance['id'], (int) $profile['id'], $score);
        }
    }

    header('Location: ' . $redirectTo);
    exit;
}

$ratings = $app->seances->ratings((int) $seance['id']);

$app->render('seance', [
    'seance' => $seance,
    'movie' => $app->movies->find((int) $seance['movie_id']),
    'startTime' => $app->settings->startTime(),
    'ratings' => $ratings,
    'myScore' => (int) (array_column($ratings, 'score', 'profile_id')[(int) $profile['id']] ?? 0),
    'canVeto' => $profile['side'] === 'adult' && $seance['chooser_side'] === 'kid',
], 'Filmi, la séance');
