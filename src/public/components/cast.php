<?php

declare(strict_types=1);

/**
 * Render a casting grid.
 *
 * @param array $castData   Array of cast members [{name, character, profile_url}, …]
 * @param string $emptyMsg  Message shown when cast is empty (null = render nothing)
 */
function render_cast_grid(array $castData, ?string $emptyMsg = null): string
{
    if (empty($castData)) {
        if ($emptyMsg === null) return '';
        return '<div style="padding:40px 0;color:var(--color-text-muted);font-size:.9rem;">'
            . htmlspecialchars($emptyMsg)
            . '</div>';
    }

    static $noPhotoSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
        . '<circle cx="12" cy="8" r="4"/>'
        . '<path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>'
        . '</svg>';

    $html = '<div class="cast-grid">';
    foreach ($castData as $member) {
        $name = htmlspecialchars($member['name'] ?? '');
        $photo = ($member['profile_url'] ?? null)
            ? '<img src="' . htmlspecialchars($member['profile_url']) . '" alt="' . $name . '" loading="lazy">'
            : '<div class="cast-card__no-photo">' . $noPhotoSvg . '</div>';

        $role = ($member['character'] ?? null)
            ? '<span class="cast-card__role">' . htmlspecialchars($member['character']) . '</span>'
            : '';

        $html .= '<div class="cast-card">'
            . '<div class="cast-card__photo">' . $photo . '</div>'
            . '<div class="cast-card__body"><span class="cast-card__name">' . $name . '</span>' . $role . '</div>'
            . '</div>';
    }
    return $html . '</div>';
}
