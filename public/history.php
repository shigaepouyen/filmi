<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;

$app = App::boot();
$profile = $app->requireProfile();

$app->render('history', [
    'seances' => $app->seances->history(),
    'vetoes' => $app->seances->vetoCounts(),
    'myRatings' => $app->seances->myRatings((int) $profile['id']),
    'mySkippedRatings' => $app->seances->mySkippedRatings((int) $profile['id']),
], 'Filmi, historique');
