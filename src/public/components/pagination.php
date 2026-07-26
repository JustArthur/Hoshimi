<?php

declare(strict_types=1);

/**
 * Render a pagination bar.
 *
 * @param int      $page       Current page (1-based)
 * @param int      $totalPages Total number of pages
 * @param callable $urlFn      fn(int $page): string — builds a URL for the given page
 */
function render_pagination(int $page, int $totalPages, callable $urlFn): string
{
    if ($totalPages <= 1) return '';

    $firstBtn = $page > 1
        ? '<a href="' . htmlspecialchars($urlFn(1)) . '" class="pagination__btn pagination__btn--icon" title="Première page">«</a>'
        : '<span class="pagination__btn pagination__btn--icon pagination__btn--disabled">«</span>';

    $prev = $page > 1
        ? '<a href="' . htmlspecialchars($urlFn($page - 1)) . '" class="pagination__btn"><span class="pagination__btn-icon">‹</span> Précédente</a>'
        : '<span class="pagination__btn pagination__btn--disabled"><span class="pagination__btn-icon">‹</span> Précédente</span>';

    $next = $page < $totalPages
        ? '<a href="' . htmlspecialchars($urlFn($page + 1)) . '" class="pagination__btn">Suivante <span class="pagination__btn-icon">›</span></a>'
        : '<span class="pagination__btn pagination__btn--disabled">Suivante <span class="pagination__btn-icon">›</span></span>';

    $lastBtn = $page < $totalPages
        ? '<a href="' . htmlspecialchars($urlFn($totalPages)) . '" class="pagination__btn pagination__btn--icon" title="Dernière page">»</a>'
        : '<span class="pagination__btn pagination__btn--icon pagination__btn--disabled">»</span>';

    $pages = '<div class="pagination__pages">';

    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);

    if ($start > 1) $pages .= '<a href="' . htmlspecialchars($urlFn(1)) . '" class="pagination__page">1</a>';
    if ($start > 2) $pages .= '<span class="pagination__ellipsis">…</span>';

    for ($i = $start; $i <= $end; $i++) {
        $cls    = 'pagination__page' . ($i === $page ? ' is-active' : '');
        $pages .= '<a href="' . htmlspecialchars($urlFn($i)) . '" class="' . $cls . '">' . $i . '</a>';
    }

    if ($end < $totalPages - 1) $pages .= '<span class="pagination__ellipsis">…</span>';
    if ($end < $totalPages)     $pages .= '<a href="' . htmlspecialchars($urlFn($totalPages)) . '" class="pagination__page">' . $totalPages . '</a>';

    $pages .= '</div>';

    return '<div class="pagination">'
        . '<div class="pagination__nav pagination__nav--left">' . $firstBtn . $prev . '</div>'
        . $pages
        . '<div class="pagination__nav pagination__nav--right">' . $next . $lastBtn . '</div>'
        . '</div>';
}
