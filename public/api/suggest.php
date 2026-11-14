<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\LlmException;

$app = App::boot();
$profile = $app->requireProfileJson();
$app->requirePost();

// La session PHP verrouille son fichier jusqu'a la fin de la requete : sans cela, la page ne pourrait
// pas interroger l'avancement (autre requete, meme session) pendant ces dizaines de secondes. Le jeton
// CSRF a deja ete verifie, rien d'autre n'est ecrit en session ici.
session_write_close();

// Le modele et TMDb se suivent : plus long qu'une page ordinaire, sans que ce
// temps soit celui de l'utilisateur devant un ecran fige (le bouton affiche l'attente).
set_time_limit(150);
// Si le telephone se met en veille ou ferme la page, le travail va quand meme a son terme et reste
// enregistre pour toute la famille (sinon la demande serait payee et perdue).
ignore_user_abort(true);

try {
    $result = $app->popcorn->generate((string) $profile['name'], (string) ($_POST['source'] ?? 'all'));
} catch (LlmException $e) {
    $app->json(['error' => $e->getMessage()], 502);
}

$app->json($result);
