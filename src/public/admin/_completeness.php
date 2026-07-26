<?php

declare(strict_types=1);

/**
 * Calcule le score de complétude d'un média.
 *
 * @return array{
 *   checks: array<string, bool>,
 *   score: int,
 *   total: int,
 *   pct: int,
 *   status: 'complete'|'partial'|'incomplete',
 *   missing: string[]
 * }
 */
function mediaCompleteness(array $meta, string $type, string $dirPath): array
{
    $checks = [
        'cover'    => !empty($meta['cover']) || !empty($meta['banner']) || !empty(glob($dirPath . '/cover.*')),
        'synopsis' => strlen(trim($meta['overview'] ?? '')) > 10,
        'genres'   => !empty($meta['genres']),
        'cast'     => !empty($meta['cast']),
        'score'    => ($meta['score'] ?? 0) > 0,
    ];

    if ($type !== 'film') {
        $checks['saisons'] = !empty($meta['seasons']);
    }

    $score   = array_sum($checks);
    $total   = count($checks);
    $pct     = (int) round($score / $total * 100);
    $status  = $pct >= 80 ? 'complete' : ($pct >= 40 ? 'partial' : 'incomplete');
    $missing = array_keys(array_filter($checks, fn($v) => !$v));

    return compact('checks', 'score', 'total', 'pct', 'status', 'missing');
}

/**
 * Labels lisibles pour les champs manquants.
 */
function completenessFieldLabel(string $field): string
{
    return match ($field) {
        'cover'    => 'Cover',
        'synopsis' => 'Synopsis',
        'genres'   => 'Genres',
        'cast'     => 'Cast',
        'score'    => 'Score',
        'saisons'  => 'Saisons manquantes',
        default    => $field,
    };
}

/**
 * Détecte les doublons par tmdb_id dans la liste des médias.
 *
 * @return array<int, array<int, array>> Groupes de doublons (tmdb_id => [media, ...])
 */
function detectDuplicates(array $allMedia): array
{
    $byTmdbId = [];
    foreach ($allMedia as $m) {
        $tid = $m['tmdb_id'] ?? null;
        if ($tid) {
            $byTmdbId[(int) $tid][] = $m;
        }
    }
    return array_filter($byTmdbId, fn($g) => count($g) > 1);
}
