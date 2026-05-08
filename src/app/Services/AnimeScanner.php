<?php
// ============================================================
//  AnimeScanner — Scan des animes locaux depuis Y:\Animes
//  Format : Anime 1/Season 01/Anime 1 S01E01 720p VOSTFR.mp4
//  Lit les fichiers JSON AniList + cover image à la racine
// ============================================================

declare(strict_types=1);

class AnimeScanner
{
    private string $animesPath;

    // Extensions image acceptées pour la cover
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    // Extensions vidéo pour détecter les épisodes
    private const VIDEO_EXT = ['mkv', 'mp4', 'avi', 'webm'];

    public function __construct()
    {
        $this->animesPath = $_ENV['ANIMES_PATH'] ?? '/media/animes';
    }

    // ----------------------------------------------------------------
    //  CATALOGUE COMPLET — retourne tous les animes
    // ----------------------------------------------------------------
    public function getAllAnimes(): array
    {
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

        // Tri alphabétique par défaut
        usort($animes, fn($a, $b) => strcmp($a['title'], $b['title']));

        return $animes;
    }

    // ----------------------------------------------------------------
    //  UN ANIME PAR SLUG (nom de dossier encodé)
    // ----------------------------------------------------------------
    public function getAnimeBySlug(string $slug): ?array
    {
        $dirName = $this->slugToDir($slug);
        $path    = $this->animesPath . '/' . $dirName;

        if (!is_dir($path)) {
            return null;
        }

        return $this->scanAnimeDir($path);
    }

