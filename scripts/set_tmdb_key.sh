#!/usr/bin/env bash
# Ecrit la cle TMDb dans config/config.php sans qu'elle apparaisse a l'ecran,
# ni dans l'historique du shell, ni dans la liste des processus.
#
#   ./scripts/set_tmdb_key.sh
#
# La cle est demandee en saisie masquee. Elle est transmise a PHP par l'entree
# standard et jamais par un argument de ligne de commande, sinon elle serait
# visible dans "ps".
set -euo pipefail

cd "$(dirname "$0")/.."

CONFIG=config/config.php

if [ ! -f "$CONFIG" ]; then
    cp config/config.example.php "$CONFIG"
    chmod 600 "$CONFIG"
    echo "config/config.php cree depuis l'exemple."
fi

printf 'Cle TMDb v3 (saisie masquee, rien ne s affiche) : '
read -rs TMDB_KEY
printf '\n'

if [ -z "${TMDB_KEY}" ]; then
    echo "Aucune cle saisie, rien n a ete modifie." >&2
    exit 1
fi

# La cle passe par l'environnement du seul processus php, pas par argv.
TMDB_KEY="$TMDB_KEY" php -r '
$path = "config/config.php";
$config = (array) require $path;
$config["tmdb_api_key"] = getenv("TMDB_KEY");

$lignes = ["<?php", "", "// Fichier local, jamais versionne. Voir config/config.example.php.", "return ["];
foreach ($config as $cle => $valeur) {
    $lignes[] = sprintf("    %s => %s,", var_export($cle, true), var_export($valeur, true));
}
$lignes[] = "];";

file_put_contents($path, implode(PHP_EOL, $lignes) . PHP_EOL);
chmod($path, 0600);
'
unset TMDB_KEY

echo "Cle enregistree dans $CONFIG (permissions 600)."
echo

# Controle immediat : la cle est-elle reellement hors de portee de git ?
if git check-ignore -q "$CONFIG"; then
    echo "OK : $CONFIG est ignore par git."
else
    echo "ALERTE : $CONFIG n est PAS ignore par git. Ne commitez rien." >&2
    exit 1
fi

php -r '
$config = (array) require "config/config.php";
$cle = (string) ($config["tmdb_api_key"] ?? "");
printf(
    "OK : cle longue de %d caracteres, empreinte %s (les 4 derniers : %s)." . PHP_EOL,
    strlen($cle),
    substr(hash("sha256", $cle), 0, 8),
    substr($cle, -4)
);
'
