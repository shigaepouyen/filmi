#!/usr/bin/env bash
# Rastérise l'icône SVG vers les formats attendus par iOS, Android et les navigateurs.
# Prérequis : inkscape (brew install inkscape).
set -euo pipefail

cd "$(dirname "$0")/.."
SRC=public/assets/icons/icon.svg
OUT=public/assets/icons

command -v inkscape >/dev/null || { echo "inkscape introuvable, lancer : brew install inkscape"; exit 1; }

inkscape "$SRC" --export-type=png --export-filename="$OUT/apple-touch-icon.png" -w 180 -h 180
inkscape "$SRC" --export-type=png --export-filename="$OUT/icon-192.png" -w 192 -h 192
inkscape "$SRC" --export-type=png --export-filename="$OUT/icon-512.png" -w 512 -h 512
inkscape public/assets/icons/favicon.svg --export-type=png \
         --export-filename="$OUT/favicon-32.png" -w 32 -h 32

echo "Icônes générées :"
ls -1 "$OUT"
