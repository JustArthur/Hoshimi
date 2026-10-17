<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/IndexManager.php';
require_once __DIR__ . '/../../app/Services/MediaProbe.php';

$type = $_GET['type'] ?? 'anime';

preg_match('/(?:^|&)slug=([^&]*)/', $_SERVER['QUERY_STRING'] ?? '', $_m);
$slug          = isset($_m[1]) ? rawurldecode($_m[1]) : '';
$seasonNumber  = max(1, (int) ($_GET['saison']  ?? 1));
$episodeNumber = max(1, (int) ($_GET['episode'] ?? 1));

if ($type === 'film') {
    $anime = $slug ? IndexManager::getBySlug('film', $slug) : null;
    $seasonNumber  = 1;
    $episodeNumber = 1;
} else {
    $imType = in_array($type, ['anime', 'serie'], true) ? $type : 'anime';
    $anime  = $slug ? IndexManager::getBySlug($imType, $slug) : null;
}

if (!$anime) {
    http_response_code(404);
    header('Location: /');
    exit;
}

$season = null;
foreach ($anime['seasons'] as $s) {
    if ($s['number'] === $seasonNumber) { $season = $s; break; }
}
$season ??= $anime['seasons'][0] ?? null;

if (!$season || empty($season['episodes'])) {
    header('Location: /content/?type=anime&slug=' . urlencode($slug));
    exit;
}

$episode    = null;
$episodeIdx = 0;
foreach ($season['episodes'] as $i => $ep) {
    if ($ep['number'] === $episodeNumber) { $episode = $ep; $episodeIdx = $i; break; }
}
$episode    ??= $season['episodes'][0];
$episodeIdx   = $episodeIdx ?: 0;

$prevEpisode = $season['episodes'][$episodeIdx - 1] ?? null;
$nextEpisode = $season['episodes'][$episodeIdx + 1] ?? null;

$nextSeasonNumber = null;
$nextSeasonLabel  = null;
if (!$nextEpisode) {
    foreach ($anime['seasons'] as $s) {
        if ($s['number'] > $seasonNumber && $s['number'] !== 999 && !empty($s['episodes'])) {
            $nextSeasonNumber = $s['number'];
            $nextSeasonLabel  = $s['label'];
            $nextEpisode      = $s['episodes'][0];
            break;
        }
    }
}

$baseUrl = '/player/?slug=' . urlencode($slug) . ($type !== 'anime' ? '&type=' . urlencode($type) : '');
$prevUrl = $prevEpisode ? $baseUrl . '&saison=' . $seasonNumber . '&episode=' . $prevEpisode['number'] : null;

if ($nextEpisode) {
    $targetSeason = $nextSeasonNumber ?? $seasonNumber;
    $nextUrl = $baseUrl . '&saison=' . $targetSeason . '&episode=' . $nextEpisode['number'];
} else {
    $nextUrl = null;
}

// Bascule Jellyfin — activable via JELLYFIN_STREAMING_ENABLED (.env),
// utilisé seulement si le fichier est déjà mappé côté Jellyfin
$useJellyfin = false;
if (($_ENV['JELLYFIN_STREAMING_ENABLED'] ?? 'false') === 'true') {
    $jellyfinMapFile = __DIR__ . '/../../data/jellyfin-map.json';
    if (file_exists($jellyfinMapFile)) {
        $jellyfinMap = json_decode((string) file_get_contents($jellyfinMapFile), true) ?: [];
        $useJellyfin = isset($jellyfinMap[realpath($episode['file_path']) ?: $episode['file_path']]);
    }
}

$streamUrl   = $useJellyfin
    ? '/stream/jellyfin.php?path=' . rawurlencode($episode['file_path'])
    : '/stream/index.php?path=' . rawurlencode($episode['file_path']);
$progressKey = md5($episode['file_path']);

// Sous-titres
$subtitles = [];
$videoDir  = dirname($episode['file_path']);
$langLabels = [
    'fr' => 'Français', 'en' => 'English', 'ja' => '日本語',
    'es' => 'Español',  'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português',
];

