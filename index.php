<?php
/**
 * Landing page: the video library, the bundles on offer and the checkout form
 * for whichever item the visitor selected.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();
$storefront = catalog_storefront();
$buyQuery = trim((string) query_str('buy'));
// Only an explicit ?buy= link counts as a choice. A plain visit keeps the phone
// checkout (and the "You are buying" bar) hidden until something is picked.
$selected = catalog_selected_product($storefront, $buyQuery);

render('site/home', [
    'pageTitle'       => $settings['site_name'] . ' — ' . $settings['tagline'],
    'metaDescription' => $settings['tagline'],
    'bodyClass'       => 'site-home' . ($buyQuery === '' ? ' no-pick' : ''),
    'explicitPick'    => $buyQuery !== '',
    'storefront'      => $storefront,
    'selected'        => $selected,
    'csrf'            => csrf_token(),
]);
