<?php

declare(strict_types=1);

// ── Named SVG icons ───────────────────────────────────────────────────────────

function tab_icon(string $name): string
{
    return match ($name) {
        'play' =>
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/>'
            . '</svg>',

        'book' =>
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
            . '<polyline points="14 2 14 8 20 8"/>'
            . '<line x1="16" y1="13" x2="8" y2="13"/>'
            . '<line x1="16" y1="17" x2="8" y2="17"/>'
            . '</svg>',

        'cast' =>
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>'
            . '<circle cx="9" cy="7" r="4"/>'
            . '<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>'
            . '<path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
            . '</svg>',

        'sparkles' =>
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/>'
            . '<path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/>'
            . '</svg>',

        default => '',
    };
}

/**
 * Render the tabs bar.
 *
 * Each tab is an array:
 *   id:      string  — matches panel id="tab-{id}"
 *   label:   string
 *   icon:    string  — key passed to tab_icon(), or raw SVG string
 *   count?:  int     — optional count badge
 *   active?: bool    — is this tab initially active?
 *   hidden?: bool    — skip rendering this button (panel still exists)
 */
function render_tabs_bar(array $tabs): string
{
    $html = '<div class="detail-tabs-bar"><div class="detail-tabs-bar__inner">';

    foreach ($tabs as $tab) {
        if ($tab['hidden'] ?? false) continue;

        $active = ($tab['active'] ?? false) ? ' is-active' : '';
        $icon   = strlen($tab['icon'] ?? '') < 20
            ? tab_icon($tab['icon'] ?? '')   // named shorthand
            : ($tab['icon'] ?? '');           // raw SVG passed directly

        $count = isset($tab['count']) && $tab['count'] > 0
            ? '<span class="detail-tab-btn__count">' . (int) $tab['count'] . '</span>'
            : '';

        $html .= '<button class="detail-tab-btn' . $active . '" data-tab="' . htmlspecialchars($tab['id']) . '">'
            . $icon
            . htmlspecialchars($tab['label'])
            . $count
            . '</button>';
    }

    return $html . '</div></div>';
}
