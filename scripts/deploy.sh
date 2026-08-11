#!/usr/bin/env bash
# Deploie Filmi sur Infomaniak. Ne touche jamais data/ ni config/config.php,
# qui contiennent respectivement les vraies donnees de la famille et la cle TMDb.
set -euo pipefail

cd "$(dirname "$0")/.."

REMOTE=infomaniak-prod
REMOTE_PATH=sites/filmi.shi-ga.net

command -v rsync >/dev/null || { echo "rsync introuvable"; exit 1; }

echo "Verification des tests avant deploiement..."
vendor/bin/phpunit --no-progress

echo "Synchronisation vers ${REMOTE}:${REMOTE_PATH}..."
rsync -az --delete \
      --exclude '.git/' \
      --exclude '.superpowers/' \
      --exclude 'data/' \
      --exclude 'config/config.php' \
      --exclude 'tests/' \
      --exclude 'tmp/' \
      --exclude '.phpunit.cache/' \
      --exclude 'docs/' \
      ./ "${REMOTE}:${REMOTE_PATH}/"

echo "Application du schema sur le serveur (idempotent)..."
ssh "${REMOTE}" "cd ${REMOTE_PATH} && php scripts/init_db.php"

echo "Deploye. Docroot a faire pointer sur ${REMOTE_PATH}/public si ce n est pas deja fait."
