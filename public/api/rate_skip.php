<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

/**
 * Fermer, ou rouvrir, sa ligne de note sur une seance.
 *
 * Fermer ne cree aucune note : la seance sort simplement de la liste des choses
 * a noter pour cette personne. Moyennes et palmares n'en savent rien.
 */

$app = App::boot();
$profile = $app->requireProfile();
$app->requirePost();

$seanceId = (int) ($_POST['seance_id'] ?? 0);
$profileId = (int) $profile['id'];
$intent = (string) ($_POST['intent'] ?? 'skip');

$seance = $app->seances->find($seanceId);
if ($seance === null || $seance['status'] !== 'done' || $seance['movie_id'] === null) {
    $app->json(['error' => 'Séance introuvable ou sans film.'], 404);
}

if ($intent === 'reopen') {
    $app->seances->reopenRating($seanceId, $profileId);
    $app->json(['skipped' => false]);
}

// Une note deja donnee compte dans la moyenne : la fermer laisserait croire
// qu'on ne s'est pas prononce. On refuse plutot que d'afficher un etat faux.
if ($app->seances->ratingFor($seanceId, $profileId) !== null) {
    $app->json(['error' => 'Tu as déjà noté cette séance.'], 409);
}

$app->seances->skipRating($seanceId, $profileId);

$app->json(['skipped' => true]);
