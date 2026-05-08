<?php
declare(strict_types=1);

/**
 * HOSHIMI (星見) - Fetch Metadata
 * Scan les dossiers d'animes et télécharge les infos depuis AniList.
 */

// Configuration
$animePath = getenv('ANIMES_PATH') ?: '/media/animes';
$aniListUrl = 'https://graphql.anilist.co';

// --- Couleurs console ---
function info(string $msg) { echo "\033[36m[INFO]  $msg\033[0m\n"; }
function ok(string $msg)   { echo "\033[32m[OK]    $msg\033[0m\n"; }
function warn(string $msg) { echo "\033[33m[WARN]  $msg\033[0m\n"; }
function err(string $msg)  { echo "\033[31m[ERROR] $msg\033[0m\n"; }

if (!is_dir($animePath)) {
    err("Le chemin '$animePath' est introuvable.");
    exit(1);
}

// --- Fonctions de nettoyage ---
function cleanAnimeName(string $raw): string {
    $clean = preg_replace('/\[.*?\]/', '', $raw);
    $clean = preg_replace('/\(.*?\)/', '', $clean);
    $clean = preg_replace('/ S\d{1,2}$/i', '', $clean);
    $clean = preg_replace('/ Season \d+/i', '', $clean);
    $clean = preg_replace('/ Part \d+/i', '', $clean);
    $clean = preg_replace('/\d{4}$/', '', $clean);
    $clean = preg_replace('/[_\.]/', ' ', $clean);
    return trim(preg_replace('/\s{2,}/', ' ', (string)$clean));
}

// --- Requête AniList ---
function fetchAniListData(string $animeName, string $apiUrl): ?array {
    $query = '
    query ($search: String) {
      Media(search: $search, type: ANIME) {
        id idMal title { romaji english native }
        type format status description(asHtml: false)
        startDate { year month day } season seasonYear episodes
        coverImage { extraLarge large medium color }
        bannerImage genres averageScore trailer { id site }
        studios(isMain: true) { nodes { name } }
      }
    }';

    $vars = ['search' => $animeName];
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query, 'variables' => $vars]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string)$response, true);
    return $data['data']['Media'] ?? null;
}

// --- Scan des dossiers ---
$folders = array_filter(glob($animePath . '/*'), 'is_dir');
info(count($folders) . " dossier(s) trouvé(s) dans $animePath.");

foreach ($folders as $folderPath) {
    $rawName = basename($folderPath);
    $cleanName = cleanAnimeName($rawName);
    
    // Noms de fichiers raccourcis
    $outputFile = $folderPath . "/metadata.json";
    $coverPattern = $folderPath . "/cover.*";
    
    $jsonExists = file_exists($outputFile);
    $coverExists = !empty(glob($coverPattern));

    // Si tout est déjà là, on passe au suivant sans polluer la console
    if ($jsonExists && $coverExists) {
        continue;
    }

    info("Traitement : '$rawName'...");
    usleep(500000); // Respect du rate-limit API (0.5s)

    $result = fetchAniListData($cleanName, $aniListUrl);

    if (!$result) {
        warn("  Aucun résultat trouvé sur AniList pour '$cleanName'");
        continue;
    }

    // Sauvegarde du JSON
    if (!$jsonExists) {
        $jsonContent = json_encode(['Media' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (@file_put_contents($outputFile, $jsonContent) !== false) {
            ok("  [JSON]  metadata.json créé.");
        } else {
            err("  [JSON]  Erreur d'écriture");
        }
    }

    // Sauvegarde de la Cover
    if (!$coverExists) {
        $url = $result['coverImage']['extraLarge'] ?? $result['coverImage']['large'] ?? null;
        if ($url) {
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $coverPath = $folderPath . "/cover.$ext";
            $imgData = @file_get_contents($url);
            
            if ($imgData) {
                if (@file_put_contents($coverPath, $imgData) !== false) {
                    ok("  [IMG]   cover.$ext enregistrée.");
                } else {
                    err("  [IMG]   Erreur d'écriture");
                }
            } else {
                warn("  [IMG]   Impossible de télécharger l'image.");
            }
        }
    }
}

echo "\n\033[1;35m=========================================\033[0m\n";
echo "\033[1;35mSCAN TERMINÉ AVEC SUCCÈS !\033[0m\n";
echo "\033[1;35m=========================================\033[0m\n";