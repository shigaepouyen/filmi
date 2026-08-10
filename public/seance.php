<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\ScheduleService;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();

$date = ScheduleService::nextSeanceDate()->format('Y-m-d');
$seance = $app->seances->findByDate($date);

if ($seance === null || $seance['movie_id'] === null) {
    header('Location: /tonight.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'veto' && $profile['side'] === 'adult') {
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

    header('Location: /seance.php');
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
