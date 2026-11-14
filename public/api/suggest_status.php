<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;

$app = App::boot();
$app->requireProfileJson();
session_write_close();   // lecture seule : ne retient jamais le verrou de session

// Avancement de la demande a Madame Pop-Corn, interroge par la page pendant qu'elle travaille.
// Lecture seule ; une demande restee « en cours » trop longtemps est donnee pour morte.
$progress = $app->settings->aiProgress();
if ($progress !== null) {
    $age = time() - (int) ($progress['updated'] ?? 0);
    if (($progress['state'] ?? '') === 'running' && $age >= \App\Services\AiSuggestionService::STALE_AFTER) {
        $progress['state'] = 'error';
        $progress['message'] = 'La demande a été interrompue.';
    }
    $progress['elapsed'] = max(0, ((int) ($progress['updated'] ?? time())) - ((int) ($progress['started'] ?? time())));
    if (($progress['state'] ?? '') === 'running') {
        $progress['elapsed'] = max(0, time() - (int) ($progress['started'] ?? time()));
    }
}

$app->json(['progress' => $progress]);