// Pistes audio — l'index transmis diffère selon le backend :
// - stream/index.php (ffmpeg)   attend l'index relatif aux pistes audio (-map 0:a:N)
// - stream/jellyfin.php (HLS)   attend l'index absolu du conteneur (AudioStreamIndex)
$audioTracks = [];
foreach (MediaProbe::probeAudioTracks($episode['file_path']) as $track) {
    $lang  = MediaProbe::normalizeLang($track['lang']);
    $label = $track['title'] ?: ($lang ? ($langLabels[$lang] ?? strtoupper($lang)) : 'Piste ' . ($track['relativeIndex'] + 1));
    $audioTracks[] = [
        'index' => $useJellyfin ? $track['absoluteIndex'] : $track['relativeIndex'],
        'label' => $label,
        'lang'  => $lang,
    ];
}

$subtitleDir = null;
foreach ([$videoDir . '/subtitle', dirname($videoDir) . '/subtitle'] as $candidate) {
    if (is_dir($candidate)) { $subtitleDir = $candidate; break; }
}

$videoFilename = pathinfo($episode['file_path'], PATHINFO_FILENAME);
preg_match('/S(\d{1,2})E(\d{1,2})/i', $videoFilename, $epMatch);
$epCode = $epMatch[0] ?? null;

$vttByLang = [];
$vttFiles  = $subtitleDir
    ? (glob($subtitleDir . '/*.vtt') ?: [])
    : (glob($videoDir . '/*.vtt') ?: []);

foreach ($vttFiles as $vttPath) {
    $vttName = pathinfo($vttPath, PATHINFO_FILENAME);
    if ($subtitleDir && $epCode && !preg_match('/' . preg_quote($epCode, '/') . '/i', $vttName)) continue;
    $parts = explode('.', $vttName);
    $last  = strtolower(end($parts));
    $prev  = count($parts) >= 2 ? strtolower($parts[count($parts) - 2]) : '';
    if (isset($langLabels[$last])) {
        $lang    = $last;
        $vttType = in_array($prev, ['complet', 'forced'], true) ? $prev : 'complet';
    } else {
        $lang    = 'fr';
        $vttType = in_array($last, ['complet', 'forced'], true) ? $last : 'complet';
    }
    $vttByLang[$lang][$vttType] = $vttPath;
}

foreach ($vttByLang as $lang => $types) {
    $hasComplet = isset($types['complet']);
    $hasForced  = isset($types['forced']);
    $baseLabel  = $langLabels[$lang] ?? strtoupper($lang);
    if ($hasForced) {
        $subtitles[] = ['url' => '/stream/subtitle.php?path=' . rawurlencode($types['forced']), 'lang' => $lang, 'label' => $baseLabel . ' (Auto)', 'show' => true];
    }
    if ($hasComplet) {
        $subtitles[] = ['url' => '/stream/subtitle.php?path=' . rawurlencode($types['complet']), 'lang' => $lang, 'label' => $baseLabel, 'show' => !$hasForced && $lang === 'fr'];
    }
}

// TMDB épisodes pour l'overlay — calculé pour une saison donnée
function getTmdbEpisodesForSeason(array $anime, array $season): array
{
    $tmdbEpisodes = [];
    $folderName   = basename($season['path'] ?? '');
    $metaSeasons  = ($anime['metadata']['seasons'] ?? []);
    if (!empty($metaSeasons[$folderName]['episodes'])) {
        foreach (array_values($metaSeasons[$folderName]['episodes']) as $idx => $ep) {
            $tmdbEpisodes[$idx + 1] = $ep;
        }
    } elseif (!empty($season['episodes'])) {
        $firstEpPath  = $season['episodes'][0]['file_path'] ?? '';
        $seasonFolder = $firstEpPath ? dirname($firstEpPath) : '';
        foreach ([$seasonFolder . '/metadata.json', dirname($seasonFolder) . '/metadata.json'] as $candidate) {
            if ($candidate !== '/metadata.json' && file_exists($candidate)) {
                $seasonJson = @json_decode(@file_get_contents($candidate), true) ?: [];
                if (!empty($seasonJson['episodes']) && isset($seasonJson['season_number'])) {
                    $localNum = 1;
                    foreach ($seasonJson['episodes'] as $tmdbEp) { $tmdbEpisodes[$localNum++] = $tmdbEp; }
                    break;
                }
            }
        }
    }
    return $tmdbEpisodes;
}

