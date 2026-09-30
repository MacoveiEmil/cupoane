<?php
/**
 * Iconițe SVG: un singur sprite inline (după <body>), apoi <use> în pagină.
 * Fără icon fonts, fără requesturi, fără a repeta path-urile în fiecare card.
 */

declare(strict_types=1);

function ct_icon_paths(): array
{
    return [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h8"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'check'    => '<path d="m5 12 5 5 9-10"/>',
        'clock'    => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'shield'   => '<path d="M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'chevron'  => '<path d="m9 6 6 6-6 6"/>',
        'up'       => '<path d="M7 11v9H4v-9zM7 11l4-8a2 2 0 0 1 2 2v4h5a2 2 0 0 1 2 2l-1.5 7a2 2 0 0 1-2 1.7H7"/>',
        'down'     => '<path d="M17 13V4h3v9zM17 13l-4 8a2 2 0 0 1-2-2v-4H6a2 2 0 0 1-2-2l1.5-7A2 2 0 0 1 7.5 4H17"/>',
        'star'     => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'store'    => '<path d="M4 9h16l-1-5H5zM5 9v11h14V9M9 20v-6h6v6"/>',
        'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'fire'     => '<path d="M12 21c4 0 7-2.7 7-6.5 0-3.5-2.5-5.5-4-8-1 2-2 3-3.5 3.5C11 7 10.5 5 11 3 7.5 5 5 8.7 5 14.5 5 18.3 8 21 12 21z"/>',
        'percent'  => '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
        'bolt'     => '<path d="M13 3 4 14h7l-1 7 9-11h-7z"/>',
    ];
}

/** Sprite-ul, tipărit o singură dată imediat după <body>. */
function ct_icon_sprite(): void
{
    echo '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">';
    foreach (ct_icon_paths() as $name => $paths) {
        printf('<symbol id="i-%s" viewBox="0 0 24 24">%s</symbol>', esc_attr($name), $paths); // phpcs:ignore -- markup static
    }
    echo '</svg>';
}

function ct_icon(string $name, int $size = 18): string
{
    return sprintf(
        '<svg class="i i-%2$s" width="%1$d" height="%1$d" aria-hidden="true" focusable="false"><use href="#i-%2$s"/></svg>',
        $size, esc_attr($name)
    );
}
