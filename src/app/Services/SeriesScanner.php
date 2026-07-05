<?php

declare(strict_types=1);

require_once __DIR__ . '/AnimeScanner.php';

// ============================================================
//  SeriesScanner — Scan des séries depuis D:\Series
//  Identique à AnimeScanner mais :
//   - Chemin configurable via SERIES_PATH
//   - Regex épisode tolère les tags H264/H265/x265/HEVC
//   - Cache propre (static:: par classe)
// ============================================================
class SeriesScanner extends AnimeScanner
{
    protected static ?array $cache = null;

    public function __construct()
    {
        parent::__construct($_ENV['SERIES_PATH'] ?? '/media/series');
    }

    public function getAllSeries(): array
    {
        return $this->getAllAnimes();
    }

    public function getSerieBySlug(string $slug): ?array
    {
        return $this->getAnimeBySlug($slug);
    }

    // Surcharge : ajoute H264/H265/HEVC/x265 à la liste des tags à ignorer
    protected function parseEpisodeFilename(string $filename): array
    {
        // Nettoie les tags codec avant de passer au parser parent
        $cleaned = preg_replace('/\b(H\.?264|H\.?265|x264|x265|HEVC|AVC)\b/i', '', $filename);
        return parent::parseEpisodeFilename($cleaned);
    }
}
