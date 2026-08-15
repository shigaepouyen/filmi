<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

/**
 * Note sur 5 d'une seance, depuis l'historique.
 *
 * La note se pose directement tant que la personne n'en a pas encore donne une.
 * Changer une note deja donnee demande une confirmation explicite : dans une
 * longue liste qu'on fait defiler, un doigt qui derape ne doit pas remplacer un
 * avis deja exprime. La regle est verifiee ici, pas seulement dans l'interface.
 */

$app = App::boot();
$profile = $app->requireProfile();
$app->requirePost();

$seanceId = (int) ($_POST['seance_id'] ?? 0);
$score = (int) ($_POST['score'] ?? 0);
$profileId = (int) $profile['id'];

$seance = $app->seances->find($seanceId);
if ($seance === null || $seance['status'] !== 'done' || $seance['movie_id'] === null) {
    $app->json(['error' => 'Séance introuvable ou sans film.'], 404);
}

if ($score < 1 || $score > 5) {
    $app->json(['error' => 'La note va de 1 à 5.'], 400);
}

$existante = $app->seances->ratingFor($seanceId, $profileId);
$remplace = ($_POST['intent'] ?? '') === 'replace';

if ($existante !== null && !$remplace) {
    $app->json([
        'already_rated' => true,
        'score' => $existante,
        'average' => $app->seances->averageFor($seanceId),
    ], 409);
}

$app->seances->rate($seanceId, $profileId, $score);

$app->json([
    'score' => $score,
    'average' => $app->seances->averageFor($seanceId),
]);
