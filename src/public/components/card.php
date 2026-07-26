<?php

declare(strict_types=1);

require_once __DIR__ . '/icon.php';

/**
 * Retourne les classes CSS badge pour une langue donnée.
 * Ex: 'VOSTFR' → 'badge badge--lang badge--vostfr'
 */
if (!function_exists('langBadgeClass')) {
    function langBadgeClass(?string $lang): string
    {
        if (!$lang) return 'badge badge--lang';
        $map = ['VOSTFR' => 'vostfr', 'VOSTA' => 'vostfr', 'VOSTEN' => 'vostfr', 'VF' => 'vf', 'MULTI' => 'multi', 'DUAL' => 'multi', 'VO' => 'vo'];
        $key = strtoupper(trim($lang));
        $mod = $map[$key] ?? null;
        return 'badge badge--lang' . ($mod ? ' badge--' . $mod : '');
    }
}

if (!function_exists('renderLangBadges')) {
    function renderLangBadges(?string $lang): string
    {
        if (!$lang) return '';
        $tags = array_unique(array_filter(array_map('trim', explode(' ', strtoupper($lang)))));
        return implode('', array_map(fn($t) => '<span class="' . langBadgeClass($t) . '">' . htmlspecialchars($t) . '</span>', $tags));
    }
}

/**
 * Render a media card.
 *
 * @param array  $item  Data array (slug, title, cover_url, score, year, main_language, …)
 * @param string $type  'anime' | 'serie' | 'film'
 * @param array  $opts  {
 *   href?:         string   – override destination URL
 *   show_fav?:     bool     – show the ♡ fav icon (default false)
 *   data_attrs?:   array    – key→value map added as data-* attributes on the <a>
 *   match_genres?: string[] – genre badges shown on suggestion cards
 * }
 */
function render_card(array $item, string $type, array $opts = []): string
{
    // ── URL ───────────────────────────────────────────────────────────────────
    $href = $opts['href'] ?? match ($type) {
        'film'  => '/content/?type=film&slug='  . urlencode($item['slug']),
        'serie' => '/content/?type=serie&slug=' . urlencode($item['slug']),
        default => '/content/?type=anime&slug=' . urlencode($item['slug']),
    };

    // ── Score ─────────────────────────────────────────────────────────────────
    $rawScore = (float) ($item['score'] ?? 0);
    $score    = $rawScore > 0 ? number_format($rawScore, 1) : null;

    // ── Title ─────────────────────────────────────────────────────────────────
    $title = $type === 'film'
        ? ($item['title'] ?? '')
        : (($item['title_english'] ?? null) ?: ($item['title'] ?? ''));

    // ── Badge / icon ──────────────────────────────────────────────────────────
    $badge = match ($type) { 'film' => 'Film', 'serie' => 'Série', default => 'Anime' };
    $icon  = match ($type) { 'film' => lc('clapperboard', 36), 'serie' => lc('tv', 36), default => lc('tv-2', 36) };

    // ── Poster ────────────────────────────────────────────────────────────────
    $coverUrl   = $item['cover_url'] ?? null;
    $posterHtml = $coverUrl
        ? '<img class="card__poster" src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" loading="lazy">'
        : '<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">' . $icon . '</span></div></div>';

    // ── Badges (score / soon / trending) ─────────────────────────────────────
    $scoreBadge = $score
        ? '<div class="card__score">' . lc('star', 11, 'card__score-star') . $score . '</div>'
        : '';
    $noFile = ($item['episodes_local'] ?? 1) === 0;
    $unavailable = $opts['unavailable'] ?? false;
    $soonBadge = $unavailable
        ? '<div class="card__badge--soon card__badge--unavailable">Non Disponible</div>'
        : ($noFile ? '<div class="card__badge--soon">Prochainement</div>' : '');
    $trendingBadge = ($item['is_trending'] ?? false)
        ? '<div class="card__trending"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"></path></svg>Tendance</div>'
        : '';

    // ── Lang badge ────────────────────────────────────────────────────────────
    $langHtml = '';
    $lang = $item['main_language'] ?? null;
    if ($lang) {
        static $lmap = ['VOSTFR' => 'vostfr', 'VOSTA' => 'vostfr', 'VOSTEN' => 'vostfr', 'VF' => 'vf', 'MULTI' => 'multi', 'DUAL' => 'multi', 'VO' => 'vo'];
        $tags = array_unique(array_filter(array_map('trim', explode(' ', strtoupper($lang)))));
        $spans = implode('', array_map(function($t) use ($lmap) {
            $cls = $lmap[$t] ?? null;
            return '<span class="card__lang' . ($cls ? ' card__lang--' . $cls : '') . '">' . htmlspecialchars($t) . '</span>';
        }, $tags));
        $langHtml = $spans ? '<div class="card__langs">' . $spans . '</div>' : '';
    }

    // ── Year ──────────────────────────────────────────────────────────────────
    $yearHtml = ($item['year'] ?? null)
        ? '<span class="card__meta-year">' . $item['year'] . '</span>'
        : '';

    // ── Match genres (suggestions) ────────────────────────────────────────────
    $matchHtml = '';
    foreach ($opts['match_genres'] ?? [] as $mg) {
        $matchHtml .= '<span>' . htmlspecialchars((string) $mg) . '</span>';
    }
    if ($matchHtml) $matchHtml = '<div class="card__match-tags">' . $matchHtml . '</div>';

    // ── Extra data-* attributes ───────────────────────────────────────────────
    $dataAttrs = '';
    foreach ($opts['data_attrs'] ?? [] as $k => $v) {
        $dataAttrs .= ' data-' . htmlspecialchars($k) . '="' . htmlspecialchars((string) $v) . '"';
    }

    // ── Bouton créer dossier ──────────────────────────────────────────────────
    $createBtn = '';
    if (!empty($opts['create_folder'])) {
        $cf = $opts['create_folder'];
        $createBtn = '<button class="card__create-btn"'
            . ' data-tmdb-id="' . (int)($cf['tmdb_id'] ?? 0) . '"'
            . ' data-title="' . htmlspecialchars($cf['title'] ?? $title) . '"'
            . ' data-year="' . (int)($cf['year'] ?? ($item['year'] ?? 0)) . '"'
            . ' title="Créer le dossier">'
            . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>'
            . ' Créer le dossier'
            . '</button>';
    }

    $cardClass = 'card' . ($unavailable ? ' card--unavailable' : '');

    return '<a href="' . htmlspecialchars($href) . '" class="' . $cardClass . '" data-type="' . htmlspecialchars($type) . '"' . $dataAttrs . '>'
        . '<div class="card__media">'
            . $posterHtml
            . '<div class="card__overlay"></div>'
            . $trendingBadge
            . $scoreBadge
            . '<div class="card__play"><div class="card__play-btn">' . lc('play', 16) . '</div></div>'
            . '<div class="card__body">'
                . '<div class="card__title">' . htmlspecialchars($title) . '</div>'
                . '<div class="card__meta">'
                    . '<span class="card__meta-badge">' . $badge . '</span>'
                    . $yearHtml
                . '</div>'
                . $matchHtml
                . ($langHtml || $soonBadge
                    ? '<div class="card__bottom-badges">' . $langHtml . $soonBadge . '</div>'
                    : '')
            . '</div>'
        . '</div>'
        . $createBtn
    . '</a>';
}
