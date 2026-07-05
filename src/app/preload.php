<?php

// OPcache preload — chargé une seule fois au démarrage PHP-FPM.
// Ces classes sont compilées et disponibles dans tous les workers sans relire les fichiers.

declare(strict_types=1);

$base = dirname(__DIR__) . '/app/Services';

require_once $base . '/AnimeScanner.php';
require_once $base . '/SeriesScanner.php';
require_once $base . '/FilmScanner.php';
require_once $base . '/ThumbnailHelper.php';
