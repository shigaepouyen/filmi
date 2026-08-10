<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\TmdbException;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $pool = ($_POST['pool'] ?? '') === 'kid' ? 'kid' : 'adult';
    $tmdbId = ctype_digit((string) ($_POST['tmdb_id'] ?? '')) ? (int) $_POST['tmdb_id'] : null;
    $memo = trim((string) ($_POST['memo'] ?? ''));
    $betType = in_array($_POST['bet_type'] ?? '', ['safe', 'discovery'], true)
        ? $_POST['bet_type']
        : null;

    // Le pool des parents exige un tag, c'est lui qui pilote le tirage.
    if ($pool === 'adult' && $betType === null) {
        $error = 'Choisis valeur sûre ou découverte, c est ce qui permet le tirage des trois films.';
    }

    if ($error === null) {
        $data = ['pool' => $pool, 'bet_type' => $betType, 'memo' => $memo, 'added_by' => (int) $profile['id']];

        if ($tmdbId !== null) {
            try {
                // La fiche est rechargée côté serveur : le navigateur ne dicte pas les métadonnées.
                $data = array_merge($app->tmdb->details($tmdbId), $data);
            } catch (TmdbException $e) {
                $error = 'TMDb est injoignable. Enregistre le film à la main pour le moment.';
            }
        } else {
            $data['title'] = trim((string) ($_POST['title'] ?? ''));
            $data['year'] = ctype_digit((string) ($_POST['year'] ?? '')) ? (int) $_POST['year'] : null;
            $data['runtime'] = ctype_digit((string) ($_POST['runtime'] ?? '')) ? (int) $_POST['runtime'] : null;
        }

        if ($error === null) {
            try {
                $app->movies->add($data);
                header('Location: /pool.php?pool=' . $pool);
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$app->render('add', [
    'error' => $error,
    'tmdbConfigured' => $app->tmdb->isConfigured(),
    'defaultPool' => $profile['side'] === 'kid' ? 'kid' : 'adult',
], 'Filmi, ajouter un film');
