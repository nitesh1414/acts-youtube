<?php
/**
 * CSV export of the (filtered) payment list. Opens in Excel / Google Sheets.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();

$filters = [
    'q'      => query_str('q'),
    'status' => query_str('status'),
    'from'   => query_str('from'),
    'to'     => query_str('to'),
];

$rows = app()->payments->exportRows($filters);

app_log('info', 'Payments exported', ['admin_id' => $admin['id'], 'rows' => count($rows)]);

$filename = 'payments-' . date('Ymd-His') . '.csv';
if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens ₹ correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Payment #',
    'Name',
    'Email',
    'Mobile',
    'Amount',
    'Currency',
    'Status',
    'Order ID',
    'Payment ID',
    'Method',
    'Created at',
    'Paid at',
    'Access expires',
    'IP',
], ',', '"', '\\');

// Guard against CSV formula injection in spreadsheet apps.
$safe = function ($value) {
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
};

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $safe($row['user_name']),
        $safe($row['user_email']),
        $safe(display_mobile($row['user_mobile'])),
        number_format((float) $row['amount'], 2, '.', ''),
        $row['currency'],
        $row['status'],
        $safe($row['order_id']),
        $safe($row['payment_id']),
        $safe($row['method']),
        $row['created_at'],
        $row['paid_at'],
        $row['access_expires_at'],
        $safe($row['ip_address']),
    ], ',', '"', '\\');
}

fclose($out);
exit;
