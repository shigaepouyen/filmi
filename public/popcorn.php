<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Repositories\SuggestionRepository;
use App\Services\AiPrompt;
use App\Utils\Access;

$app = App::boot();
$profile = $app->requireProfile();

// Le filtre (toute la famille, les parents, les filles) sert a deux choses : ne montrer que les
// idees qui conviennent a ce public, et orienter la prochaine demande vers lui.
$public = (string) ($_GET['public'] ?? 'all');
$public = in_array($public, AiPrompt::AUDIENCES, true) ? $public : 'all';
$new = $app->suggestions->byStatus('new');

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
    'lastAttempt' => $app->settings->lastAiAttempt(),
    'includeMemos' => $app->settings->aiIncludeMemos(),
], 'Filmi, Madame Pop-Corn');
