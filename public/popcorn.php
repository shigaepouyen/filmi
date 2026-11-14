<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Utils\Access;

$app = App::boot();
$profile = $app->requireProfile();

$app->render('popcorn', [
    'fresh' => $app->suggestions->byStatus('new'),
    'kept' => $app->suggestions->byStatus('kept'),
    'rejected' => $app->suggestions->byStatus('rejected'),
    'configured' => $app->popcorn->isConfigured(),
    'callsLeft' => max(0, \App\Services\AiSuggestionService::DAILY_LIMIT - $app->settings->aiCallsToday()),
    'startTime' => $app->settings->startTime(),
    'subscribedBrands' => $app->settings->subscribedBrands(),
    'manageablePools' => Access::manageablePools((string) $profile['side']),
    'lastAttempt' => $app->settings->lastAiAttempt(),
    'includeMemos' => $app->settings->aiIncludeMemos(),
], 'Filmi, Madame Pop-Corn');
