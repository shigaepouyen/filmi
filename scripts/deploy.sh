#!/usr/bin/env bash
# Deploie Filmi sur Infomaniak.
#
# L'hebergement mutualise ne permet pas de lancer composer, donc les dependances
# sont installees ici puis expediees dans le rsync. La sequence compte :
#   1. les tests, qui ont besoin des dependances de developpement
#   2. reinstallation sans les dependances de developpement, pour ne pas
#      expedier PHPUnit en production
#   3. le rsync, qui embarque vendor/
#   4. restauration des dependances de developpement en local
#
# Ne touche jamais data/ ni config/config.php, qui contiennent respectivement les
# vraies donnees de la famille et la cle TMDb.
set -euo pipefail

cd "$(dirname "$0")/.."

REMOTE=infomaniak-prod
REMOTE_PATH=sites/filmi.shi-ga.net

command -v rsync >/dev/null || { echo "rsync introuvable"; exit 1; }
command -v composer >/dev/null || { echo "composer introuvable"; exit 1; }

# Quoi qu'il arrive ensuite, on remet l'environnement local en etat de test.
restaurer_dev() {
    echo
    echo "Restauration des dependances de developpement en local..."
    composer install --quiet
}
trap restaurer_dev EXIT

echo "Verification des tests avant deploiement..."
vendor/bin/phpunit --no-progress

echo
echo "Installation des dependances de production (sans PHPUnit)..."
composer install --no-dev --optimize-autoloader --classmap-authoritative --quiet

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php absent apres composer install, deploiement annule." >&2
    exit 1
fi

echo
echo "Synchronisation vers ${REMOTE}:${REMOTE_PATH}..."
rsync -az --delete \
      --exclude '.git/' \
      --exclude '.superpowers/' \
      --include 'data/' \
      --include 'data/.htaccess' \
      --exclude 'data/*' \
      --exclude 'config/config.php' \
      --exclude 'tests/' \
      --exclude 'tmp/' \
      --exclude '.phpunit.cache/' \
      --exclude 'docs/' \
      --exclude '/index.html' \
      --exclude '/.infomaniak-maintenance.html' \
      --exclude '/.user.ini' \
      ./ "${REMOTE}:${REMOTE_PATH}/"

echo
echo "Application du schema sur le serveur (idempotent)..."
ssh "${REMOTE}" "cd ${REMOTE_PATH} && php scripts/init_db.php"

echo
echo "Deploye. Docroot a faire pointer sur ${REMOTE_PATH}/public si ce n est pas deja fait."
echo "Rappel : la cle TMDb n est jamais expediee, elle se pose une fois sur le serveur avec"
echo "         ssh ${REMOTE} \"cd ${REMOTE_PATH} && ./scripts/set_tmdb_key.sh\""
