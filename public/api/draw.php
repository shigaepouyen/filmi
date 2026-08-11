<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\DrawException;
use App\Services\DrawService;
use App\Services\ScheduleService;
use App\Utils\Providers;

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

// Le regroupement par marque et l'avertissement de périmètre sont calculés ici,
// une bonne fois, pour que la vue n'ait pas à dupliquer la logique de Providers
// en JavaScript.
$subscribedBrands = $app->settings->subscribedBrands();
$movies = array_map(static function (array $movie) use ($subscribedBrands): array {
    $raw = json_decode((string) ($movie['providers'] ?? '[]'), true);
    $raw = is_array($raw) ? $raw : [];

    // Meme regle que la carte : on n'envoie que les plateformes du perimetre
    // d'abonnement. Trois films cote a cote avec vingt plateformes chacun
    // rendraient l'ecran de tirage illisible sur une television.
    $display = Providers::displayBrands(Providers::brands($raw), $subscribedBrands);

    $movie['provider_brands'] = array_column($display['shown'], 'brand');
    $movie['needs_warning'] = $display['warning'];
    unset($movie['providers']);

    return $movie;
}, $result['movies']);

$app->json([
    'movies' => $movies,
    'reset' => $result['reset'],
    'shown' => $_SESSION[$sessionKey],
]);
