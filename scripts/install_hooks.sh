#!/usr/bin/env bash
# Installe le hook pre-commit qui refuse de commiter un secret.
# A relancer apres un clone, les hooks ne se versionnent pas.
set -euo pipefail

cd "$(dirname "$0")/.."

HOOK=.git/hooks/pre-commit

cat > "$HOOK" <<'HOOK_FIN'
#!/usr/bin/env bash
# Refuse tout commit contenant un secret, meme ajoute de force avec git add -f.
set -euo pipefail

echec=0

# 1. Le fichier de configuration local ne doit jamais etre indexe.
if git diff --cached --name-only | grep -qx 'config/config.php'; then
    echo "REFUS : config/config.php est dans l index. Il contient la cle TMDb." >&2
    echo "        Retirez-le avec : git rm --cached config/config.php" >&2
    echec=1
fi

# 2. La base de donnees de la famille non plus.
if git diff --cached --name-only | grep -qE '\.sqlite(-wal|-shm)?$'; then
    echo "REFUS : un fichier .sqlite est dans l index. Ce sont les donnees de la famille." >&2
    echec=1
fi

# 3. Une cle TMDb non vide ne doit apparaitre dans aucun fichier indexe,
#    y compris config.example.php ou un script laisse par erreur.
if git diff --cached -U0 | grep -qiE "^\+.*tmdb_api_key['\"]?[[:space:]]*=>[[:space:]]*['\"][a-z0-9]{8,}"; then
    echo "REFUS : une cle TMDb non vide apparait dans les modifications indexees." >&2
    git diff --cached -U0 --name-only | while read -r f; do
        if git show ":$f" 2>/dev/null | grep -qiE "tmdb_api_key['\"]?[[:space:]]*=>[[:space:]]*['\"][a-z0-9]{8,}"; then
            echo "        Fichier concerne : $f" >&2
        fi
    done
    echec=1
fi

if [ "$echec" -ne 0 ]; then
    echo >&2
    echo "Commit annule. Aucun secret n a ete enregistre." >&2
    exit 1
fi
HOOK_FIN

chmod +x "$HOOK"
echo "Hook installe : $HOOK"
