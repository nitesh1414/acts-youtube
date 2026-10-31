<?php
/**
 * Landing page: the video library, the bundles on offer and the checkout form
 * for whichever item the visitor selected.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();
$storefront = catalog_storefront();
$selected = catalog_selected_product($storefront, query_str('buy'));

render('site/home', [
    'pageTitle'       => $settings['site_name'] . ' — ' . $settings['tagline'],
    'metaDescription' => $settings['tagline'],
    'bodyClass'       => 'site-home',
    'storefront'      => $storefront,
    'selected'        => $selected,
    'csrf'            => csrf_token(),
]);
