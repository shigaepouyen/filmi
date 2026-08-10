<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\DrawException;
use App\Services\DrawService;
use App\Services\ScheduleService;

$app = App::boot();
$app->requireProfile();
$app->requirePost();

$date = ScheduleService::nextSeanceDate()->format('Y-m-d');
$seance = $app->seances->findByDate($date);
if ($seance === null) {
    $app->json(['error' => 'Aucune séance en cours.'], 404);
}

$sessionKey = 'filmi_shown_' . (int) $seance['id'];
$shown = array_map('intval', $_SESSION[$sessionKey] ?? []);

try {
    $result = DrawService::pick(
        $app->movies->drawCandidates(),
        $app->seances->cooldownMovieIds(2),
        $shown
    );
} catch (DrawException $e) {
    $app->json([
        'error' => $e->getMessage(),
        'missing' => $e->missing,
        'available' => $e->available,
    ], 409);
}

$drawnIds = array_map(static fn (array $m): int => (int) $m['id'], $result['movies']);

// Un reset repart du pool complet : la mémoire de la soirée redémarre au tirage courant.
$_SESSION[$sessionKey] = $result['reset']
    ? $drawnIds
    : array_values(array_unique(array_merge($shown, $drawnIds)));

$app->json([
    'movies' => $result['movies'],
    'reset' => $result['reset'],
    'shown' => $_SESSION[$sessionKey],
]);
