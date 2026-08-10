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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $movieId = (int) ($_POST['movie_id'] ?? 0);
    $choosable = $seance !== null
        && $seance['status'] === 'planned'
        && $seance['chooser_side'] === 'kid';

    if (($_POST['action'] ?? '') === 'choose' && $choosable && $movieId > 0) {
        // Semaine filles : aucune shortlist, il n'y a pas eu de tirage. Elles choisissent
        // dans tout le pool, donc cette séance ne consomme aucun créneau de cooldown.
        $app->seances->recordChoice((int) $seance['id'], [], $movieId);
        header('Location: /seance.php');
        exit;
    }

    header('Location: /pool.php?pool=kid');
    exit;
}

$pool = ($_GET['pool'] ?? 'adult') === 'kid' ? 'kid' : 'adult';
$sort = in_array($_GET['sort'] ?? '', ['votes', 'recent', 'runtime'], true) ? $_GET['sort'] : 'votes';

$app->render('pool', [
    'pool' => $pool,
    'sort' => $sort,
    'movies' => $app->movies->pool($pool, $sort),
    'counts' => ['adult' => $app->movies->countPool('adult'), 'kid' => $app->movies->countPool('kid')],
    'startTime' => $app->settings->startTime(),
    'myVotes' => $app->votes->votedMovieIds((int) $profile['id']),
    'kidWeek' => $seance !== null
        && $seance['status'] === 'planned'
        && $seance['chooser_side'] === 'kid',
], 'Filmi, les listes');
