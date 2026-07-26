<?php

declare(strict_types=1);

/**
 * Render a casting grid.
 *
 * @param array $castData   Array of cast members [{name, character, profile_url}, …]
 * @param string $emptyMsg  Message shown when cast is empty (null = render nothing)
 */
function render_character_grid(array $castData, ?string $emptyMsg = null, ?string $flatLabel = null): string
{
    if (empty($castData)) {
        if ($emptyMsg === null) return '';
        return '<div style="padding:40px 0;color:var(--color-text-muted);font-size:.9rem;">'
            . htmlspecialchars($emptyMsg) . '</div>';
    }

    if ($flatLabel !== null) {
        $groups = [$flatLabel => $castData];
        $isMainMap = [$flatLabel => true];
        $countSuffix = fn(int $n) => $n . ' acteur' . ($n > 1 ? 's' : '');
    } else {
        $groups = ['MAIN' => [], 'SUPPORTING' => [], 'BACKGROUND' => []];
        foreach ($castData as $m) {
            $r = strtoupper($m['role'] ?? 'SUPPORTING');
            if (!isset($groups[$r])) $r = 'BACKGROUND';
            $groups[$r][] = $m;
        }
        $labels    = ['MAIN' => 'Principaux', 'SUPPORTING' => 'Secondaires', 'BACKGROUND' => 'Autres'];
        $groups    = array_combine(array_map(fn($k) => $labels[$k], array_keys($groups)), $groups);
        $isMainMap = ['Principaux' => true, 'Secondaires' => false, 'Autres' => false];
        $countSuffix = fn(int $n) => $n . ' perso' . ($n > 1 ? 's' : '');
    }

    $html = '<div class="char-grid-wrap">';

    foreach ($groups as $label => $members) {
        if (empty($members)) continue;
        $isMain = $isMainMap[$label] ?? false;
        $html .= '<div class="char-section">'
            . '<div class="char-section__header">'
            . '<span class="char-section__label">' . htmlspecialchars($label) . '</span>'
            . '<span class="char-section__line"></span>'
            . '<span class="char-section__count">' . $countSuffix(count($members)) . '</span>'
            . '</div>'
            . '<div class="char-grid' . ($isMain ? ' char-grid--main' : '') . '">';

        foreach ($members as $m) {
            $charName = htmlspecialchars($m['character'] ?? $m['name'] ?? '');
            $vaName   = ($m['character'] ?? null) ? htmlspecialchars($m['name'] ?? '') : '';
            $img      = $m['profile_url'] ?? null;

            $html .= '<div class="char-card">';
            if ($img) {
                $html .= '<img class="char-card__img" src="' . htmlspecialchars($img) . '" alt="' . $charName . '" loading="lazy">';
            } else {
                $html .= '<div class="char-card__img char-card__img--empty">'
                    . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    . '<circle cx="12" cy="8" r="4"/>'
                    . '<path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>'
                    . '</svg>'
                    . '</div>';
            }
            $html .= '<div class="char-card__overlay">'
                . '<span class="char-card__name">' . $charName . '</span>';
            if ($vaName) {
                $html .= '<span class="char-card__va">' . $vaName . '</span>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div></div>';
    }

    return $html . '</div>';
}
