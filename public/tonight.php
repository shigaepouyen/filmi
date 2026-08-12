<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\ScheduleService;
use App\Utils\Config;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();

$date = ScheduleService::nextSeanceDate()->format('Y-m-d');
$defaultSide = ScheduleService::defaultChooserSide($app->seances->recentForSchedule());
$seance = $app->seances->ensure($date, $defaultSide);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'flip') {
        $note = trim((string) ($_POST['note'] ?? ''));
        $nouveauCamp = ScheduleService::opposite((string) $seance['chooser_side']);

        // Une derogation, c'est s'ecarter de l'alternance naturelle. Revenir sur
        // le camp que l'alternance designait annule donc la derogation au lieu
        // d'en poser une seconde : sinon le badge reste colle pour toujours et
        // ne veut plus rien dire.
        $estUneDerogation = ScheduleService::isDerogation($nouveauCamp, $defaultSide);

        $app->seances->setChooserSide(
            (int) $seance['id'],
            $nouveauCamp,
            $estUneDerogation,
            $estUneDerogation && $note !== '' ? $note : null
        );
    } elseif ($action === 'skip') {
        $app->seances->skip((int) $seance['id']);
    } elseif ($action === 'unskip') {
        $app->seances->unskip((int) $seance['id']);
    }

    header('Location: /tonight.php');
    exit;
}

$counts = $app->movies->countByBetType();
$threshold = (int) Config::get('low_pool_threshold', 5);

$app->render('tonight', [
    'seance' => $app->seances->findByDate($date),
    'defaultSide' => $defaultSide,
    'counts' => $counts,
    'adultTotal' => array_sum($counts),
    'kidTotal' => $app->movies->countPool('kid'),
    'lowPool' => array_sum($counts) < $threshold,
    'threshold' => $threshold,
], 'Filmi, ce samedi');
