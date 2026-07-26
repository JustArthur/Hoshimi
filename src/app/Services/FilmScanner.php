<?php

declare(strict_types=1);

// ============================================================
//  FilmScanner — Scan des films depuis Y:\Films
//  Structure : Films/Titre/Titre.mkv  +  cover.*  +  metadata.json
// ============================================================
class FilmScanner
{
    private string $filmsPath;
    private static ?array $cache = null;

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm', 'm4v'];

    public function __construct()
    {
        $this->filmsPath = $_ENV['FILMS_PATH'] ?? '/media/films';
    }

    // ----------------------------------------------------------------
    //  CATALOGUE COMPLET
    // ----------------------------------------------------------------
    public function getAllFilms(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $cacheFile = sys_get_temp_dir() . '/hoshimi_scanner_filmscanner.json';
        $cacheTTL  = 300;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                self::$cache = $cached;
                return self::$cache;
            }
        }

        $films = [];

        if (!is_dir($this->filmsPath)) {
            return $films;
        }

        $dirs = glob($this->filmsPath . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            $film = $this->scanFilmDir($dir);
            if ($film !== null) {
                $films[] = $film;
            }
        }

        usort($films, fn($a, $b) => strcmp($a['title'], $b['title']));

        self::$cache = $films;
        @file_put_contents($cacheFile, json_encode($films, JSON_UNESCAPED_UNICODE));
        return self::$cache;
    }

    // ----------------------------------------------------------------
    //  UN FILM PAR SLUG
    // ----------------------------------------------------------------
    public function getFilmBySlug(string $slug): ?array
    {
        $dirName = urldecode($slug);
        $path    = $this->filmsPath . '/' . $dirName;

        if (!is_dir($path)) {
            return null;
        }

        return $this->scanFilmDir($path);
    }

    // ----------------------------------------------------------------
    //  UN FILM PAR CHEMIN DIRECT
    // ----------------------------------------------------------------
    public function getFilmByPath(string $dir): ?array
    {
        return is_dir($dir) ? $this->scanFilmDir($dir) : null;
    }

    // ----------------------------------------------------------------
    //  SCAN D'UN DOSSIER FILM
    // ----------------------------------------------------------------
    private function scanFilmDir(string $dir): ?array
    {
        $videoFile = $this->findVideoFile($dir);
        $hasFile   = $videoFile !== null;

        $name     = basename($dir);
        $metadata = $this->findMetadataJson($dir);

        // Dossier sans fichier vidéo ET sans métadonnées → ignorer
        if (!$hasFile && empty($metadata)) {
            return null;
        }

        $coverFile = $this->findCoverImage($dir);

        // v2 format (flat) or old TMDB format (nested under "details")
        if (($metadata['v'] ?? 0) >= 2) {
            $title     = $metadata['title'] ?? $name;
            $rawGenres = $metadata['genres'] ?? [];
            $genres    = array_map(fn($g) => (string)$g, $rawGenres);
            $coverUrl  = $coverFile
                ? '/stream-image/?path=' . urlencode($coverFile)
                : ($metadata['cover'] ?? null);
            $bannerUrl = $metadata['banner'] ?? null;
            $details   = $metadata; // for synopsis/year/score/runtime below
        } else {
            $details = isset($metadata['details']) && is_array($metadata['details'])
                ? $metadata['details']
                : $metadata;
            $title     = $details['title'] ?? $details['name'] ?? $name;
            $rawGenres = $details['genres'] ?? [];
            $genres    = array_map(
                fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g,
                $rawGenres
            );
            $coverUrl  = $coverFile
                ? '/stream-image/?path=' . urlencode($coverFile)
                : ($metadata['images']['posters'][0]['urls']['w500']
                    ?? $metadata['images']['posters'][0]['urls']['original']
                    ?? null);
            $bannerUrl = $metadata['images']['backdrops'][0]['urls']['w1280']
                ?? $metadata['images']['backdrops'][0]['urls']['original']
                ?? null;
        }

        $seasons = [];
        if ($hasFile) {
            $fileInfo = $this->parseFilmFilename(pathinfo($videoFile, PATHINFO_FILENAME));
            $seasons  = [
                [
                    'number'   => 1,
                    'label'    => 'Film',
                    'path'     => $dir,
                    'episodes' => [
                        [
                            'filename'   => basename($videoFile),
                            'file_path'  => $videoFile,
                            'title'      => $title,
                            'number'     => 1,
                            'season'     => 1,
                            'quality'    => $fileInfo['quality'],
                            'language'   => $fileInfo['language'],
                            'stream_url' => '/stream/index.php?path=' . rawurlencode($videoFile),
                            'added_at'   => date('Y-m-d\TH:i:s', filemtime($videoFile) ?: 0),
                        ],
                    ],
                ],
            ];
        }

        return [
            'slug'       => urlencode($name),
            'dir_name'   => $name,
            'dir_path'   => $dir,
            'title'      => $title,
            'cover_url'  => $coverUrl,
            'banner_url' => $bannerUrl,
            'synopsis'   => $details['overview'] ?? $details['description'] ?? $details['synopsis'] ?? null,
            'genres'     => $genres,
            'year'       => $this->extractYear($details),
            'score'      => isset($details['vote_average']) && $details['vote_average'] > 0
                ? round((float)$details['vote_average'], 1)
                : (isset($details['score']) && $details['score'] > 0 ? (float)$details['score'] : null),
            'runtime'    => $details['runtime'] ?? null,
            'format'        => 'Film',
            'has_file'        => $hasFile,
            'main_language'   => $seasons[0]['episodes'][0]['language'] ?? null,
            'last_episode_at' => $seasons[0]['episodes'][0]['added_at'] ?? null,
            'metadata'        => $metadata,
            'seasons'         => $seasons,
        ];
    }

    // ----------------------------------------------------------------
    //  CHERCHE LE FICHIER VIDÉO (premier trouvé, priorité mkv > mp4)
    // ----------------------------------------------------------------
    private function findVideoFile(string $dir): ?string
    {
        foreach (self::VIDEO_EXT as $ext) {
            $files = glob($dir . '/*.' . $ext) ?: [];
            if (!empty($files)) {
                return $files[0];
            }
        }
        return null;
    }

    // ----------------------------------------------------------------
    //  CHERCHE LA COVER IMAGE
    // ----------------------------------------------------------------
    private function findCoverImage(string $dir): ?string
    {
        foreach (self::IMAGE_EXT as $ext) {
            $candidate = $dir . '/cover.' . $ext;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || is_dir($dir . '/' . $file)) continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, self::IMAGE_EXT, true)) {
                return $dir . '/' . $file;
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    //  CHERCHE LE JSON METADATA
    // ----------------------------------------------------------------
    private function findMetadataJson(string $dir): array
    {
        $file = $dir . '/metadata.json';
        if (!file_exists($file)) {
            return [];
        }

        $content = ltrim((string)@file_get_contents($file), "\xEF\xBB\xBF");
        $json    = json_decode($content, true);
        return is_array($json) ? $json : [];
    }

    // ----------------------------------------------------------------
    //  PARSE QUALITÉ ET LANGUE DEPUIS LE NOM DU FICHIER FILM
    // ----------------------------------------------------------------
    private function parseFilmFilename(string $name): array
    {
        $quality  = null;
        $language = null;

        if (preg_match('/\b(4K|2160p|1080p|720p|480p)\b/i', $name, $m)) {
            $quality = strtolower($m[1]);
        }

        // Collecte tous les tags langue et garde le plus spécifique (VOSTFR > VOSTA > VO, MULTI > VF…)
        if (preg_match_all('/\b(VOSTFR|VOSTA|VOSTEN|MULTI|DUAL|VF|VO)\b/i', $name, $ms)) {
            $priority = ['VOSTFR' => 6, 'VOSTA' => 5, 'VOSTEN' => 4, 'MULTI' => 3, 'DUAL' => 3, 'VF' => 2, 'VO' => 1];
            $tags = array_map('strtoupper', $ms[1]);
            usort($tags, fn($a, $b) => ($priority[$b] ?? 0) <=> ($priority[$a] ?? 0));
            $language = implode(' ', array_unique($tags));
        }

        return ['quality' => $quality, 'language' => $language];
    }

    // ----------------------------------------------------------------
    //  EXTRAIT L'ANNÉE DEPUIS LES MÉTADONNÉES
    // ----------------------------------------------------------------
    private function extractYear(array $details): ?int
    {
        foreach (['release_date', 'first_air_date'] as $key) {
            if (!empty($details[$key])) {
                $y = (int)substr($details[$key], 0, 4);
                if ($y > 1800) return $y;
            }
        }
        if (!empty($details['year'])) {
            return (int)$details['year'];
        }
        return null;
    }
}
