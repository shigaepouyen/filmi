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
    'providerBrands' => $app->movies->providerBrands(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
], 'Filmi, réglages');
