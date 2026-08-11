<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Utils\Config;
use App\Utils\Database;
use App\Utils\Migrations;

$path = (string) Config::get('db_path');
$db = Database::connect($path);

$before = Migrations::currentVersion($db);
$applied = Migrations::run($db);

if ($applied === []) {
    printf('Base %s déjà à jour (version %d), rien à faire.%s', $path, $before, PHP_EOL);
    exit(0);
}

printf(
    "Base %s migrée : version %d -> %d (migration(s) %s).%s",
    $path,
    $before,
    Migrations::currentVersion($db),
    implode(', ', $applied),
    PHP_EOL
);
