<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();
$message = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $start = (string) ($_POST['start_time'] ?? '');
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start)) {
        $app->settings->set('default_start_time', $start);
    }

    // Section Madame Pop-Corn : le drapeau cache evite qu'un enregistrement fait
    // depuis un autre formulaire (ou par une fille) remette la case a zero. Seuls
    // les parents decident de ce qui part chez le modele.
    if (($_POST['ai_section'] ?? '') === '1' && ($profile['side'] ?? '') === 'adult') {
        $app->settings->setAiIncludeMemos(($_POST['ai_include_memos'] ?? '') === '1');
    }

    $brands = array_values(array_filter(array_map('strval', (array) ($_POST['brands'] ?? []))));
    $app->settings->setSubscribedBrands($brands);

    // Le depot leve une InvalidArgumentException sur un nom vide : on valide ici
    // pour afficher un message plutot que de laisser passer une erreur 500.
    $name = trim((string) ($_POST['name'] ?? $profile['name']));
    if ($name === '') {
        $message = "Le nom ne peut pas être vide.";
    } else {
        $app->profiles->update(
            (int) $profile['id'],
            $name,
            (string) ($_POST['avatar'] ?? $profile['avatar']),
            (string) ($_POST['color'] ?? $profile['color'])
        );

        header('Location: /settings.php?ok=1');
        exit;
    }
}

$app->render('settings', [
    'startTime' => $app->settings->startTime(),
    'saved' => isset($_GET['ok']),
    'message' => $message,
    'tmdbConfigured' => $app->tmdb->isConfigured(),
    'popcornConfigured' => $app->popcorn->isConfigured(),
    'aiIncludeMemos' => $app->settings->aiIncludeMemos(),
    'isAdult' => ($profile['side'] ?? '') === 'adult',
    'providerBrands' => $app->movies->providerBrands(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
    'refreshableWorks' => $app->movies->refreshableWorks(),
    'canRefresh' => ($profile['side'] ?? '') === 'adult',
], 'Filmi, réglages');
