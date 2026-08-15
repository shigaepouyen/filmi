<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

/**
 * Vote sur une oeuvre.
 *
 * Deux intentions possibles, parce que les deux ecrans n'ont pas le meme risque
 * d'erreur :
 *
 * - 'toggle', le comportement historique des listes : un clic pose ou retire le
 *   vote. La carte est grande, l'action est deliberee.
 * - 'add', utilise depuis l'historique : le vote se pose directement s'il
 *   n'existe pas, mais un vote deja pose n'est jamais retire par ce chemin. On
 *   renvoie 409 pour que l'interface demande confirmation avant de retirer, ce
 *   qui evite d'annuler un vote en faisant defiler une longue liste.
 */

$app = App::boot();
$profile = $app->requireProfile();
$app->requirePost();

$movieId = (int) ($_POST['movie_id'] ?? 0);
if ($app->movies->find($movieId) === null) {
    $app->json(['error' => 'Film inconnu.'], 404);
}

$profileId = (int) $profile['id'];
$intent = ($_POST['intent'] ?? 'toggle') === 'add' ? 'add' : 'toggle';

if ($intent === 'add' && $app->votes->hasVoted($movieId, $profileId)) {
    $app->json([
        'already_voted' => true,
        'voted' => true,
        'count' => $app->votes->count($movieId),
    ], 409);
}

$app->json($app->votes->toggle($movieId, $profileId));
