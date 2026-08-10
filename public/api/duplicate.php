<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

$app = App::boot();
$app->requireProfile();

$tmdbId = isset($_GET['tmdb_id']) && ctype_digit((string) $_GET['tmdb_id'])
    ? (int) $_GET['tmdb_id']
    : null;
$year = isset($_GET['year']) && ctype_digit((string) $_GET['year']) ? (int) $_GET['year'] : null;

$found = $app->movies->findDuplicate($tmdbId, (string) ($_GET['title'] ?? ''), $year);

if ($found === null) {
    $app->json(['duplicate' => null]);
}

$app->json(['duplicate' => [
    'title' => $found['title'],
    'status' => $found['status'],
    'pool' => $found['pool'],
    'watched_on' => $found['watched_on'] ?? null,
]]);