$tmdbEpisodes = $season ? getTmdbEpisodesForSeason($anime, $season) : [];

$backUrl = '/content/?type=' . $type . '&slug=' . urlencode($slug);
?>
<!DOCTYPE html>
<html lang="fr" style="scrollbar-gutter: auto; scrollbar-width: none; ::-webkit-scrollbar { display: none; };">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($type === 'film') {
            echo htmlspecialchars($anime['title']);
        } else {
            $epNum = sprintf('S%02dE%02d', $seasonNumber, $episodeNumber);
            echo $epNum . ' · ' . htmlspecialchars($anime['title_english'] ?: $anime['title']);
        }
    ?> — Hoshimi</title>
    <link rel="stylesheet" href="../css/hoshimi.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video-js.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.10.0/video.min.js"></script>
</head>
<body class="player-body--fullscreen">

    <div class="video-wrapper" id="hpl-wrapper">

        <!-- Overlay custom -->
        <div class="hpl-overlay" id="hpl-overlay">

            <!-- Barre du haut -->
            <div class="hpl-top" id="hpl-top">
                <div class="hpl-top__left">
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="hpl-icon-btn" title="Retour">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
                    </a>
                    <div class="hpl-title-block">
                        <span class="hpl-anime-title"><?= htmlspecialchars($anime['title_english'] ?? $anime['title']) ?></span>
                        <?php if ($type !== 'film' && $season['number'] !== 999): ?>
                        <div class="hpl-ep-nav">
                            <?php if ($prevUrl): ?>
                            <a href="<?= htmlspecialchars($prevUrl) ?>" class="hpl-ep-nav-btn" title="Épisode précédent">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <?php else: ?><span class="hpl-ep-nav-btn is-disabled"></span><?php endif; ?>
                            <span class="hpl-ep-pill">S<?= str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) ?>E<?= str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT) ?></span>
                            <?php if ($nextUrl): ?>
                            <a href="<?= htmlspecialchars($nextUrl) ?>" class="hpl-ep-nav-btn" title="Épisode suivant">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <?php else: ?><span class="hpl-ep-nav-btn is-disabled"></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hpl-top__right"></div>
            </div>

            <!-- Zone de clic centrale -->
            <div class="hpl-click-zone" id="hpl-click-zone"></div>

            <!-- Barre du bas -->
            <div class="hpl-bottom" id="hpl-bottom">
                <div class="hpl-progress-wrap" id="hpl-progress-wrap">
                    <div class="hpl-progress-track">
                        <div class="hpl-buffer" id="hpl-buffer"></div>
                        <div class="hpl-played" id="hpl-played"></div>
                        <div class="hpl-thumb" id="hpl-thumb"></div>
                        <div class="hpl-hover-tooltip" id="hpl-thumb-tooltip">0:00</div>
                    </div>
                </div>
                <div class="hpl-controls">
                    <div class="hpl-controls__left">
                        <button class="hpl-icon-btn" id="hpl-play-btn" title="Lecture/Pause">
                            <svg class="hpl-icon-play" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                            <svg class="hpl-icon-pause" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><rect x="14" y="3" width="5" height="18" rx="1"/><rect x="5" y="3" width="5" height="18" rx="1"/></svg>
                        </button>
                        <button class="hpl-icon-btn" id="hpl-back10" title="Reculer 10s">
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                        </button>
                        <button class="hpl-icon-btn" id="hpl-fwd10" title="Avancer 10s">
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path></svg>
                        </button>
                        <div class="hpl-vol-group">
                            <button class="hpl-icon-btn" id="hpl-mute-btn" title="Volume">
                                <svg class="hpl-icon-vol hpl-icon-vol-high" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/></svg>
                                <svg class="hpl-icon-vol hpl-icon-vol-low" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/></svg>
                                <svg class="hpl-icon-muted" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/></svg>
                            </button>
                            <div class="hpl-vol-slider-wrap" id="hpl-vol-wrap">
                                <div class="hpl-vol-track">
                                    <div class="hpl-vol-fill" id="hpl-vol-fill"></div>
                                    <div class="hpl-vol-thumb" id="hpl-vol-thumb"></div>
                                </div>
                            </div>
                        </div>
                        <span class="hpl-time-display" id="hpl-time">0:00 / 0:00</span>
                    </div>
                    <div class="hpl-controls__right">
                        <?php if ($type !== 'film' && $season['number'] !== 999): ?>
                        <button class="hpl-icon-btn" id="hpl-eplist-btn" title="Épisodes">
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/></svg>
                        </button>
                        <?php endif; ?>
                        <button class="hpl-icon-btn" id="hpl-settings-btn" title="Paramètres">
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="hpl-icon-btn" id="hpl-fs-btn" title="Plein écran">
                            <svg class="hpl-icon-fs-enter" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                            <svg class="hpl-icon-fs-exit" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panneau liste d'épisodes (bandeau latéral) -->
            <?php if ($type !== 'film' && $season['number'] !== 999):
                $visibleSeasons = array_filter($anime['seasons'], fn($s) => $s['number'] !== 999 && !empty($s['episodes']));
            ?>
            <div class="hpl-eplist-panel" id="hpl-eplist-panel">
                <div class="hpl-eplist-header">
                    <span class="hpl-eplist-title">Épisodes</span>
                    <button class="hpl-eplist-close" id="hpl-eplist-close" title="Fermer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <div class="hpl-eplist-season-select" id="hpl-eplist-season-select">
                    <button class="hpl-eplist-season-btn" id="hpl-eplist-season-btn" <?= count($visibleSeasons) <= 1 ? 'disabled' : '' ?>>
                        <span class="hpl-eplist-season-btn__label" id="hpl-eplist-season-btn-label">Saison <?= $seasonNumber ?></span>
                        <span class="hpl-eplist-season-btn__count" id="hpl-eplist-season-btn-count"><?= count($season['episodes']) ?> ép</span>
                        <?php if (count($visibleSeasons) > 1): ?>
                        <svg class="hpl-eplist-season-btn__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        <?php endif; ?>
                    </button>
                    <?php if (count($visibleSeasons) > 1): ?>
                    <div class="hpl-eplist-season-dropdown" id="hpl-eplist-season-dropdown">
                        <?php foreach ($visibleSeasons as $s): ?>
                        <button class="hpl-eplist-season-option <?= $s['number'] === $seasonNumber ? 'is-active' : '' ?>"
                                data-season="<?= $s['number'] ?>"
                                data-season-label="Saison <?= $s['number'] ?>"
                                data-season-count="<?= count($s['episodes']) ?> ép">
                            <?= htmlspecialchars(strtoupper($s['label'])) ?>
                            <span class="hpl-eplist-season-option__count"><?= count($s['episodes']) ?> ép</span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hpl-eplist-body" id="hpl-eplist-body">
                    <?php foreach ($visibleSeasons as $s):
                        $isActiveSeason  = $s['number'] === $seasonNumber;
                        $seasonTmdbEps   = $isActiveSeason ? $tmdbEpisodes : getTmdbEpisodesForSeason($anime, $s);
                    ?>
                    <div class="hpl-eplist-season-block <?= $isActiveSeason ? 'is-visible' : '' ?>" data-season="<?= $s['number'] ?>">
                        <?php foreach ($s['episodes'] as $ep):
                            $isActive   = $isActiveSeason && $ep['number'] === $episode['number'];
                            $epUrl      = $baseUrl . '&saison=' . $s['number'] . '&episode=' . $ep['number'];
                            $tmdbEp     = $seasonTmdbEps[$ep['number']] ?? null;
                            $epName     = $tmdbEp['name'] ?? ($ep['title'] !== '' ? $ep['title'] : null);
                            $epOverview = $tmdbEp['overview'] ?? '';
                            $epStill    = $tmdbEp['still'] ?? null;
                        ?>
                        <a href="<?= htmlspecialchars($epUrl) ?>" class="hpl-eplist-card <?= $isActive ? 'is-active' : '' ?>">
                            <div class="hpl-eplist-thumb">
                                <?php if ($epStill): ?>
                                <img src="<?= htmlspecialchars($epStill) ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <?php if ($isActive): ?>
                                <span class="hpl-eplist-thumb-play">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="hpl-eplist-info">
                                <div class="hpl-eplist-card-title">E<?= str_pad((string)$ep['number'], 2, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($epName ?? ('Épisode ' . $ep['number'])) ?></div>
                                <?php if ($epOverview): ?>
                                <div class="hpl-eplist-card-desc"><?= htmlspecialchars($epOverview) ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Panneau paramètres -->
            <div class="hpl-settings-panel" id="hpl-settings-panel">
                <div class="hpl-settings-menu is-visible" id="hpl-menu-main">
                    <div class="hpl-settings-header">Paramètres</div>
                    <?php if (!empty($subtitles)): ?>
                    <button class="hpl-settings-row" data-submenu="subtitles">
                        <span class="hpl-settings-row__label">Sous-titres</span>
                        <div class="hpl-settings-row__right">
                            <span class="hpl-settings-row__val" id="hpl-cur-sub">Désactivés</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </button>
                    <?php endif; ?>
                    <?php if (count($audioTracks) > 1): ?>
                    <button class="hpl-settings-row" data-submenu="audio">
                        <span class="hpl-settings-row__label">Audio</span>
                        <div class="hpl-settings-row__right">
                            <span class="hpl-settings-row__val" id="hpl-cur-audio"><?= htmlspecialchars($audioTracks[0]['label']) ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </button>
                    <?php endif; ?>
                    <button class="hpl-settings-row" data-submenu="speed">
                        <span class="hpl-settings-row__label">Vitesse</span>
                        <div class="hpl-settings-row__right">
                            <span class="hpl-settings-row__val" id="hpl-cur-speed">Normal</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </button>
                </div>
                <?php if (count($audioTracks) > 1): ?>
                <div class="hpl-settings-menu" id="hpl-menu-audio">
                    <div class="hpl-submenu-header">
                        <button class="hpl-submenu-back" data-back="main">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <span class="hpl-submenu-title">Audio</span>
                    </div>
                    <?php foreach ($audioTracks as $i => $track): ?>
                    <button class="hpl-settings-option <?= $i === 0 ? 'is-active' : '' ?>" data-audio-idx="<?= $track['index'] ?>"><?= htmlspecialchars($track['label']) ?><svg class="hpl-opt-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($subtitles)): ?>
                <div class="hpl-settings-menu" id="hpl-menu-subtitles">
                    <div class="hpl-submenu-header">
                        <button class="hpl-submenu-back" data-back="main">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <span class="hpl-submenu-title">Sous-titres</span>
                    </div>
                    <button class="hpl-settings-option is-active" data-track-idx="-1">Désactivés<svg class="hpl-opt-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                    <?php foreach ($subtitles as $i => $sub): ?>
                    <button class="hpl-settings-option" data-track-idx="<?= $i ?>"><?= htmlspecialchars($sub['label']) ?><svg class="hpl-opt-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="hpl-settings-menu" id="hpl-menu-speed">
                    <div class="hpl-submenu-header">
                        <button class="hpl-submenu-back" data-back="main">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <span class="hpl-submenu-title">Vitesse</span>
                    </div>
                    <?php foreach ([['0.5','×0.5'],['0.75','×0.75'],['1','Normal'],['1.25','×1.25'],['1.5','×1.5'],['2','×2']] as [$spVal, $spLbl]): ?>
                    <button class="hpl-settings-option <?= $spVal === '1' ? 'is-active' : '' ?>" data-speed="<?= $spVal ?>"><?= $spLbl ?><svg class="hpl-opt-check" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
        <!-- fin overlay -->

        <div class="hpl-buffering" id="hpl-buffering">
            <span class="spinner spinner--lg"></span>
        </div>

        <video
            id="hoshimi-player"
            class="video-js vjs-default-skin vjs-big-play-centered"
            controls
            preload="auto"
            poster="<?= htmlspecialchars($anime['banner_url'] ?? $anime['cover_url'] ?? '') ?>"
            data-progress-key="<?= htmlspecialchars($progressKey) ?>"
            data-next-url="<?= htmlspecialchars($nextUrl ?? '') ?>"
            data-next-title="<?= htmlspecialchars($nextEpisode['title'] ?? '') ?>"
            data-next-number="<?= $nextEpisode['number'] ?? '' ?>">
            <?php
            if ($useJellyfin) {
                $mimeType = 'application/x-mpegURL';
            } else {
                $ext      = strtolower(pathinfo($episode['file_path'], PATHINFO_EXTENSION));
                $mimeMap  = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'm4v' => 'video/mp4'];
                $mimeType = $mimeMap[$ext] ?? 'video/mp4';
            }
            ?>
            <source src="<?= htmlspecialchars($streamUrl) ?>" type="<?= $mimeType ?>">
            <?php foreach ($subtitles as $sub): ?>
            <track kind="subtitles"
                   src="<?= htmlspecialchars($sub['url']) ?>"
                   srclang="<?= htmlspecialchars($sub['lang']) ?>"
                   label="<?= htmlspecialchars($sub['label']) ?>">
            <?php endforeach; ?>
            <p class="vjs-no-js">Votre navigateur ne supporte pas la lecture vidéo.</p>
        </video>
    </div>

    <?php if ($nextEpisode): ?>
    <div class="next-toast" id="next-toast">
        <div class="next-toast__label">
            <?= $nextSeasonNumber ? htmlspecialchars($nextSeasonLabel ?? 'Saison ' . $nextSeasonNumber) . ' dans ' : 'Épisode suivant dans ' ?><span id="next-countdown">10</span>s
        </div>
        <div class="next-toast__title">
            <?= $nextSeasonNumber ? htmlspecialchars($nextSeasonLabel ?? 'Saison ' . $nextSeasonNumber) . ' — ' : '' ?>
            Ép. <?= str_pad((string)$nextEpisode['number'], 2, '0', STR_PAD_LEFT) ?>
            — <?= htmlspecialchars($nextEpisode['title']) ?>
        </div>
        <div class="next-toast__countdown">
            <div class="next-toast__countdown-bar" id="next-bar"></div>
        </div>
        <div class="next-toast__actions">
            <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn btn--primary" style="flex:1; justify-content:center;">Regarder maintenant</a>
            <button class="btn btn--ghost" onclick="cancelNext()">Annuler</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.HOSHIMI_PLAYER = {
            progressKey: <?= json_encode($progressKey) ?>,
            isFilm:      <?= $type === 'film' ? 'true' : 'false' ?>,
            subtitles:   <?= json_encode(array_map(fn($s) => ['label' => $s['label'], 'show' => $s['show'], 'lang' => $s['lang']], $subtitles)) ?>,
            audioTracks: <?= json_encode($audioTracks) ?>,
            streamUrl:   <?= json_encode($streamUrl) ?>,
        };
        window.HOSHIMI_NEXT_URL = <?= json_encode($nextUrl) ?>;
    </script>
    <script src="../js/storage.js"></script>
    <script src="../js/player.js"></script>
    <script src="../js/player-next.js"></script>

</body>
</html>
