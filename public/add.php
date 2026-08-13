<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\TmdbException;
use App\Utils\Access;
use App\Utils\Security;

$app = App::boot();
$profile = $app->requireProfile();
$error = null;
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Conservés pour réafficher le formulaire tel quel si une erreur de validation
// est levée plus bas : perdre la saisie est ce qui irrite le plus dans l'app.
$old = [
    // La liste postee est conservee telle quelle : c'est le controle d'acces plus
    // bas qui tranche. La ramener silencieusement a la liste du profil ecrirait
    // un film ailleurs que la ou la requete le demandait, sans rien dire.
    'pool' => $isPost && in_array($_POST['pool'] ?? '', Access::POOLS, true)
        ? (string) $_POST['pool']
        : (Access::manageablePools((string) $profile['side'])[0] ?? 'kid'),
    'kind' => $isPost && ($_POST['kind'] ?? '') === 'series' ? 'series' : 'film',
    'bet_type' => $isPost && in_array($_POST['bet_type'] ?? '', ['safe', 'discovery'], true) ? $_POST['bet_type'] : null,
    'memo' => $isPost ? trim((string) ($_POST['memo'] ?? '')) : '',
    'title' => $isPost ? trim((string) ($_POST['title'] ?? '')) : '',
    'year' => $isPost ? (string) ($_POST['year'] ?? '') : '',
    'runtime' => $isPost ? (string) ($_POST['runtime'] ?? '') : '',
    'episodes_per_evening' => $isPost && ctype_digit((string) ($_POST['episodes_per_evening'] ?? '')) && (int) $_POST['episodes_per_evening'] >= 1
        ? (string) $_POST['episodes_per_evening']
        : '2',
];

if ($isPost) {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $pool = $old['pool'];
    $kind = $old['kind'];
    $tmdbId = ctype_digit((string) ($_POST['tmdb_id'] ?? '')) ? (int) $_POST['tmdb_id'] : null;
    $memo = $old['memo'];
    $betType = $old['bet_type'];

    // Regle asymetrique : les parents alimentent les deux listes, les filles
    // seulement la leur. Verifie ici et pas seulement dans le formulaire, sinon
    // une requete forgee suffirait a contourner la regle.
    if (!Access::canManagePool((string) $profile['side'], $pool)) {
        http_response_code(403);
        exit("Tu ne peux ajouter un film que dans ta propre liste.");
    }

    // Le pool des parents exige un tag, c'est lui qui pilote le tirage. Une
    // série n'y est jamais soumise : elle ne sort jamais au tirage, donc lui
    // demander un pari n'aurait aucun sens et ne doit pas bloquer son ajout.
    if ($kind === 'film' && $pool === 'adult' && $betType === null) {
        $error = "Choisis valeur sûre ou découverte, c'est ce qui permet le tirage des trois films.";
    }

    if ($error === null && $kind === 'series') {
        if ($tmdbId === null) {
            $error = "Choisis une série dans les résultats de recherche, une série a besoin de sa liste d'épisodes.";
        } else {
            try {
                // La fiche est rechargée côté serveur : le navigateur ne dicte pas les métadonnées.
                $data = array_merge($app->tmdb->seriesDetails($tmdbId), [
                    'pool' => $pool,
                    'memo' => $memo,
                    'added_by' => (int) $profile['id'],
                    'episodes_per_evening' => (int) $old['episodes_per_evening'],
                ]);
            } catch (TmdbException $e) {
                $error = 'TMDb est injoignable. Réessaie dans un instant pour ajouter cette série.';
            }
        }

        if ($error === null) {
            try {
                $app->movies->addSeries($data);
                header('Location: /pool.php?pool=' . $pool);
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($error === null) {
        $data = ['pool' => $pool, 'bet_type' => $betType, 'memo' => $memo, 'added_by' => (int) $profile['id']];

        if ($tmdbId !== null) {
            try {
                // La fiche est rechargée côté serveur : le navigateur ne dicte pas les métadonnées.
                $data = array_merge($app->tmdb->details($tmdbId), $data);
            } catch (TmdbException $e) {
                $error = 'TMDb est injoignable. Enregistre le film à la main pour le moment.';
            }
        } else {
            $data['title'] = $old['title'];
            $data['year'] = ctype_digit($old['year']) ? (int) $old['year'] : null;
            $data['runtime'] = ctype_digit($old['runtime']) ? (int) $old['runtime'] : null;
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
    'manageablePools' => Access::manageablePools((string) $profile['side']),
    'old' => $old,
], 'Filmi, ajouter un film');
