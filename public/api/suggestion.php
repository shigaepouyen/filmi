<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\App;
use App\Utils\Access;

$app = App::boot();
$profile = $app->requireProfileJson();
$app->requirePost();

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$suggestion = $id > 0 ? $app->suggestions->find($id) : null;

if ($suggestion === null) {
    $app->json(['error' => "Cette idée n'existe plus."], 404);
}

switch ($action) {
    case 'keep':
        $app->suggestions->setStatus($id, 'kept');
        break;
    case 'reject':
        $app->suggestions->setStatus($id, 'rejected');
        break;
    case 'restore':
        $app->suggestions->setStatus($id, 'new');
        break;
    case 'transfer':
        $pool = (string) ($_POST['pool'] ?? '');
        // Refus explicite plutot que repli : une liste inconnue ou interdite ne
        // doit jamais finir ecrite ailleurs que la ou la requete le demandait.
        if (!in_array($pool, Access::POOLS, true) || !Access::canManagePool((string) $profile['side'], $pool)) {
            $app->json(['error' => 'Tu ne peux ajouter un film que dans ta propre liste.'], 403);
        }
        try {
            $movieId = $app->popcorn->transfer($id, $pool, $profile);
        } catch (InvalidArgumentException $e) {
            $app->json(['error' => $e->getMessage()], 422);
        }
        $app->json(['ok' => true, 'movie_id' => $movieId, 'pool' => $pool]);
    default:
        $app->json(['error' => 'Action inconnue.'], 400);
}

$app->json(['ok' => true, 'status' => $app->suggestions->find($id)['status']]);
