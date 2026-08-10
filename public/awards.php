<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Services\AwardsService;

$app = App::boot();
$profile = $app->requireProfile();

$history = $app->seances->history(1000);

$years = array_values(array_unique(array_map(
    static fn (array $row): int => (int) substr((string) $row['date'], 0, 4),
    $history
)));
rsort($years);

$requested = (string) ($_GET['year'] ?? (string) (int) date('Y'));
$year = $requested === 'all' ? null : (int) $requested;

$awards = AwardsService::compute($history, $app->seances->vetoCounts(), $year);

$data = ['awards' => $awards, 'years' => $years, 'selected' => $requested];

if (isset($_GET['print'])) {
    // Page autonome : pas de layout, donc pas de navigation à l'impression.
    extract($data);
    require dirname(__DIR__) . '/views/awards_print.php';
    exit;
}

$app->render('awards', $data, 'Filmi, palmarès');
