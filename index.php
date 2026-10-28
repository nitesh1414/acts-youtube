<?php
/**
 * Landing page: price, details form and Razorpay checkout.
 */
require_once __DIR__ . '/config.php';

$settings = app()->settings->all();

render('site/home', [
    'pageTitle'       => $settings['site_name'] . ' — ' . $settings['tagline'],
    'metaDescription' => $settings['tagline'],
    'bodyClass'       => 'site-home',
]);
