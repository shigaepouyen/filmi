<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Repositories\BackfillException;
use App\Services\ScheduleService;
use App\Services\SeriesService;
use App\Utils\Access;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();

$id = (int) ($_REQUEST['id'] ?? 0);
$movie = $id > 0 ? $app->movies->find($id) : null;

if ($movie === null) {
    http_response_code(404);
    exit('Film introuvable.');
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    // Contrôle d'accès côté serveur. Règle asymétrique voulue : les parents
    // gèrent les deux listes, les filles seulement la leur. Masquer le bouton
    // côté vue ne suffit pas, l'action est donc revérifiée ici. Enregistrer une
    // soirée de série compte comme gérer, même règle.
    if (!Access::canManagePool((string) $profile['side'], (string) $movie['pool'])) {
        http_response_code(403);
        exit("Cette liste n'est pas la tienne, tu peux la consulter et voter.");
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'set_episodes_per_evening') {
        $perEvening = (int) ($_POST['episodes_per_evening'] ?? 0);
        if (($movie['kind'] ?? 'film') === 'series' && $perEvening >= 1) {
            $app->movies->setEpisodesPerEvening($id, $perEvening);
        }
        header('Location: /movie.php?id=' . $id);
        exit;
    }

    if ($action === 'record_evening') {
        $date = ScheduleService::nextSeanceDate()->format('Y-m-d');
        $defaultSide = ScheduleService::defaultChooserSide($app->seances->recentForSchedule());
        $seance = $app->seances->ensure($date, $defaultSide);

        // La plage d'épisodes n'est jamais lue depuis le formulaire : elle est
        // toujours recalculée ici, comme sur tonight.php.
        $eligible = ($movie['kind'] ?? 'film') === 'series'
            && $movie['status'] === 'pool'
            && $seance['status'] === 'planned'
            && $seance['chooser_side'] === $movie['pool'];

        if (!$eligible) {
            http_response_code(403);
            exit("Ce n'est pas le tour de cette liste, la soirée ne peut pas être enregistrée maintenant.");
        }

        $episodes = json_decode((string) $movie['episodes'], true);
        $episodes = is_array($episodes) ? $episodes : [];
        $evening = SeriesService::nextEvening(
            $episodes,
            (int) $movie['episodes_watched'],
            (int) $movie['episodes_per_evening']
        );

        $app->seances->recordSeriesEvening((int) $seance['id'], $id, $evening);
        header('Location: /seance.php');
        exit;
    }

    if ($action === 'backfill') {
        $date = (string) ($_POST['backfill_date'] ?? '');
        try {
            $app->seances->recordBackfill($id, $date);
            header('Location: /movie.php?id=' . $id);
            exit;
        } catch (BackfillException $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'archive') {
        $app->movies->archive($id);
        header('Location: /pool.php?pool=' . $movie['pool']);
        exit;
    }

    if ($action === 'unarchive') {
        $app->movies->unarchive($id);
        header('Location: /movie.php?id=' . $id);
        exit;
    }

    if ($action === 'move_watch' || $action === 'remove_watch') {
        $seance = $app->seances->watchSeanceForMovie($id);

        if ($seance === null) {
            $error = "Ce film n'a pas de date de visionnage a modifier.";
        } elseif ($action === 'remove_watch') {
            $app->seances->removeWatch((int) $seance['id']);
            header('Location: /movie.php?id=' . $id);
            exit;
        } else {
            try {
                $app->seances->moveSeance((int) $seance['id'], (string) ($_POST['watched_on'] ?? ''));
                header('Location: /movie.php?id=' . $id);
                exit;
            } catch (BackfillException $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($action === 'toggle_order') {
        // Echappatoire pour les sagas dont l'ordre de sortie n'est pas l'ordre de
        // visionnage, Star Wars typiquement. Sans elle l'application aurait tort
        // sans recours possible.
        $app->movies->setIgnoreOrder($id, ((int) ($movie['ignore_order'] ?? 0)) === 0);
        header('Location: /movie.php?id=' . $id);
        exit;
    }

    if ($action === 'reclassify') {
        $pool = ($_POST['pool'] ?? '') === 'kid' ? 'kid' : 'adult';
        $betType = in_array($_POST['bet_type'] ?? '', ['safe', 'discovery'], true)
            ? $_POST['bet_type']
            : null;
        // N'importe lequel des quatre profils peut être proposeur, quelle que
        // soit la liste : un parent ajoute légitimement un film pour une de ses
        // filles. updateClassification() vérifie que le profil existe vraiment.
        $proposerId = isset($_POST['added_by']) && $_POST['added_by'] !== ''
            ? (int) $_POST['added_by']
            : null;

        // Deplacer un film vers une liste interdite serait un contournement de la
        // regle : une fille ne doit pas pouvoir pousser un film chez les parents.
        if (!Access::canManagePool((string) $profile['side'], $pool)) {
            http_response_code(403);
            exit("Tu ne peux pas deplacer un film vers cette liste.");
        }

        try {
            $app->movies->updateClassification($id, $pool, $betType, $proposerId);
            header('Location: /movie.php?id=' . $id);
            exit;
        } catch (InvalidArgumentException $e) {
            // Le dépôt refuse une classification incohérente : on affiche l'erreur
            // au lieu de laisser passer une 500.
            $error = $e->getMessage();
        }
    }

    // La reclassification a pu échouer après avoir déjà changé d'autres champs
    // ailleurs : on relit pour ne jamais afficher un état périmé.
    $movie = $app->movies->find($id);
}

$seriesEvening = null;
$canRecordTonight = false;
if (($movie['kind'] ?? 'film') === 'series' && $movie['status'] === 'pool') {
    $episodes = json_decode((string) $movie['episodes'], true);
    $episodes = is_array($episodes) ? $episodes : [];
    $seriesEvening = SeriesService::nextEvening(
        $episodes,
        (int) $movie['episodes_watched'],
        (int) $movie['episodes_per_evening']
    );

    $scheduleDate = ScheduleService::nextSeanceDate()->format('Y-m-d');
    $scheduledSeance = $app->seances->ensure(
        $scheduleDate,
        ScheduleService::defaultChooserSide($app->seances->recentForSchedule())
    );
    $canRecordTonight = $scheduledSeance['status'] === 'planned' && $scheduledSeance['chooser_side'] === $movie['pool'];
}

$app->render('movie', [
    'movie' => $movie,
    'proposer' => $app->profiles->find((int) $movie['added_by']),
    'voters' => $app->votes->voters($id),
    'watchedOn' => $movie['status'] === 'watched' ? $app->seances->watchedDateForMovie($id) : null,
    'watchSeance' => $movie['status'] === 'watched' ? $app->seances->watchSeanceForMovie($id) : null,
    'startTime' => $app->settings->startTime(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
    'canManage' => Access::canManagePool((string) $profile['side'], (string) $movie['pool']),
    'blockedByFilm' => $app->movies->blockedBy($id),
    'manageablePools' => Access::manageablePools((string) $profile['side']),
    'allProfiles' => $app->profiles->all(),
    'seriesEvening' => $seriesEvening,
    'canRecordTonight' => $canRecordTonight,
    'error' => $error,
], 'Filmi, ' . $movie['title']);
