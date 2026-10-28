<?php
/**
 * Payment list: search, status/date filters, sortable columns, pagination.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();

$filters = [
    'q'      => query_str('q'),
    'status' => query_str('status'),
    'from'   => query_str('from'),
    'to'     => query_str('to'),
];

$sort = query_str('sort', 'created_at');
$direction = strtolower(query_str('order', 'desc')) === 'asc' ? 'asc' : 'desc';
$page = max(1, query_int('page', 1));
$perPage = query_int('per_page', 15);
$perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

$result = app()->payments->paginate($filters, $sort, $direction, $page, $perPage);
$totals = app()->payments->totals($filters);

render('admin/payments', [
    'pageTitle' => 'Payments',
    'activeNav' => 'payments.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'filters'   => $filters,
    'sort'      => in_array($sort, Payments::SORTABLE, true) ? $sort : 'created_at',
    'direction' => $direction,
    'result'    => $result,
    'totals'    => $totals,
]);
