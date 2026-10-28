<?php
/**
 * Dynamic PWA manifest: uses the uploaded logo when available.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();
$logo = app()->settings->logoUrl();

$icons = [];
if ($logo !== '') {
    $icons[] = [
        'src'     => $logo,
        'sizes'   => 'any',
        'type'    => 'image/png',
        'purpose' => 'any',
    ];
} else {
    $icons[] = [
        'src'     => url('assets/img/icon-192.png'),
        'sizes'   => '192x192',
        'type'    => 'image/png',
        'purpose' => 'any',
    ];
    $icons[] = [
        'src'     => url('assets/img/icon-512.png'),
        'sizes'   => '512x512',
        'type'    => 'image/png',
        'purpose' => 'any',
    ];
}
$icons[] = [
    'src'     => url('assets/img/icon-maskable-512.png'),
    'sizes'   => '512x512',
    'type'    => 'image/png',
    'purpose' => 'maskable',
];

$manifest = [
    'name'             => $settings['site_name'],
    'short_name'       => text_limit($settings['logo_text'] ?: $settings['site_name'], 12),
    'description'      => $settings['tagline'],
    'start_url'        => url(''),
    'scope'            => url(''),
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0b1120',
    'theme_color'      => $settings['theme_color'],
    'icons'            => $icons,
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
