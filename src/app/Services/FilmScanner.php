<?php

declare(strict_types=1);

// ============================================================
//  FilmScanner — Scan des films depuis D:\Films
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

        // Support format tmdb_fetch.py (détails imbriqués sous "details")
        // et format plat pour la compatibilité ascendante
        $details  = isset($metadata['details']) && is_array($metadata['details'])
            ? $metadata['details']
            : $metadata;

        $coverFile = $this->findCoverImage($dir);

        // Fallback : poster TMDB remote si pas de cover locale
        $coverUrl = null;
        if ($coverFile) {
            $coverUrl = '/stream-image/?path=' . urlencode($coverFile);
        } elseif (!empty($metadata['images']['posters'][0]['urls']['original'])) {
            $coverUrl = $metadata['images']['posters'][0]['urls']['original'];
        }

        // Titre : details prioritaire sur nom de dossier
        $title = $details['title'] ?? $details['name'] ?? $name;

        // Genres : [{id, name}] (TMDB) ou tableau de chaînes
        $rawGenres = $details['genres'] ?? [];
        $genres    = array_map(
            fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g,
            $rawGenres
        );

        // Banner (backdrop TMDB) — image paysage pour le hero
        $bannerUrl = $metadata['images']['backdrops'][0]['urls']['w1280']
            ?? $metadata['images']['backdrops'][0]['urls']['original']
            ?? null;

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
                : null,
            'runtime'    => $details['runtime'] ?? null,
            'format'     => 'Film',
            'has_file'   => $hasFile,
            'metadata'   => $metadata,
            'seasons'    => $seasons,
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

        if (preg_match('/\b(VOSTFR|VOSTA|VOSTEN|VF|VO|MULTI|DUAL)\b/i', $name, $m)) {
            $language = strtoupper($m[1]);
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
