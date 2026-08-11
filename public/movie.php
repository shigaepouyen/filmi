<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
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
    // côté vue ne suffit pas, l'action est donc revérifiée ici.
    if (!Access::canManagePool((string) $profile['side'], (string) $movie['pool'])) {
        http_response_code(403);
        exit("Cette liste n'est pas la tienne, tu peux la consulter et voter.");
    }

    $action = (string) ($_POST['action'] ?? '');

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

    if ($action === 'reclassify') {
        $pool = ($_POST['pool'] ?? '') === 'kid' ? 'kid' : 'adult';
        $betType = in_array($_POST['bet_type'] ?? '', ['safe', 'discovery'], true)
            ? $_POST['bet_type']
            : null;

        // Deplacer un film vers une liste interdite serait un contournement de la
        // regle : une fille ne doit pas pouvoir pousser un film chez les parents.
        if (!Access::canManagePool((string) $profile['side'], $pool)) {
            http_response_code(403);
            exit("Tu ne peux pas deplacer un film vers cette liste.");
        }

        try {
            $app->movies->updateClassification($id, $pool, $betType);
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

$app->render('movie', [
    'movie' => $movie,
    'proposer' => $app->profiles->find((int) $movie['added_by']),
    'voters' => $app->votes->voters($id),
    'watchedOn' => $movie['status'] === 'watched' ? $app->seances->watchedDateForMovie($id) : null,
    'startTime' => $app->settings->startTime(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
    'canManage' => Access::canManagePool((string) $profile['side'], (string) $movie['pool']),
    'manageablePools' => Access::manageablePools((string) $profile['side']),
    'error' => $error,
], 'Filmi, ' . $movie['title']);
