#!/bin/sh
# Mise à jour hebdomadaire des épisodes/saisons pour les animes et séries.
set -e

SCRIPT="/var/www/html/cli/fetch_metadata.py"
ANIME_DIR="${ANIMES_PATH:-/media/animes}"
SERIE_DIR="${SERIES_PATH:-/media/series}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] === Début mise à jour hebdomadaire des saisons ==="

echo "[$(date '+%Y-%m-%d %H:%M:%S')] → Animes..."
python3 "$SCRIPT" --type anime --seasons "$ANIME_DIR" || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] → Séries..."
python3 "$SCRIPT" --type serie --seasons "$SERIE_DIR" || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] === Terminé ==="
