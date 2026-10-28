<?php
/**
 * Admin dashboard: KPIs, revenue chart and recent payments.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();

render('admin/dashboard', [
    'pageTitle' => 'Dashboard',
    'activeNav' => 'index.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'stats'     => app()->payments->stats(),
    'chart'     => app()->payments->dailyRevenue(14),
    'recent'    => app()->payments->recent(8),
]);
