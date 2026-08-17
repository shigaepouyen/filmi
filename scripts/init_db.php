<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Utils\Config;
use App\Utils\Database;
use App\Utils\Migrations;

$path = (string) Config::get('db_path');
$fresh = !is_file($path);

$db = Database::connect($path);
$db->exec(Database::schemaSql());

$profiles = [
    ['JC', 'jc', 'adult', 'blob', 'slate'],
    ['Élodie', 'elodie', 'adult', 'soucoupe', 'rose'],
    ['Zoé', 'zoe', 'kid', 'meduse', 'violet'],
    ['Soline', 'soline', 'kid', 'chauve', 'emerald'],
];

$insert = $db->prepare(
    'INSERT OR IGNORE INTO profiles (name, slug, side, avatar, color)
     VALUES (?, ?, ?, ?, ?)'
);
foreach ($profiles as $profile) {
    $insert->execute($profile);
}

$db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)')
   ->execute(['default_start_time', (string) Config::get('default_start_time')]);

$applied = Migrations::run($db);

printf(
    "Base %s : %s%sProfils en base : %d%s%s",
    $path,
    $fresh ? 'créée' : 'mise à jour',
    PHP_EOL,
    (int) $db->query('SELECT COUNT(*) FROM profiles')->fetchColumn(),
    PHP_EOL,
    $applied === []
        ? 'Aucune migration à appliquer.' . PHP_EOL
        : 'Migrations appliquées : ' . implode(', ', $applied) . PHP_EOL
);
