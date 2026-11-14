<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Repositories\SuggestionRepository;
use App\Utils\Access;

$app = App::boot();
$profile = $app->requireProfile();

// Le filtre (tout le monde, parents, filles) dit de quelle liste de films viennent les idees : il ne
// montre que celles nees de cette liste, et la prochaine demande s'appuie sur elle (rien d'autre ne change).
$public = (string) ($_GET['public'] ?? 'all');
$public = in_array($public, SuggestionRepository::SOURCES, true) ? $public : 'all';
$new = $app->suggestions->byStatus('new');

// Une demande en cours (lancee ici ou sur un autre telephone) : la page affiche ses etapes.
$progress = $app->settings->aiProgress();
if ($progress !== null && ($progress['state'] ?? '') === 'running'
    && time() - (int) ($progress['updated'] ?? 0) < \App\Services\AiSuggestionService::STALE_AFTER) {
    $progress['elapsed'] = max(0, time() - (int) ($progress['started'] ?? time()));
} else {
    $progress = null;
}

$app->render('popcorn', [
    'public' => $public,
    'publicCounts' => [
        'all' => count($new),
        'adult' => count(SuggestionRepository::filtered($new, 'adult')),
        'kid' => count(SuggestionRepository::filtered($new, 'kid')),
    ],
    'fresh' => SuggestionRepository::filtered($new, $public),
    'kept' => SuggestionRepository::filtered($app->suggestions->byStatus('kept'), $public),
    'rejected' => SuggestionRepository::filtered($app->suggestions->byStatus('rejected'), $public),
    'configured' => $app->popcorn->isConfigured(),
    'callsLeft' => max(0, \App\Services\AiSuggestionService::DAILY_LIMIT - $app->settings->aiCallsToday()),
    'startTime' => $app->settings->startTime(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
    'manageablePools' => Access::manageablePools((string) $profile['side']),
    'progress' => $progress,
    'lastAttempt' => $app->settings->lastAiAttempt(),
    'includeMemos' => $app->settings->aiIncludeMemos(),
], 'Filmi, Madame Pop-Corn');
