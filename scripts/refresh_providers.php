<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\TmdbException;

$app = App::boot();

if (!$app->tmdb->isConfigured()) {
    echo 'Aucune clé TMDb, rien à rafraîchir.' . PHP_EOL;
    exit(0);
}

$stale = $app->movies->staleProviders(7, 25);
if ($stale === []) {
    echo 'Tous les caches de plateformes sont à jour.' . PHP_EOL;
    exit(0);
}

$done = 0;
$failed = 0;

foreach ($stale as $movie) {
    try {
        $fresh = $app->tmdb->details((int) $movie['tmdb_id']);
        $app->movies->updateProviders(
            (int) $movie['id'],
            (string) $fresh['providers'],
            $fresh['certification'],
            $fresh['trailer_url'] ?? null
        );
        $done++;
    } catch (TmdbException $e) {
        // Un film en échec ne doit pas arrêter le lot : on continue et on compte.
        fwrite(STDERR, sprintf("Échec sur %s : %s%s", $movie['title'], $e->getMessage(), PHP_EOL));
        $failed++;
    }
    usleep(250000); // 4 requêtes par seconde, très en dessous des limites TMDb
}

printf('%d film(s) rafraîchi(s), %d en échec.%s', $done, $failed, PHP_EOL);
