<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\TmdbException;

/**
 * Mise a jour manuelle des fiches, par petits lots.
 *
 * Rafraichir soixante-dix fiches en une seule requete depasserait le temps
 * d'execution PHP et le delai du serveur : chaque fiche demande un appel TMDb,
 * et une serie en demande un par saison. Le navigateur envoie donc des lots de
 * quelques identifiants et affiche la progression, ce qui rend l'attente
 * lisible plutot que suspecte.
 */

const LOT_MAX = 5;

$app = App::boot();
$profile = $app->requireProfileJson();
$app->requirePost();

// Action d'entretien, qui consomme le quota TMDb de la famille : reservee aux
// parents, verifie ici et pas seulement par le bouton masque.
if (($profile['side'] ?? '') !== 'adult') {
    $app->json(['error' => "Seuls les parents peuvent lancer la mise a jour des fiches."], 403);
}

if (!$app->tmdb->isConfigured()) {
    $app->json(['error' => 'Aucune cle TMDb configuree.'], 409);
}

$ids = array_values(array_filter(array_map(
    'intval',
    explode(',', (string) ($_POST['ids'] ?? ''))
)));

if ($ids === []) {
    $app->json(['error' => 'Aucune oeuvre a mettre a jour.'], 400);
}

$ids = array_slice($ids, 0, LOT_MAX);

$faits = [];
$echecs = [];

foreach ($ids as $id) {
    $movie = $app->movies->find($id);
    if ($movie === null || $movie['tmdb_id'] === null) {
        $echecs[] = ['id' => $id, 'title' => $movie['title'] ?? 'inconnu', 'raison' => 'sans fiche TMDb'];
        continue;
    }

    try {
        if (($movie['kind'] ?? 'film') === 'series') {
            // Une serie garde sa progression : seules les metadonnees changent.
            $fresh = $app->tmdb->seriesDetails((int) $movie['tmdb_id']);
            $app->movies->updateSeriesMetadata($id, $fresh);
        } else {
            $fresh = $app->tmdb->details((int) $movie['tmdb_id']);
            $app->movies->updateProviders(
                $id,
                (string) $fresh['providers'],
                $fresh['certification'] ?? null,
                $fresh['trailer_url'] ?? null,
                $fresh['collection_id'] ?? null,
                $fresh['collection_name'] ?? null,
                $fresh['collection_rank'] ?? null
            );
        }

        $faits[] = ['id' => $id, 'title' => $movie['title']];
    } catch (TmdbException $e) {
        // Un echec isole ne doit pas interrompre la serie de lots.
        $echecs[] = ['id' => $id, 'title' => $movie['title'], 'raison' => $e->getMessage()];
    }
}

$app->json(['faits' => $faits, 'echecs' => $echecs]);
