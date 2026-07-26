<?php
// ============================================================
//  AnimeScanner — Scan des animes locaux depuis Y:\Animes
//  Format : Anime 1/Season 01/Anime 1 S01E01 720p VOSTFR.mp4
//  Lit les fichiers JSON AniList + cover image à la racine
// ============================================================

declare(strict_types=1);

class AnimeScanner
{
    protected string $animesPath;

    protected static ?array $cache = null;

    // Extensions image acceptées pour la cover
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    // Extensions vidéo pour détecter les épisodes
    private const VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm'];

    public function __construct(?string $basePath = null)
    {
        $this->animesPath = $basePath ?? ($_ENV['ANIMES_PATH'] ?? '/media/animes');
    }

    // ----------------------------------------------------------------
    //  CATALOGUE COMPLET — retourne tous les animes
    // ----------------------------------------------------------------
    public function getAllAnimes(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $cacheFile = sys_get_temp_dir() . '/hoshimi_scanner_' . strtolower(static::class) . '.json';
        $cacheTTL  = 300;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                static::$cache = $cached;
                return static::$cache;
            }
        }

        $animes = [];

        if (!is_dir($this->animesPath)) {
            return $animes;
        }

        $dirs = glob($this->animesPath . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            $anime = $this->scanAnimeDir($dir);
            if ($anime !== null) {
                $animes[] = $anime;
            }
        }

        // ── Trending : top 5 % (min 1, max 10) par score multi-critères ──────────
        if (!empty($animes)) {
            $topN = max(1, min(10, (int) ceil(count($animes) * 0.05)));
            usort($animes, fn($a, $b) => $b['trending_score'] <=> $a['trending_score']);
            foreach ($animes as $i => &$a) {
                $a['is_trending'] = $i < $topN;
            }
            unset($a);
        }

        usort($animes, fn($a, $b) => strcmp($a['title'], $b['title']));

        static::$cache = $animes;
        @file_put_contents($cacheFile, json_encode($animes, JSON_UNESCAPED_UNICODE));
        return static::$cache;
    }

    // ----------------------------------------------------------------
    //  UN ANIME PAR SLUG (nom de dossier encodé)
    // ----------------------------------------------------------------
    public function getAnimeBySlug(string $slug): ?array
    {
        foreach ($this->getAllAnimes() as $anime) {
            if ($anime['slug'] === $slug) {
                return $anime;
            }
        }
        return null;
    }

    // ----------------------------------------------------------------
    //  UN ANIME PAR CHEMIN DIRECT (évite le scan de tous les dossiers)
    // ----------------------------------------------------------------
    public function getAnimeByPath(string $dir): ?array
    {
        return is_dir($dir) ? $this->scanAnimeDir($dir) : null;
    }

    // ----------------------------------------------------------------
    //  SCAN D'UN DOSSIER ANIME
    // ----------------------------------------------------------------
    private function scanAnimeDir(string $dir): ?array
    {
        $name     = basename($dir);
        $metadata = $this->findMetadataJson($dir);
        $seasons  = $this->scanSeasons($dir);

        if (empty($metadata) && empty($seasons)) {
            return null;
        }

        $fields    = $this->resolveMetaFields($metadata, $name);
        $coverFile = $this->findCoverImage($dir);
        $coverUrl  = $coverFile
            ? '/stream-image/?path=' . urlencode($coverFile)
            : ($fields['cover_url'] ?? null);

        $mainLanguage = null;
        if (!empty($seasons) && !empty($seasons[0]['episodes'])) {
            $mainLanguage = $seasons[0]['episodes'][0]['language'] ?? 'VOSTFR';
        }

        // ── Trending score ──────────────────────────────────────────────────
        $popularity   = $fields['popularity'];
        $scoreRaw     = $fields['score_raw'];
        $status       = strtoupper($fields['status']);

        $popNorm      = min($popularity / 500000.0, 1.0) * 50;
        $scoreNorm    = ($scoreRaw / 100.0) * 10;
        $statusBonus  = match ($status) {
            'RELEASING', 'RETURNING SERIES' => 30,
            'NOT_YET_RELEASED', 'IN PRODUCTION', 'PLANNED' => 10,
            default => 0,
        };
        $age          = time() - (@filemtime($dir) ?: 0);
        $recencyBonus = match (true) {
            $age < 7  * 86400 => 20,
            $age < 30 * 86400 => 12,
            $age < 90 * 86400 => 5,
            default           => 0,
        };

        return [
            'slug'           => $this->dirToSlug($name),
            'dir_name'       => $name,
            'dir_path'       => $dir,
            'title'          => $fields['title'],
            'title_original' => $fields['title_original'],
            'title_romaji'   => $fields['title_romaji'],
            'title_english'  => $fields['title_english'],
            'cover_url'      => $coverUrl,
            'cover_path'     => $coverFile,
            'banner_url'     => $fields['banner_url'],
            'synopsis'       => $fields['synopsis'],
            'genres'         => $fields['genres'],
            'status'         => $fields['status'],
            'episodes_total' => $fields['episodes_total'],
            'main_language'  => $mainLanguage,
            'score'          => $fields['score'],
            'year'           => $fields['year'],
            'format'         => $fields['format'],
            'studio'         => $fields['studio'],
            'anilist_id'     => $fields['anilist_id'],
            'seasons'          => $seasons,
            'episodes_local'   => array_sum(array_map(fn($s) => count($s['episodes']), $seasons)),
            'last_episode_at'  => (function() use ($seasons): ?string {
                $max = null;
                foreach ($seasons as $s) {
                    foreach ($s['episodes'] as $ep) {
                        if (isset($ep['added_at']) && ($max === null || $ep['added_at'] > $max)) {
                            $max = $ep['added_at'];
                        }
                    }
                }
                return $max;
            })(),
            'metadata'       => $metadata,
            'trending_score' => $popNorm + $scoreNorm + $statusBonus + $recencyBonus,
            'is_trending'    => false,
        ];
    }

    // ----------------------------------------------------------------
    //  NORMALISE LE METADATA.JSON (v2 / vieux TMDB / AniList)
    //  Retourne un tableau unifié utilisé par scanAnimeDir
    // ----------------------------------------------------------------
    private function resolveMetaFields(array $metadata, string $dirName): array
    {
        if (empty($metadata)) {
            return $this->_emptyMetaFields($dirName);
        }

        // ── v2 : format lean ───────────────────────────────────────────────
        if (($metadata['v'] ?? 0) >= 2) {
            $score = (float)($metadata['score'] ?? 0);
            return [
                'title'          => $metadata['title']          ?? $dirName,
                'title_romaji'   => $metadata['title_romaji']   ?? $metadata['title'] ?? $dirName,
                'title_original' => $metadata['title_original'] ?? null,
                'title_english'  => $metadata['title']          ?? $dirName,
                'synopsis'       => $metadata['overview']       ?? null,
                'status'         => $metadata['status']         ?? 'UNKNOWN',
                'year'           => $metadata['year']           ?? null,
                'genres'         => $metadata['genres']         ?? [],
                'score'          => $score > 0 ? $score : null,
                'score_raw'      => (int)round($score * 10),
                'popularity'     => (int)($metadata['popularity'] ?? 0),
                'format'         => $metadata['format']         ?? 'TV',
                'studio'         => $metadata['studio']         ?? null,
                'cover_url'      => $metadata['cover']          ?? null,
                'banner_url'     => $metadata['banner']         ?? null,
                'episodes_total' => $metadata['episodes_total'] ?? null,
                'anilist_id'     => $metadata['anilist_id']     ?? null,
            ];
        }

        // ── Vieux format TMDB : {media_type, details, images, credits} ─────
        if (isset($metadata['media_type'])) {
            $d       = $metadata['details']  ?? [];
            $images  = $metadata['images']   ?? [];
            $title   = $d['name'] ?? $d['title'] ?? $dirName;
            $yr      = $d['first_air_date'] ?? $d['release_date'] ?? '';
            $year    = strlen($yr) >= 4 ? (int)substr($yr, 0, 4) : null;
            $rawG    = $metadata['anilist_genres'] ?? $d['genres'] ?? [];
            $genres  = array_filter(array_map(
                fn($g) => is_array($g) ? ($g['name'] ?? '') : (string)$g, $rawG
            ));
            $vote    = (float)($d['vote_average'] ?? 0);
            $score   = $vote > 0 ? round($vote, 1) : null;
            $coverUrl = $images['posters'][0]['urls']['w500']
                ?? $images['posters'][0]['urls']['original']
                ?? null;
            $bannerUrl = $images['backdrops'][0]['urls']['w1280']
                ?? $images['backdrops'][0]['urls']['original']
                ?? null;
            return [
                'title'          => $title,
                'title_romaji'   => $title,
                'title_original' => $d['original_name'] ?? $d['original_title'] ?? null,
                'title_english'  => $title,
                'synopsis'       => $d['overview'] ?? null,
                'status'         => strtoupper($d['status'] ?? 'UNKNOWN'),
                'year'           => $year,
                'genres'         => array_values($genres),
                'score'          => $score,
                'score_raw'      => (int)round(($score ?? 0) * 10),
                'popularity'     => (int)($d['popularity'] ?? 0),
                'format'         => 'TV',
                'studio'         => $d['networks'][0]['name'] ?? $d['production_companies'][0]['name'] ?? null,
                'cover_url'      => $coverUrl,
                'banner_url'     => $bannerUrl,
                'episodes_total' => $d['number_of_episodes'] ?? null,
                'anilist_id'     => null,
            ];
        }

        // ── Vieux format AniList : {Media:{...}} ou bare {...} ─────────────
        $al    = $metadata['Media'] ?? $metadata;
        $t     = $al['title']       ?? [];
        $title = $t['french'] ?? $t['romaji'] ?? $t['english'] ?? $dirName;
        $avg   = (int)($al['averageScore'] ?? 0);
        $score = $avg > 0 ? round($avg / 10, 1) : null;
        $ci    = $al['coverImage'] ?? [];
        return [
            'title'          => $title,
            'title_romaji'   => $t['romaji']       ?? $dirName,
            'title_original' => $t['native']       ?? null,
            'title_english'  => $t['english']      ?? $dirName,
            'synopsis'       => $al['description'] ?? null,
            'status'         => $al['status']      ?? 'UNKNOWN',
            'year'           => $al['seasonYear']  ?? $al['startDate']['year'] ?? null,
            'genres'         => $al['genres']      ?? [],
            'score'          => $score,
            'score_raw'      => $avg,
            'popularity'     => (int)($al['popularity'] ?? 0),
            'format'         => $al['format']      ?? 'TV',
            'studio'         => $al['studios']['nodes'][0]['name'] ?? null,
            'cover_url'      => $ci['extraLarge']  ?? $ci['large'] ?? null,
            'banner_url'     => $al['bannerImage'] ?? null,
            'episodes_total' => $al['episodes']    ?? null,
            'anilist_id'     => $al['id']          ?? null,
        ];
    }

    private function _emptyMetaFields(string $dirName): array
    {
        return [
            'title' => $dirName, 'title_romaji' => $dirName, 'title_original' => null,
            'title_english' => $dirName, 'synopsis' => null, 'status' => 'UNKNOWN',
            'year' => null, 'genres' => [], 'score' => null, 'score_raw' => 0,
            'popularity' => 0, 'format' => 'TV', 'studio' => null,
            'cover_url' => null, 'banner_url' => null,
            'episodes_total' => null, 'anilist_id' => null,
        ];
    }

    // ----------------------------------------------------------------
    //  SCAN SAISONS ET ÉPISODES
    //  Format attendu : Season 01/, Season 02/…
    //  Fallback : fichiers vidéo directement à la racine
    // ----------------------------------------------------------------
    private function scanSeasons(string $animeDir): array
    {
        $seasons = [];

        // Chargement du mapping custom si présent
        // Format : { "First Stage": { "number": 1, "label": "First Stage" }, ... }
        $folderMap = [];
        $mapFile   = $animeDir . '/.folder-map.json';
        if (file_exists($mapFile)) {
            $raw = @json_decode(@file_get_contents($mapFile), true);
            if (is_array($raw)) {
                $folderMap = $raw;
            }
        }

        // 1. On scanne TOUS les sous-dossiers du répertoire de l'animé
        $allSubDirs = glob($animeDir . '/*', GLOB_ONLYDIR) ?: [];

        // Pré-tri : préfixe numérique ("01 First Stage") → tri par ce chiffre, sinon alphabétique
        usort($allSubDirs, function (string $a, string $b): int {
            $numA = preg_match('/^(\d+)[\s\-_.]/', basename($a), $mA) ? (int)$mA[1] : PHP_INT_MAX;
            $numB = preg_match('/^(\d+)[\s\-_.]/', basename($b), $mB) ? (int)$mB[1] : PHP_INT_MAX;
            return $numA !== $numB ? $numA <=> $numB : strcmp(basename($a), basename($b));
        });

        $customSeasonIndex = 1; // compteur pour les saisons au nom libre

        foreach ($allSubDirs as $sDir) {
            $folderName      = basename($sDir);
            $folderNameLower = strtolower($folderName);

            $targetSeason = null;

            // Mapping custom prioritaire
            if (isset($folderMap[$folderName])) {
                $entry = $folderMap[$folderName];
                $targetSeason = [
                    'number' => (int)($entry['number'] ?? 1),
                    'label'  => $entry['label'] ?? $folderName,
                ];
            // Saison numérotée standard : "Season 01", "Saison 2"
            } elseif (preg_match('/^(season|saison)\s*(\d+)/i', $folderName, $m)) {
                $num = (int)$m[2];
                $targetSeason = ['number' => $num, 'label' => 'Saison ' . $num];
            // Saison avec préfixe numérique : "01 First Stage", "02 - Metal Fusion"
            } elseif (preg_match('/^(\d+)[\s\-_.]+(.+)$/u', $folderName, $m)) {
                $num   = (int)$m[1];
                $label = trim($m[2]);
                $targetSeason = ['number' => $num, 'label' => $label];
            } elseif (str_contains($folderNameLower, 'film') || str_contains($folderNameLower, 'movie')) {
                $targetSeason = ['number' => 999, 'label' => 'Films'];
            } elseif (str_contains($folderNameLower, 'oav') || str_contains($folderNameLower, 'ova')) {
                $targetSeason = ['number' => 888, 'label' => 'OAV'];
            } elseif (str_contains($folderNameLower, 'special')) {
                $targetSeason = ['number' => 777, 'label' => 'Specials'];
            } else {
                // Nom libre (ex: "First Stage", "Metal", "Fusion") → traité comme saison custom
                $targetSeason = ['number' => $customSeasonIndex, 'label' => $folderName];
            }

            // Incrémenter le compteur custom uniquement pour les saisons "normales" (< 700)
            if ($targetSeason && $targetSeason['number'] < 700 && $targetSeason['number'] === $customSeasonIndex) {
                $customSeasonIndex++;
            }

            // Scanner les épisodes uniquement si le dossier est reconnu
            if ($targetSeason) {
                $episodes = $this->scanEpisodes($sDir);
                if (!empty($episodes)) {
                    $seasons[] = [
                        'number'   => $targetSeason['number'],
                        'label'    => $targetSeason['label'],
                        'path'     => $sDir,
                        'episodes' => $episodes,
                    ];
                }
            }
        }

        // 2. Fallback : Si aucun dossier trouvé, on cherche les vidéos à la racine
        if (empty($seasons)) {
            $episodes = $this->scanEpisodes($animeDir);
            if (!empty($episodes)) {
                $seasons[] = [
                    'number'   => 1,
                    'label'    => 'Saison 1',
                    'path'     => $animeDir,
                    'episodes' => $episodes,
                ];
            }
        }

        // 3. Tri final : Saisons (1, 2...) puis OAV (888), puis Films (999)
        usort($seasons, fn($a, $b) => $a['number'] <=> $b['number']);

        return $seasons;
    }

    // ----------------------------------------------------------------
    //  EXTRAIT LE NUMÉRO DE SAISON depuis le nom du dossier
    //  "Season 01" → 1 / "Saison 02" → 2
    // ----------------------------------------------------------------
    private function extractSeasonNumber(string $dirName): int
    {
        if (preg_match('/(\d+)/', $dirName, $m)) {
            return (int) $m[1];
        }
        return 1;
    }


    // ----------------------------------------------------------------
    //  SCAN ÉPISODES DANS UN DOSSIER
    //  Format : Anime 1 S01E01 720p VOSTFR.mp4
    // ----------------------------------------------------------------
    private function scanEpisodes(string $dir): array
    {
        $episodes = [];
        $files    = scandir($dir) ?: [];

        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::VIDEO_EXT, true)) {
                continue;
            }

            $fullPath    = $dir . '/' . $file;
            $parsed      = $this->parseEpisodeFilename($file);

            $episodes[] = [
                'filename'   => $file,
                'file_path'  => $fullPath,
                'title'      => $parsed['title'],
                'number'     => $parsed['episode'],
                'season'     => $parsed['season'],
                'quality'    => $parsed['quality'],
                'language'   => $parsed['language'],
                'stream_url' => '/stream/?path=' . urlencode($fullPath),
                'added_at'   => date('Y-m-d\TH:i:s', filemtime($fullPath) ?: 0),
            ];
        }

        // Tri par numéro d'épisode
        usort($episodes, fn($a, $b) => $a['number'] <=> $b['number']);

        return $episodes;
    }

    // ----------------------------------------------------------------
    //  PARSE LE NOM DE FICHIER
    //  "Anime 1 S01E01 720p VOSTFR.mp4"
    //  → season=1, episode=1, quality="720p", language="VOSTFR"
    // ----------------------------------------------------------------
    protected function parseEpisodeFilename(string $filename): array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $season   = 1;
        $episode  = 0;
        $quality  = null;
        $language = null;

        // 1. DÉTECTION DU NUMÉRO (Ordre de priorité)

        // Priorité A : Format standard S01E01
        if (preg_match('/S(\d{1,2})E(\d{1,3})/i', $name, $m)) {
            $season  = (int) $m[1];
            $episode = (int) $m[2];
        }
        // Priorité B : Spéciaux (Film 01, OAV 2, Special 05)
        elseif (preg_match('/(?:Film|Movie|OAV|Specials?|SP)\s*(\d{1,3})/i', $name, $m)) {
            $episode = (int) $m[1];
        }
        // Priorité C : Format E01 ou EP01
        elseif (preg_match('/(?:EP|E)(\d{1,3})/i', $name, $m)) {
            $episode = (int) $m[1];
        }
        // Priorité D : Numéro isolé (ex: "My Hero Academia - 01 - 720p")
        elseif (preg_match('/(?:^|\s|-)(\d{1,3})(?:\s|-|$)/', $name, $m)) {
            $episode = (int) $m[1];
        }

        // 2. QUALITÉ (720p, 1080p, etc.)
        if (preg_match('/\b(4K|2160p|1080p|720p|480p|360p)\b/i', $name, $m)) {
            $quality = strtolower($m[1]);
        }

        // 3. LANGUE (VOSTFR, VF, etc.) — collecte tous les tags, garde le plus spécifique en premier
        if (preg_match_all('/\b(VOSTFR|VOSTA|VOSTEN|MULTI|DUAL|VF|VO)\b/i', $name, $ms)) {
            $priority = ['VOSTFR' => 6, 'VOSTA' => 5, 'VOSTEN' => 4, 'MULTI' => 3, 'DUAL' => 3, 'VF' => 2, 'VO' => 1];
            $tags = array_map('strtoupper', $ms[1]);
            usort($tags, fn($a, $b) => ($priority[$b] ?? 0) <=> ($priority[$a] ?? 0));
            $language = implode(' ', array_unique($tags));
        }

        // 4. NETTOYAGE DU TITRE
        // On retire tout ce qui ressemble à des tags techniques ou des numéros pour le titre "propre"
        $cleanTitle = $name;

        // Liste des patterns à supprimer pour le titre
        $patternsToRemove = [
            '/S\d{1,2}E\d{1,3}.*/i',               // Supprime à partir de S01E01...
            '/\b(Film|Movie|OAV|Special|SP)\s*\d*.*/i', // Supprime à partir de Film 01...
            '/\b(4K|2160p|1080p|720p|480p|VOSTFR|VOSTA|VF|MULTI|DUAL)\b.*/i', // Supprime tags techniques
            '/[\(\[\{].*?[\)\]\}]/',              // Supprime tout ce qui est entre parenthèses ou crochets
        ];

        foreach ($patternsToRemove as $pattern) {
            $cleanTitle = preg_replace($pattern, '', $cleanTitle);
        }

        $cleanTitle = trim(preg_replace('/\s+/', ' ', $cleanTitle)); // Nettoie les espaces doubles

        // Si le titre est vide après nettoyage, on met un fallback
        if (empty($cleanTitle)) {
            $cleanTitle = "Épisode $episode";
        }

        return [
            'season'   => $season,
            'episode'  => $episode ?: 1,
            'quality'  => $quality,
            'language' => $language,
            'title'    => $cleanTitle,
        ];
    }

    // ----------------------------------------------------------------
    //  CHERCHE LE JSON METADATA (retourne le JSON brut)
    //  La normalisation est faite par resolveMetaFields()
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
    //  CHERCHE LA COVER IMAGE (premier jpg/png/webp à la racine)
    // ----------------------------------------------------------------
    private function findCoverImage(string $dir): ?string
    {
        // Priorité : cover.* explicite
        foreach (self::IMAGE_EXT as $ext) {
            $candidate = $dir . '/cover.' . $ext;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        // Sinon premier fichier image trouvé à la racine (pas dans les sous-dossiers)
        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($dir . '/' . $file)) continue; // ignorer les dossiers
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, self::IMAGE_EXT, true)) {
                return $dir . '/' . $file;
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    //  HELPERS — slug
    // ----------------------------------------------------------------
    public function dirToSlug(string $name): string
    {
        return urlencode($name);
    }

    public function slugToDir(string $slug): string
    {
        return urldecode($slug);
    }
}