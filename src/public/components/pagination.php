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

    $h  = '<div class="pagination">';

    $h .= $page > 1
        ? '<a href="' . htmlspecialchars($urlFn($page - 1)) . '" class="pagination__btn">← Précédent</a>'
        : '<span class="pagination__btn pagination__btn--disabled">← Précédent</span>';

    $h .= '<div class="pagination__pages">';

    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);

    if ($start > 1) $h .= '<a href="' . htmlspecialchars($urlFn(1)) . '" class="pagination__page">1</a>';
    if ($start > 2) $h .= '<span class="pagination__ellipsis">…</span>';

    for ($i = $start; $i <= $end; $i++) {
        $cls = 'pagination__page' . ($i === $page ? ' is-active' : '');
        $h  .= '<a href="' . htmlspecialchars($urlFn($i)) . '" class="' . $cls . '">' . $i . '</a>';
    }

    if ($end < $totalPages - 1) $h .= '<span class="pagination__ellipsis">…</span>';
    if ($end < $totalPages)     $h .= '<a href="' . htmlspecialchars($urlFn($totalPages)) . '" class="pagination__page">' . $totalPages . '</a>';

    $h .= '</div>';

    $h .= $page < $totalPages
        ? '<a href="' . htmlspecialchars($urlFn($page + 1)) . '" class="pagination__btn">Suivant →</a>'
        : '<span class="pagination__btn pagination__btn--disabled">Suivant →</span>';

    $h .= '</div>';
    return $h;
}