    // ----------------------------------------------------------------
    //  SCAN D'UN DOSSIER ANIME
    // ----------------------------------------------------------------
    private function scanAnimeDir(string $dir): ?array
    {
        $name = basename($dir);

        // --- JSON AniList (nom-anime-metadata.json) ---
        $metadata = $this->findMetadataJson($dir);

        // --- Cover image (premier jpg/png/webp trouvé à la racine) ---
        $coverFile = $this->findCoverImage($dir);
        $coverUrl  = $coverFile
            ? '/stream-image/?path=' . urlencode($coverFile)
            : null;

        // --- Saisons et épisodes ---
        $seasons = $this->scanSeasons($dir);

        // Titre : priorité JSON > nom du dossier
        $title         = $metadata['title']['french']
            ?? $metadata['title']['romaji']
            ?? $metadata['title']['english']
            ?? $name;;

        // Extraction de la langue principale (depuis le premier épisode de la première saison)
        $mainLanguage = null;
        if (!empty($seasons) && !empty($seasons[0]['episodes'])) {
            $mainLanguage = $seasons[0]['episodes'][0]['language'] ?? 'VOSTFR';
        }

        return [
            'slug'           => $this->dirToSlug($name),
            'dir_name'       => $name,
            'dir_path'       => $dir,
            'title'          => $title,
            'title_original' => $metadata['title']['native'] ?? null,
            'title_romaji'   => $metadata['title']['romaji'] ?? $name,
            'title_english'  => $metadata['title']['english'] ?? $name,
            'cover_url'      => $coverUrl,
            'cover_path'     => $coverFile,
            'accent_color'   => $metadata['coverImage']['color'] ?? null,
            'banner_url'     => $metadata['bannerImage'] ?? null,
            'synopsis'       => $metadata['description'] ?? null,
            'genres'         => $metadata['genres'] ?? [],
            'status'         => $metadata['status'] ?? 'UNKNOWN',
            'episodes_total' => $metadata['episodes'] ?? null,
            'main_language'  => $mainLanguage ?? null,
            'score'          => $metadata['averageScore'] ?? null,
            'year'           => $metadata['seasonYear']
                ?? $metadata['startDate']['year']
                ?? null,
            'format'         => $metadata['format'] ?? 'TV',
            'studio'         => $metadata['studios']['nodes'][0]['name'] ?? null,
            'anilist_id'     => $metadata['id'] ?? null,
            'anidb_id'       => $metadata['idMal'] ?? null,
            'trailer'        => $metadata['trailer'] ?? null,
            'seasons'        => $seasons,
            'episodes_local' => array_sum(array_map(
                fn($s) => count($s['episodes']),
                $seasons
            )),
            'metadata'       => $metadata,
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

        // 1. On scanne TOUS les sous-dossiers du répertoire de l'animé
        $allSubDirs = glob($animeDir . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($allSubDirs as $sDir) {
            $folderName = basename($sDir);
            $folderNameLower = strtolower($folderName);

            $targetSeason = null;

            // Détection du type de dossier
            if (preg_match('/^(season|saison)\s*(\d+)/i', $folderName, $m)) {
                // C'est une saison normale
                $num = (int)$m[2];
                $targetSeason = [
                    'number' => $num,
                    'label'  => 'Saison ' . $num
                ];
            } elseif (str_contains($folderNameLower, 'film') || str_contains($folderNameLower, 'movie')) {
                $targetSeason = [
                    'number' => 999,
                    'label'  => 'Films'
                ];
            } elseif (str_contains($folderNameLower, 'oav') || str_contains($folderNameLower, 'ova')) {
                $targetSeason = [
                    'number' => 888,
                    'label'  => 'OAV'
                ];
            } elseif (str_contains($folderNameLower, 'special')) {
                $targetSeason = [
                    'number' => 777,
                    'label'  => 'Specials'
                ];
            }

            // Si on a identifié un dossier spécial ou une saison, on scanne les épisodes
            if ($targetSeason) {
                $episodes = $this->scanEpisodes($sDir);

                // On n'ajoute la section que si elle contient des fichiers vidéo
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

    // Exemple de fonction à ajouter dans ton Scanner pour récupérer la durée
    private function getVideoDuration(string $path): ?int
    {
        // On définit un fichier de cache unique dans le dossier "storage" ou "data" de ton site
        // Assure-toi que ce dossier existe et est accessible en écriture (chmod 777)
        $globalCacheFile = __DIR__ . '/../../storage/durations_cache.json';

        // Créer le dossier storage s'il n'existe pas
        if (!is_dir(dirname($globalCacheFile))) {
            mkdir(dirname($globalCacheFile), 0777, true);
        }

        // Charger le cache global
        static $globalCache = null;
        if ($globalCache === null) {
            if (file_exists($globalCacheFile)) {
                $globalCache = json_decode(file_get_contents($globalCacheFile), true) ?: [];
            } else {
                $globalCache = [];
            }
        }

        // On utilise le chemin complet comme clé pour éviter les doublons entre animes
        $cacheKey = md5($path);

        // 1. Si déjà en cache, on retourne
        if (isset($globalCache[$cacheKey])) {
            return (int)$globalCache[$cacheKey];
        }

        // 2. Sinon, on utilise FFprobe
        $safePath = escapeshellarg($path);
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $safePath";
        $output = shell_exec($cmd);

        if ($output) {
            $duration = (int)round((float)$output);

            // 3. On met à jour le cache et on sauvegarde
            $globalCache[$cacheKey] = $duration;
            file_put_contents($globalCacheFile, json_encode($globalCache));

            return $duration;
        }

        return null;
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
                // 'duration'   => $this->getVideoDuration($fullPath),
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
    private function parseEpisodeFilename(string $filename): array
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

        // 3. LANGUE (VOSTFR, VF, etc.)
        if (preg_match('/\b(VOSTFR|VOSTA|VOSTEN|VF|VO|MULTI|DUAL)\b/i', $name, $m)) {
            $language = strtoupper($m[1]);
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
    //  CHERCHE LE JSON METADATA (metadata.json)
    // ----------------------------------------------------------------
    private function findMetadataJson(string $dir): array
    {
        $files = glob($dir . '/metadata.json') ?: [];

        if (empty($files)) {
            return [];
        }

        $content = @file_get_contents($files[0]);
        if (!$content) {
            return [];
        }

        // Retire le BOM UTF-8 si présent (\xEF\xBB\xBF)
        $content = ltrim($content, "\xEF\xBB\xBF");

        $json = json_decode($content, true);
        return $json['data']['Media'] ?? $json['Media'] ?? $json ?? [];
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