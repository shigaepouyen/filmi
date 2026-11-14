<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Services\LlmException;

$app = App::boot();
$app->requireProfileJson();
$app->requirePost();

// Le modele et TMDb se suivent : plus long qu'une page ordinaire, sans que ce
// temps soit celui de l'utilisateur devant un ecran fige (le bouton affiche l'attente).
set_time_limit(90);

try {
    $result = $app->popcorn->generate();
} catch (LlmException $e) {
    $app->json(['error' => $e->getMessage()], 502);
}

$app->json($result);
