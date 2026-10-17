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

    // ── Icon ──────────────────────────────────────────────────────────────────
    $icon  = match ($type) { 'film' => lc('clapperboard', 36), 'serie' => lc('tv', 36), default => lc('tv-2', 36) };

    // ── Poster ────────────────────────────────────────────────────────────────
    $coverUrl   = $item['cover_url'] ?? null;
    $posterHtml = $coverUrl
        ? '<img class="card__poster" src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" loading="lazy">'
        : '<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">' . $icon . '</span></div></div>';

    // ── Badges (score / soon / trending) ─────────────────────────────────────
    // Le badge « Déjà vu » est affiché côté client (js/card-seen.js) d'après la progression locale
    $epKeys = implode(',', array_column($item['episodes'] ?? [], 'file_key'));
    $seenBadge = $epKeys !== ''
        ? '<button type="button" class="card__seen" data-card-seen title="Marquer comme vu" aria-pressed="false">' . lc('eye', 14) . '</button>'
        : '';
    static $seenScriptEmitted = false;
    $seenScript = '';
    if (!$seenScriptEmitted) {
        $seenScriptEmitted = true;
        $seenScript = '<script src="/js/card-seen.js" defer></script>';
    }
    $noFile = ($item['episodes_local'] ?? 1) === 0;
    $unavailable = $opts['unavailable'] ?? false;

    // Bouton watchlist (+) — même clé/meta que la page détail (slug, HoshimiStorage.Watchlist)
    $wlBadge = '';
    if (!$unavailable && !empty($item['slug'])) {
        $wlMeta = json_encode([
            'title'     => $title,
            'cover_url' => $item['cover_url'] ?? '',
            'type'      => $type,
            'year'      => (int)($item['year'] ?? 0) ?: null,
            'genres'    => array_values($item['genres'] ?? []),
        ], JSON_UNESCAPED_UNICODE);
        $wlBadge = '<button type="button" class="card__wl" data-card-wl data-wl-slug="' . htmlspecialchars((string)$item['slug']) . '" data-wl-meta="' . htmlspecialchars($wlMeta) . '" title="Ajouter à la watchlist" aria-pressed="false">' . lc('plus', 14) . '</button>';
    }
    $soonBadge = $unavailable
        ? '<div class="card__badge--soon card__badge--unavailable">Non Disponible</div>'
        : ($noFile ? '<div class="card__badge--soon">Prochainement</div>' : '');
    $trendingBadge = ($item['is_trending'] ?? false)
        ? '<div class="card__trending"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"></path></svg>Tendance</div>'
        : '';

    // ── Lang badge ────────────────────────────────────────────────────────────
    $langHtml = '';
    $lang = $item['main_language'] ?? null;
    // if ($lang) {
    //     static $lmap = ['VOSTFR' => 'vostfr', 'VOSTA' => 'vostfr', 'VOSTEN' => 'vostfr', 'VF' => 'vf', 'MULTI' => 'multi', 'DUAL' => 'multi', 'VO' => 'vo'];
    //     $tags = array_unique(array_filter(array_map('trim', explode(' ', strtoupper($lang)))));
    //     $spans = implode('', array_map(function($t) use ($lmap) {
    //         $cls = $lmap[$t] ?? null;
    //         return '<span class="card__lang' . ($cls ? ' card__lang--' . $cls : '') . '">' . htmlspecialchars($t) . '</span>';
    //     }, $tags));
    //     $langHtml = $spans ? '<div class="card__langs">' . $spans . '</div>' : '';
    // }

    // ── Year ──────────────────────────────────────────────────────────────────
    $yearHtml = ($item['year'] ?? null)
        ? '<span class="card__meta-year">' . $item['year'] . '</span>'
        : '';

    // ── Score (« 2020 · ★ 7.2 ») ──────────────────────────────────────────────
    $scoreHtml = $score
        ? ($yearHtml ? '<span class="card__meta-sep">·</span>' : '')
            . '<span class="card__meta-score">' . lc('star', 10, 'card__meta-star') . $score . '</span>'
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

    $cardClass = 'card' . ($unavailable ? ' card--unavailable' : '');

    $keysAttr = $epKeys !== '' ? ' data-ep-keys="' . htmlspecialchars($epKeys) . '"' : '';

    return $seenScript . '<a href="' . htmlspecialchars($href) . '" class="' . $cardClass . '" data-type="' . htmlspecialchars($type) . '"' . $keysAttr . $dataAttrs . '>'
        . '<div class="card__media">'
            . $posterHtml
            . '<div class="card__overlay"></div>'
            . $trendingBadge
            . $wlBadge
            . $seenBadge
            . '<div class="card__play"><div class="card__play-btn">' . lc('play', 16) . '</div></div>'
            . '<div class="card__body">'
                . '<div class="card__title">' . htmlspecialchars($title) . '</div>'
                . '<div class="card__meta">'
                    . $yearHtml
                    . $scoreHtml
                . '</div>'
                . $matchHtml
                . ($langHtml || $soonBadge
                    ? '<div class="card__bottom-badges">' . $langHtml . $soonBadge . '</div>'
                    : '')
            . '</div>'
        . '</div>'
    . '</a>';
}
