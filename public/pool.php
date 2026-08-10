<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;

$app = App::boot();
$profile = $app->requireProfile();

$pool = ($_GET['pool'] ?? 'adult') === 'kid' ? 'kid' : 'adult';
$sort = in_array($_GET['sort'] ?? '', ['votes', 'recent', 'runtime'], true) ? $_GET['sort'] : 'votes';

$app->render('pool', [
    'pool' => $pool,
    'sort' => $sort,
    'movies' => $app->movies->pool($pool, $sort),
    'counts' => ['adult' => $app->movies->countPool('adult'), 'kid' => $app->movies->countPool('kid')],
    'startTime' => $app->settings->startTime(),
    'myVotes' => $app->votes->votedMovieIds((int) $profile['id']),
], 'Filmi, les listes');
