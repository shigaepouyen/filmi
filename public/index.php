<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\App;
use App\Utils\Security;
use App\Utils\Session;

$app = App::boot();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::checkCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Requête refusée.');
    }

    $profile = $app->profiles->find((int) ($_POST['profile_id'] ?? 0));
    if ($profile !== null) {
        Session::setCurrentProfile((int) $profile['id']);
        header('Location: /tonight.php');
        exit;
    }
}

// Arriver ici volontairement remet le compteur à zéro : c'est le "ce n'est pas moi".
Session::clear();

$app->render('profile_choice', ['profiles' => $app->profiles->all()], 'Filmi, qui es-tu ?');
