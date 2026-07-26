<?php

declare(strict_types=1);

// ============================================================
//  IndexManager — Index JSON léger + détail à la demande
//
//  getAll(type)             → lit index léger (metadata.json only, pas d'épisodes)
//  getBySlug(type, slug)    → scan complet d'UN seul show via le scanner (épisodes inclus)
//  regenerate(type)         → reconstruit l'index léger sans scanner les épisodes
// ============================================================

class IndexManager
{
    private const DATA_DIR = __DIR__ . '/../data/index';
    // Pas de TTL — l'index est régénéré par inotifywait/polling, jamais par expiration

    private const MEDIA_PATHS = [
        'anime'  => ['env' => 'ANIMES_PATH',  'default' => '/media/animes'],
        'serie'  => ['env' => 'SERIES_PATH',  'default' => '/media/series'],
        'film'   => ['env' => 'FILMS_PATH',   'default' => '/media/films'],
    ];

    private const SCANNERS = [
        'anime'  => ['file' => 'AnimeScanner.php',  'class' => 'AnimeScanner',  'slug_method' => 'getAnimeBySlug'],
        'serie'  => ['file' => 'SeriesScanner.php', 'class' => 'SeriesScanner', 'slug_method' => 'getAnimeBySlug'],
        'film'   => ['file' => 'FilmScanner.php',   'class' => 'FilmScanner',   'slug_method' => 'getFilmBySlug'],
    ];

    private const VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm', 'm4v'];
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    private static array $cache = [];

    // ----------------------------------------------------------------
    //  API publique — lecture catalogue (léger, sans épisodes)
    // ----------------------------------------------------------------

    public static function getAll(string $type): array
    {
        if (isset(self::$cache[$type])) {
            return self::$cache[$type];
        }

        $file = self::indexFile($type);

        // Génération uniquement si l'index n'existe pas encore
        // La mise à jour est gérée par le watcher et les scans admin
        if (!file_exists($file)) {
            self::regenerate($type);
        }

        self::$cache[$type] = self::readFile($file);
        return self::$cache[$type];
    }

    // ----------------------------------------------------------------
    //  API publique — détail d'un show (scan complet via scanner)
    // ----------------------------------------------------------------

    public static function getBySlug(string $type, string $slug): ?array
    {
        if (!isset(self::SCANNERS[$type])) {
            return null;
        }

        // Résolution rapide via l'index JSON → scan d'un seul dossier
        $dirPath = null;
        foreach (self::getAll($type) as $item) {
            if (($item['slug'] ?? '') === $slug) {
                $dirPath = $item['dir_path'] ?? null;
                break;
            }
        }

        $cfg = self::SCANNERS[$type];
        require_once __DIR__ . '/Services/' . $cfg['file'];
        $scanner = new $cfg['class']();

        // Chemin connu → méthode directe (pas de scan global)
        if ($dirPath !== null && is_dir($dirPath)) {
            $pathMethod = $type === 'film' ? 'getFilmByPath' : 'getAnimeByPath';
            return $scanner->{$pathMethod}($dirPath);
        }

        // Fallback : slug uniquement (dossier introuvable dans l'index)
        return $scanner->{$cfg['slug_method']}($slug);
    }

    // ----------------------------------------------------------------
    //  API publique — reconstruction de l'index léger
    // ----------------------------------------------------------------

