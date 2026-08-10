<?php
// Copier ce fichier en config/config.php puis renseigner la clé TMDb.
// Clé v3 gratuite : compte sur themoviedb.org, puis Paramètres > API.
// Sans clé, Filmi fonctionne en saisie manuelle uniquement.
return [
    'tmdb_api_key' => '',
    'tmdb_language' => 'fr-FR',
    'tmdb_region' => 'FR',
    'default_start_time' => '19:15',
    'low_pool_threshold' => 5,
];
