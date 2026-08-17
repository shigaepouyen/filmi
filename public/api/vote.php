<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

$app = App::boot();
$profile = $app->requireProfileJson();
$app->requirePost();

$movieId = (int) ($_POST['movie_id'] ?? 0);
if ($app->movies->find($movieId) === null) {
    $app->json(['error' => 'Film inconnu.'], 404);
}

$app->json($app->votes->toggle($movieId, (int) $profile['id']));