    public static function regenerate(string $type): int
    {
        if (!isset(self::MEDIA_PATHS[$type])) {
            throw new \InvalidArgumentException("Type inconnu : $type");
        }

        $cfg       = self::MEDIA_PATHS[$type];
        $mediaPath = $_ENV[$cfg['env']] ?? $cfg['default'];
        $dirs      = is_dir($mediaPath) ? (glob($mediaPath . '/*', GLOB_ONLYDIR) ?: []) : [];

        $items = [];
        $id    = 1;

        foreach ($dirs as $dir) {
            $item = self::buildLightItem($type, $dir, $id);
            if ($item !== null) {
                $items[] = $item;
                $id++;
            }
        }

        // Trending score (animes/series)
        if (in_array($type, ['anime', 'serie'], true) && !empty($items)) {
            $topN = max(1, min(10, (int)ceil(count($items) * 0.05)));
            usort($items, fn($a, $b) => $b['trending_score'] <=> $a['trending_score']);
            foreach ($items as $i => &$item) {
                $item['is_trending'] = $i < $topN;
            }
            unset($item);
        }

        usort($items, fn($a, $b) => strcmp($a['title'], $b['title']));

        // Ré-assigner les ids après tri
        foreach ($items as $i => &$item) {
            $item['id'] = $i + 1;
        }
        unset($item);

        self::ensureDataDir();
        @file_put_contents(
            self::indexFile($type),
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        unset(self::$cache[$type]);

        return count($items);
    }

    // Ajoute ou met à jour uniquement les entrées correspondant aux $dirs fournis
    private static function patch(string $type, string $indexFile, array $dirs): void
    {
        $existing = self::readFile($indexFile);

        // Index existant par dir_name pour lookup rapide
        $byName = [];
        foreach ($existing as $i => $item) {
            $byName[$item['dir_name']] = $i;
        }

        $nextId = $existing ? (max(array_column($existing, 'id')) + 1) : 1;

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $isKnown = array_key_exists($name, $byName);
            $item = self::buildLightItem($type, $dir, $isKnown ? $existing[$byName[$name]]['id'] : $nextId);
            if ($item === null) continue;

            if ($isKnown) {
                $existing[$byName[$name]] = $item;
            } else {
                $item['id'] = $nextId++;
                $existing[] = $item;
                $byName[$name] = array_key_last($existing);
            }
        }

        usort($existing, fn($a, $b) => strcmp($a['title'], $b['title']));
        foreach ($existing as $i => &$item) { $item['id'] = $i + 1; }
        unset($item);

        @file_put_contents($indexFile, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        unset(self::$cache[$type]);
    }

    public static function regenerateAll(): array
    {
        $t0     = microtime(true);
        $counts = [];

        foreach (array_keys(self::MEDIA_PATHS) as $type) {
            try {
                $counts[$type] = self::regenerate($type);
            } catch (\Throwable) {
                $counts[$type] = -1;
            }
        }

        $counts['time_ms'] = (int)round((microtime(true) - $t0) * 1000);
        return $counts;
    }

    public static function getMeta(): array
    {
        $meta = [];
        foreach (array_keys(self::MEDIA_PATHS) as $type) {
            $file = self::indexFile($type);
            if (file_exists($file)) {
                $data = self::readFile($file);
                $meta[$type] = [
                    'exists'       => true,
                    'generated'    => date('Y-m-d H:i:s', filemtime($file)),
                    'generated_ts' => filemtime($file),
                    'stale'        => self::isStale($file),
                    'count'        => count($data),
                    'size_kb'      => round(filesize($file) / 1024, 1),
                ];
            } else {
                $meta[$type] = ['exists' => false, 'stale' => true];
            }
        }
        return $meta;
    }

    // ----------------------------------------------------------------
    //  Construction d'un item léger (metadata.json only)
    // ----------------------------------------------------------------

    private static function buildLightItem(string $type, string $dir, int $id): ?array
    {
        $name     = basename($dir);
        $metadata = self::readMetadataJson($dir);

        // Trouver cover locale
        $coverFile = self::findCoverFile($dir);
        $coverUrl  = $coverFile
            ? '/stream-image/?path=' . urlencode($coverFile)
            : null;

        // Parser les champs affichage depuis metadata.json
        $fields = self::parseMetaFields($metadata, $name, $type, $coverUrl);

        // Ignorer les dossiers sans métadonnées ET sans fichiers vidéo
        if (empty($metadata) && !self::hasVideoFiles($dir)) {
            return null;
        }

        // Fast episode count + langue + last_episode_at + file_keys
        [$epCount, $language, $lastEpisodeAt] = self::fastEpisodeInfo($dir);
        $episodeKeys = self::buildEpisodeKeys($dir);

        // Trending score
        $trendingScore = 0.0;
        if ($type !== 'film') {
            $pop         = $fields['popularity'];
            $scoreRaw    = $fields['score_raw'];
            $status      = strtoupper($fields['status']);
            $age         = time() - (@filemtime($dir) ?: 0);

            $trendingScore =
                min($pop / 500000.0, 1.0) * 50 +
                ($scoreRaw / 100.0) * 10 +
                match ($status) {
                    'RELEASING', 'RETURNING SERIES'               => 30,
                    'NOT_YET_RELEASED', 'IN PRODUCTION', 'PLANNED' => 10,
                    default                                        => 0,
                } +
                match (true) {
                    $age < 7  * 86400 => 20,
                    $age < 30 * 86400 => 12,
                    $age < 90 * 86400 => 5,
                    default           => 0,
                };
        }

        return [
            'id'             => $id,
            'slug'           => urlencode($name),
            'type'           => $type,
            'dir_path'       => $dir,
            'dir_name'       => $name,
            'title'          => $fields['title'],
            'title_english'  => $fields['title_english'],
            'title_romaji'   => $fields['title_romaji'],
            'title_original' => $fields['title_original'],
            'cover_url'      => $fields['cover_url'],
            'banner_url'     => $fields['banner_url'],
            'logo_url'       => $fields['logo_url'] ?? null,
            'synopsis'       => $fields['synopsis'],
            'score'          => $fields['score'],
            'year'           => $fields['year'],
            'genres'         => $fields['genres'],
            'format'         => $fields['format'],
            'studio'         => $fields['studio'],
            'status'         => $fields['status'],
            'episodes_total' => $fields['episodes_total'],
            'episodes_local'  => $epCount,
            'main_language'   => $language,
            'anilist_id'      => $fields['anilist_id'] ?? null,
            'tmdb_id'         => $metadata['tmdb_id'] ?? $metadata['details']['id'] ?? null,
            'trending_score'  => $trendingScore,
            'is_trending'     => false,
            'last_episode_at' => $lastEpisodeAt,
            'added_at'        => date('c', @filectime($dir) ?: @filemtime($dir) ?: time()),
            'updated_at'      => date('c', @filemtime($dir) ?: time()),
            'episodes'        => $episodeKeys,
        ];
    }

    // ----------------------------------------------------------------
    //  Parser metadata.json → champs affichage normalisés
    // ----------------------------------------------------------------

    private static function parseMetaFields(array $m, string $dirName, string $type, ?string $localCoverUrl): array
    {
        $empty = [
            'title' => $dirName, 'title_english' => $dirName,
            'title_romaji' => $dirName, 'title_original' => null,
            'synopsis' => null, 'status' => 'UNKNOWN', 'year' => null,
            'genres' => [], 'score' => null, 'score_raw' => 0,
            'popularity' => 0, 'format' => ($type === 'film' ? 'Film' : 'TV'),
            'studio' => null, 'cover_url' => $localCoverUrl,
            'banner_url' => null, 'logo_url' => null,
            'episodes_total' => null, 'anilist_id' => null,
        ];

        if (empty($m)) {
            return $empty;
        }

        // ── Format v2 (lean) ─────────────────────────────────────────────────
        if (($m['v'] ?? 0) >= 2) {
            $score  = (float)($m['score'] ?? 0);
            $logos  = $m['logos'] ?? [];
            $logoUrl = !empty($logos) ? ($logos[0]['w500'] ?? $logos[0]['original'] ?? null) : null;
            return [
                'title'          => $m['title']          ?? $dirName,
                'title_english'  => $m['title']          ?? $dirName,
                'title_romaji'   => $m['title_romaji']   ?? $m['title'] ?? $dirName,
                'title_original' => $m['title_original'] ?? null,
                'synopsis'       => $m['overview']       ?? null,
                'status'         => $m['status']         ?? 'UNKNOWN',
                'year'           => $m['year']            ?? null,
                'genres'         => $m['genres']          ?? [],
                'score'          => $score > 0 ? round($score, 1) : null,
                'score_raw'      => (int)round($score * 10),
                'popularity'     => (int)($m['popularity'] ?? 0),
                'format'         => $m['format']          ?? ($type === 'film' ? 'Film' : 'TV'),
                'studio'         => $m['studio']          ?? null,
                'cover_url'      => $localCoverUrl ?? $m['cover']  ?? null,
                'banner_url'     => $m['banner']          ?? null,
                'logo_url'       => $logoUrl,
                'episodes_total' => $m['episodes_total']  ?? null,
                'anilist_id'     => $m['anilist_id']      ?? null,
            ];
        }

        // ── Format AniList (Media wrapper) ───────────────────────────────────
        if (isset($m['Media']) || isset($m['data']['Media'])) {
            $al    = $m['Media'] ?? $m['data']['Media'] ?? $m;
            $t     = $al['title'] ?? [];
            $title = $t['french'] ?? $t['romaji'] ?? $t['english'] ?? $dirName;
            $avg   = (int)($al['averageScore'] ?? 0);
            $ci    = $al['coverImage'] ?? [];
            return [
                'title'          => $title,
                'title_english'  => $t['english']      ?? $dirName,
                'title_romaji'   => $t['romaji']       ?? $dirName,
                'title_original' => $t['native']       ?? null,
                'synopsis'       => $al['description'] ?? null,
                'status'         => $al['status']      ?? 'UNKNOWN',
                'year'           => $al['seasonYear']  ?? $al['startDate']['year'] ?? null,
                'genres'         => $al['genres']      ?? [],
                'score'          => $avg > 0 ? round($avg / 10, 1) : null,
                'score_raw'      => $avg,
                'popularity'     => (int)($al['popularity'] ?? 0),
                'format'         => $al['format']      ?? ($type === 'film' ? 'Film' : 'TV'),
                'studio'         => $al['studios']['nodes'][0]['name'] ?? null,
                'cover_url'      => $localCoverUrl ?? $ci['extraLarge'] ?? $ci['large'] ?? null,
                'banner_url'     => $al['bannerImage'] ?? null,
                'logo_url'       => null,
                'episodes_total' => $al['episodes']    ?? null,
                'anilist_id'     => $al['id']          ?? null,
            ];
        }

        // ── Format TMDB (films / séries) ────────────────────────────────────
        $d      = $m['details']  ?? $m;
        $images = $m['images']   ?? [];
        $title  = $d['title'] ?? $d['name'] ?? $dirName;
        $rawG   = $d['genres'] ?? [];
        $genres = array_map(fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g, $rawG);
        $avg    = (float)($d['vote_average'] ?? $d['score'] ?? 0);
        $coverUrl  = $localCoverUrl
            ?? $images['posters'][0]['urls']['w500'] ?? $images['posters'][0]['urls']['original'] ?? null;
        $bannerUrl = $images['backdrops'][0]['urls']['w1280'] ?? $images['backdrops'][0]['urls']['original'] ?? null;
        $tmdbLogos = $images['logos'] ?? [];
        if (!empty($tmdbLogos)) {
            usort($tmdbLogos, fn($a, $b) => (($b['iso_639_1'] ?? '') === 'en') <=> (($a['iso_639_1'] ?? '') === 'en'));
        }
        $logoUrl = !empty($tmdbLogos)
            ? ($tmdbLogos[0]['urls']['w500'] ?? $tmdbLogos[0]['urls']['original'] ?? null)
            : null;

        $year = null;
        foreach (['year', 'release_year', 'first_air_date', 'release_date'] as $k) {
            if (!empty($d[$k])) {
                $year = is_numeric($d[$k]) ? (int)$d[$k] : (int)substr((string)$d[$k], 0, 4);
                break;
            }
        }

        return [
            'title'          => $title,
            'title_english'  => $d['original_title'] ?? $title,
            'title_romaji'   => $title,
            'title_original' => $d['original_title'] ?? null,
            'synopsis'       => $d['overview'] ?? $d['description'] ?? $d['synopsis'] ?? null,
            'status'         => $d['status'] ?? 'UNKNOWN',
            'year'           => $year,
            'genres'         => array_values(array_filter($genres)),
            'score'          => $avg > 0 ? round($avg, 1) : null,
            'score_raw'      => (int)($avg * 10),
            'popularity'     => (int)($d['popularity'] ?? 0),
            'format'         => $type === 'film' ? 'Film' : ($d['type'] ?? 'TV'),
            'studio'         => null,
            'cover_url'      => $coverUrl,
            'banner_url'     => $bannerUrl,
            'logo_url'       => $logoUrl,
            'episodes_total' => $d['number_of_episodes'] ?? null,
            'anilist_id'     => null,
        ];
    }

    // ----------------------------------------------------------------
    //  Fast episode count + langue + last_episode_at (glob + filemtime)
    // ----------------------------------------------------------------

    private static function buildEpisodeKeys(string $dir): array
    {
        $eps = [];
        foreach (self::VIDEO_EXT as $ext) {
            // Films : fichiers à la racine
            foreach (glob($dir . '/*.' . $ext) ?: [] as $f) {
                $eps[] = ['file_key' => md5($f), 'season' => 1, 'number' => 1];
            }
            // Séries/animes : Season XX/
            foreach (glob($dir . '/*/*.' . $ext) ?: [] as $f) {
                $seasonDir = basename(dirname($f));
                preg_match('/(\d+)/', $seasonDir, $sm);
                $seasonNum = isset($sm[1]) ? (int)$sm[1] : 1;
                $base = pathinfo($f, PATHINFO_FILENAME);
                preg_match('/[Ee](\d+)|[\s_\-](\d{2,3})[\s_\-\.]/', $base, $em);
                $epNum = isset($em[1]) ? (int)$em[1] : (isset($em[2]) ? (int)$em[2] : 1);
                $eps[] = ['file_key' => md5($f), 'season' => $seasonNum, 'number' => $epNum];
            }
        }
        return $eps;
    }

    private static function fastEpisodeInfo(string $dir): array
    {
        $allFiles = [];
        foreach (self::VIDEO_EXT as $ext) {
            $allFiles = array_merge(
                $allFiles,
                glob($dir . '/*.' . $ext) ?: [],
                glob($dir . '/*/*.' . $ext) ?: []
            );
        }
        $count = count($allFiles);

        $language      = null;
        $lastEpisodeAt = null;

        foreach ($allFiles as $i => $f) {
            if ($i === 0) {
                $base = basename($f);
                if (preg_match('/\b(VOSTFR|VOSTA|VOSTEN|MULTI|DUAL|VF|VO)\b/i', $base, $lm)) {
                    $language = strtoupper($lm[1]);
                }
            }
            $mtime = @filemtime($f);
            if ($mtime && ($lastEpisodeAt === null || $mtime > $lastEpisodeAt)) {
                $lastEpisodeAt = $mtime;
            }
        }

        return [$count, $language, $lastEpisodeAt ? date('Y-m-d\TH:i:s', $lastEpisodeAt) : null];
    }

    private static function hasVideoFiles(string $dir): bool
    {
        foreach (self::VIDEO_EXT as $ext) {
            if (!empty(glob($dir . '/*.' . $ext) ?: [])) return true;
            if (!empty(glob($dir . '/*/*.' . $ext) ?: [])) return true;
        }
        return false;
    }

    private static function findCoverFile(string $dir): ?string
    {
        foreach (self::IMAGE_EXT as $ext) {
            $f = $dir . '/cover.' . $ext;
            if (file_exists($f)) return $f;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file[0] === '.') continue;
            if (is_dir($dir . '/' . $file)) continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, self::IMAGE_EXT, true)) return $dir . '/' . $file;
        }
        return null;
    }

    private static function readMetadataJson(string $dir): array
    {
        $file = $dir . '/metadata.json';
        if (!file_exists($file)) return [];
        $content = ltrim((string)@file_get_contents($file), "\xEF\xBB\xBF");
        $json    = json_decode($content, true);
        return is_array($json) ? $json : [];
    }

    // ----------------------------------------------------------------
    //  Internals
    // ----------------------------------------------------------------

    private static function indexFile(string $type): string
    {
        return self::DATA_DIR . '/' . $type . 's.json';
    }

    private static function isStale(string $file): bool
    {
        return !file_exists($file);
    }

    private static function readFile(string $file): array
    {
        if (!file_exists($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function ensureDataDir(): void
    {
        if (!is_dir(self::DATA_DIR)) {
            mkdir(self::DATA_DIR, 0755, true);
        }
    }
}
