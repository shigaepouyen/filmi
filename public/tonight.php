<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\ScheduleService;
use App\Services\SeriesService;
use App\Utils\Access;
use App\Utils\Config;
use App\Utils\FormatUtils;
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

    if ($action === 'continue_series') {
        $movieId = (int) ($_POST['movie_id'] ?? 0);
        $movie = $movieId > 0 ? $app->movies->find($movieId) : null;

        // La plage d'épisodes n'est jamais lue depuis le formulaire : elle est
        // toujours recalculée ici depuis l'état réel de la série, pour qu'une
        // requête forgée ne puisse pas imposer une plage arbitraire.
        $eligible = $movie !== null
            && ($movie['kind'] ?? 'film') === 'series'
            && $movie['status'] === 'pool'
            && $movie['pool'] === $seance['chooser_side']
            && $seance['status'] === 'planned';

        // Enregistrer une soirée, c'est gérer la liste de cette série : la règle
        // d'accès (parents gèrent tout, filles seulement leur liste) s'applique
        // ici comme partout ailleurs, vérifiée côté serveur.
        if (!$eligible || !Access::canManagePool((string) $profile['side'], (string) $movie['pool'])) {
            http_response_code(403);
            exit("Cette série n'est pas disponible pour cette soirée.");
        }

        $episodes = json_decode((string) $movie['episodes'], true);
        $episodes = is_array($episodes) ? $episodes : [];
        $evening = SeriesService::nextEvening(
            $episodes,
            (int) $movie['episodes_watched'],
            (int) $movie['episodes_per_evening']
        );

        $app->seances->recordSeriesEvening((int) $seance['id'], $movieId, $evening);

        header('Location: /seance.php');
        exit;
    }

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

$currentSeance = $app->seances->findByDate($date);
$seriesInProgress = null;
$seriesEvening = null;
$seriesEndTime = null;
if ($currentSeance !== null && $currentSeance['status'] === 'planned') {
    $seriesInProgress = $app->movies->seriesInProgress((string) $currentSeance['chooser_side']);
    if ($seriesInProgress !== null) {
        $episodes = json_decode((string) $seriesInProgress['episodes'], true);
        $episodes = is_array($episodes) ? $episodes : [];
        $seriesEvening = SeriesService::nextEvening(
            $episodes,
            (int) $seriesInProgress['episodes_watched'],
            (int) $seriesInProgress['episodes_per_evening']
        );
        $seriesEndTime = FormatUtils::endTime($app->settings->startTime(), $seriesEvening['total_runtime']);
    }
}

$app->render('tonight', [
    'seance' => $currentSeance,
    'defaultSide' => $defaultSide,
    'counts' => $counts,
    'adultTotal' => array_sum($counts),
    'kidTotal' => $app->movies->countPool('kid'),
    'lowPool' => array_sum($counts) < $threshold,
    'threshold' => $threshold,
    'seriesInProgress' => $seriesInProgress,
    'seriesEvening' => $seriesEvening,
    'seriesEndTime' => $seriesEndTime,
], 'Filmi, ce samedi');
