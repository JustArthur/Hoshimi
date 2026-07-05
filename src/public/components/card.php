<?php

declare(strict_types=1);

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
        'film'  => '/film/?slug='  . urlencode($item['slug']),
        'serie' => '/anime/?slug=' . urlencode($item['slug']) . '&type=serie',
        default => '/anime/?slug=' . urlencode($item['slug']),
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
    $icon  = match ($type) { 'film' => '🎬',   'serie' => '📺',    default => '🎌' };

    // ── Poster ────────────────────────────────────────────────────────────────
    $coverUrl   = $item['cover_url'] ?? null;
    $posterHtml = $coverUrl
        ? '<img class="card__poster" src="' . htmlspecialchars($coverUrl) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" loading="lazy">'
        : '<div class="no-cover"><div class="no-cover__content"><span class="no-cover__icon">' . $icon . '</span></div></div>';

    // ── Badges (score / soon / trending) ─────────────────────────────────────
    $scoreBadge = $score
        ? '<div class="card__score"><span class="card__score-star">★</span>' . $score . '</div>'
        : '';
    $noFile    = (isset($item['has_file']) && !$item['has_file'])
                 || ($type !== 'film' && ($item['episodes_local'] ?? 1) === 0);
    $soonBadge = $noFile ? '<div class="card__badge--soon">Prochainement</div>' : '';
    $trendingBadge = ($item['is_trending'] ?? false)
        ? '<div class="card__trending"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4"></path></svg>Tendance</div>'
        : '';

    // ── Lang badge ────────────────────────────────────────────────────────────
    $langHtml = '';
    $lang = $item['main_language'] ?? null;
    if ($lang) {
        static $lmap = ['VOSTFR' => 'vostfr', 'VF' => 'vf', 'MULTI' => 'multi', 'VO' => 'vo'];
        $lkey = strtoupper(trim($lang));
        $lcls = $lmap[$lkey] ?? null;
        $langHtml = $lcls
            ? '<div class="card__langs"><span class="card__lang card__lang--' . $lcls . '">' . $lkey . '</span></div>'
            : '';
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

    // ── Fav icon ──────────────────────────────────────────────────────────────
    $favHtml = ($opts['show_fav'] ?? false)
        ? '<div class="card__fav" data-fav-icon="' . htmlspecialchars($item['slug']) . '">♡</div>'
        : '';

    // ── Extra data-* attributes ───────────────────────────────────────────────
    $dataAttrs = '';
    foreach ($opts['data_attrs'] ?? [] as $k => $v) {
        $dataAttrs .= ' data-' . htmlspecialchars($k) . '="' . htmlspecialchars((string) $v) . '"';
    }

    return '<a href="' . htmlspecialchars($href) . '" class="card"' . $dataAttrs . '>'
        . $favHtml
        . '<div class="card__media">'
            . $posterHtml
            . '<div class="card__overlay"></div>'
            . $trendingBadge
            . $soonBadge
            . $scoreBadge
            . '<div class="card__play"><div class="card__play-btn">▶</div></div>'
            . '<div class="card__body">'
                . '<div class="card__title">' . htmlspecialchars($title) . '</div>'
                . '<div class="card__meta">'
                    . '<span class="card__meta-badge">' . $badge . '</span>'
                    . $yearHtml
                . '</div>'
                . $matchHtml
                . $langHtml
            . '</div>'
        . '</div>'
    . '</a>';
}
