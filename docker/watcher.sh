#!/bin/sh
set -e

# Hash la liste des fichiers video sous un dossier (jusqu'a maxdepth niveaux).
# Ne reagit qu'aux ajouts/suppressions de fichiers video -- ignore les sous-dossiers,
# les sous-titres, les NFO, etc.
VIDEO_EXTS='\.\(mkv\|mp4\|avi\|mov\|wmv\|m4v\|webm\)$'

get_video_hash() {
    dir="$1"; depth="$2"
    find "$dir" -maxdepth "$depth" -type f 2>/dev/null \
        | grep -i "$VIDEO_EXTS" \
        | sort \
        | md5sum \
        | cut -d' ' -f1
}

last_animes=$(get_video_hash "${ANIMES_PATH}" 4)
last_films=$(get_video_hash  "${FILMS_PATH}"  2)
last_series=$(get_video_hash "${SERIES_PATH}" 4)

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watcher demarre"
php /var/www/html/cli/regen-index.php
php /var/www/html/cli/jellyfin-map.php

while true; do
    sleep 30

    cur_animes=$(get_video_hash "${ANIMES_PATH}" 4)
    cur_films=$(get_video_hash  "${FILMS_PATH}"  2)
    cur_series=$(get_video_hash "${SERIES_PATH}" 4)

    changed=0

    if [ "$cur_animes" != "$last_animes" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Changement detecte (animes) -- fetch metadata + refresh Jellyfin"
        python3 /var/www/html/cli/fetch_metadata.py --type anime --scan "${ANIMES_PATH}" || true
        php /var/www/html/cli/jellyfin-refresh.php --type anime || true
        last_animes=$cur_animes
        changed=1
    fi

    if [ "$cur_films" != "$last_films" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Changement detecte (films) -- fetch metadata + refresh Jellyfin"
        python3 /var/www/html/cli/fetch_metadata.py --type film --scan "${FILMS_PATH}" || true
        php /var/www/html/cli/jellyfin-refresh.php --type film || true
        last_films=$cur_films
        changed=1
    fi

    if [ "$cur_series" != "$last_series" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Changement detecte (series) -- fetch metadata + refresh Jellyfin"
        python3 /var/www/html/cli/fetch_metadata.py --type serie --scan "${SERIES_PATH}" || true
        php /var/www/html/cli/jellyfin-refresh.php --type serie || true
        last_series=$cur_series
        changed=1
    fi

    if [ "$changed" = "1" ]; then
        php /var/www/html/cli/regen-index.php
        # Laisse le temps au scan Jellyfin cible (asynchrone) de se terminer
        # avant de reconstruire le mapping path -> ItemId
        sleep 10
        php /var/www/html/cli/jellyfin-map.php
    fi
done
