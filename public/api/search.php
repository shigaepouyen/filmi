<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\TmdbException;

$app = App::boot();
$app->requireProfile();

if (!$app->tmdb->isConfigured()) {
    $app->json(['results' => [], 'configured' => false]);
}

try {
    $results = $app->tmdb->search((string) ($_GET['q'] ?? ''));
} catch (TmdbException $e) {
    $app->json(['results' => [], 'configured' => true, 'error' => $e->getMessage()], 502);
}

$app->json(['results' => $results, 'configured' => true]);
