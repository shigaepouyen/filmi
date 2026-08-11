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

if ($seance === null || $seance['status'] !== 'planned') {
    header('Location: /tonight.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $shortlist = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) ($_POST['shortlist'] ?? ''))
    )));
    $chosen = (int) ($_POST['movie_id'] ?? 0);

    if ($chosen > 0 && in_array($chosen, $shortlist, true)) {
        $app->seances->recordChoice((int) $seance['id'], $shortlist, $chosen);
        unset($_SESSION['filmi_shown_' . (int) $seance['id']]);
        header('Location: /seance.php');
        exit;
    }
}

$app->render('draw', [
    'seance' => $seance,
    'startTime' => $app->settings->startTime(),
], 'Filmi, le tirage');
