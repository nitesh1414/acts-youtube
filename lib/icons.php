<?php
/**
 * Inline SVG icon set (no external icon font / CDN required).
 */

/**
 * @param  string $name
 * @param  int    $size
 * @param  string $class
 * @return string
 */
function icon($name, $size = 18, $class = '')
{
    static $paths = [
        'grid'          => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'credit-card'   => '<rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        'sliders'       => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h13M21 18h-1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="19" cy="18" r="2"/>',
        'users'         => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7" r="3.5"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16 3.6a3.5 3.5 0 0 1 0 6.8"/>',
        'user'          => '<path d="M19 20v-1.5a5 5 0 0 0-5-5h-4a5 5 0 0 0-5 5V20"/><circle cx="12" cy="7" r="3.5"/>',
        'external-link' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v4.5A2.5 2.5 0 0 1 15.5 21h-9A2.5 2.5 0 0 1 4 18.5v-9A2.5 2.5 0 0 1 6.5 7H11"/>',
        'logout'        => '<path d="M9 21H6a2.5 2.5 0 0 1-2.5-2.5v-13A2.5 2.5 0 0 1 6 3h3"/><path d="M16 16l4-4-4-4"/><path d="M20 12H9"/>',
        'menu'          => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
        'upload'        => '<path d="M21 15v3.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5V15"/><path d="M7 9l5-5 5 5"/><path d="M12 4v12"/>',
        'trash'         => '<path d="M3 6h18"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="M19 6l-1 13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'check'         => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle'  => '<path d="M21 11.1V12a9 9 0 1 1-5.3-8.2"/><path d="M22 4 12 14l-3-3"/>',
        'alert'         => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'alert-octagon' => '<path d="M7.9 2h8.2L22 7.9v8.2L16.1 22H7.9L2 16.1V7.9L7.9 2Z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/>',
        'search'        => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'download'      => '<path d="M21 15v3.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5V15"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'copy'          => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
        'eye'           => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'arrow-left'    => '<path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/>',
        'arrow-right'   => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'youtube'       => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9.5l5 2.5-5 2.5v-5Z" fill="currentColor" stroke="none"/>',
        'play'          => '<path d="M6 4.5v15l13-7.5-13-7.5Z"/>',
        'lock'          => '<rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'shield'        => '<path d="M12 22s8-3.6 8-10V5.5L12 2 4 5.5V12c0 6.4 8 10 8 10Z"/><path d="M9 12l2 2 4-4"/>',
        'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'mail'          => '<rect x="2" y="4" width="20" height="16" rx="2.5"/><path d="M3 6.5 12 13l9-6.5"/>',
        'phone'         => '<path d="M21 16.9v2a2 2 0 0 1-2.2 2 19.5 19.5 0 0 1-8.5-3 19 19 0 0 1-5.9-5.9A19.5 19.5 0 0 1 1.4 3.5 2 2 0 0 1 3.4 1.3h2a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L6.6 9a15 15 0 0 0 5.7 5.7l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'chevron-down'  => '<path d="M6 9l6 6 6-6"/>',
        'image'         => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'refresh'       => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M3 21v-5h5"/><path d="M21 3v5h-5"/>',
        'key'           => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3 21 2"/><path d="M17 6l3 3"/><path d="M14 9l3 3"/>',
        'sparkles'      => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>',
        'calendar'      => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18"/><path d="M8 3v4M16 3v4"/>',
        'filter'        => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>',
        'link'          => '<path d="M10 13a5 5 0 0 0 7.1 0l2.4-2.4a5 5 0 0 0-7.1-7.1L11 4.9"/><path d="M14 11a5 5 0 0 0-7.1 0L4.5 13.4a5 5 0 0 0 7.1 7.1l1.4-1.4"/>',
        'trending-up'   => '<path d="M22 7l-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'zap'           => '<path d="M13 2 4 14h8l-1 8 9-12h-8l1-8Z"/>',
        'star'          => '<path d="M12 3l2.9 6 6.1.9-4.4 4.3 1 6.1-5.6-3-5.6 3 1-6.1L3 9.9 9.1 9 12 3Z"/>',
        'wallet'        => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a3 3 0 0 1 3 3v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17V7.5Z"/><path d="M3 8h13"/><circle cx="17" cy="13.5" r="1.2"/>',
        'tag'           => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2.6 12.6a2 2 0 0 1-.6-1.4V4.5A2.5 2.5 0 0 1 4.5 2h6.7a2 2 0 0 1 1.4.6l8 8a2 2 0 0 1 0 2.8Z"/><circle cx="7" cy="7" r="1.3"/>',
        'help'          => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.9 2.1c-.9.6-1.4 1.1-1.4 2.4"/><path d="M12 17h.01"/>',
    ];

    $inner = isset($paths[$name]) ? $paths[$name] : $paths['info'];
    $size = (int) $size;
    $class = trim('icon ' . $class);

    return '<svg class="' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $inner . '</svg>';
}

/**
 * The PayAccess brand mark (used when no logo has been uploaded).
 */
function brand_mark($size = 44, $class = '')
{
    static $counter = 0;
    $counter++;
    $id = 'pa-mark-' . $counter;
    $size = (int) $size;

    return '<svg class="' . e(trim('brand-mark ' . $class)) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" role="img" aria-label="PayAccess">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="#6366f1"/><stop offset="100%" stop-color="#8b5cf6"/>'
        . '</linearGradient></defs>'
        . '<rect width="48" height="48" rx="14" fill="url(#' . $id . ')"/>'
        . '<path d="M19 15.5v17l14-8.5-14-8.5Z" fill="#fff" opacity="0.95"/>'
        . '<circle cx="35" cy="13" r="3.2" fill="#fff" opacity="0.35"/>'
        . '</svg>';
}
