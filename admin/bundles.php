<?php
/**
 * Bundles: named groups of videos sold for a single payment.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();
$catalog = app()->catalog;

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $id = (int) post_str('id', '0');

    if ($id <= 0 || $catalog->bundle($id) === null) {
        flash('danger', 'That bundle no longer exists.');
        redirect('admin/bundles.php');
    }

    $bundle = $catalog->bundle($id);

    if ($action === 'toggle') {
        $catalog->updateBundle($id, [
            'name'        => $bundle['name'],
            'description' => (string) $bundle['description'],
            'price'       => $bundle['price'],
            'position'    => $bundle['position'],
            'is_active'   => !$bundle['is_active'],
        ], $bundle['video_ids']);
        app_log('info', 'Bundle visibility changed', ['bundle_id' => $id, 'active' => !$bundle['is_active']]);
        flash('success', $bundle['is_active'] ? 'The bundle is now hidden from the shop.' : 'The bundle is now on sale.');
    } elseif ($action === 'delete') {
        $catalog->deleteBundle($id);
        app_log('warning', 'Bundle deleted', ['bundle_id' => $id, 'admin_id' => $admin['id']]);
        flash('success', 'The bundle was deleted. Buyers keep the videos they already unlocked.');
    } else {
        flash('danger', 'Unknown action.');
    }

    redirect('admin/bundles.php');
}

$bundles = $catalog->bundles(false);
$rows = [];
foreach ($bundles as $bundle) {
    $bundle['sales'] = $catalog->bundleSales((int) $bundle['id']);
    $rows[] = $bundle;
}

render('admin/bundles', [
    'pageTitle' => 'Bundles',
    'activeNav' => 'bundles.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'bundles'   => $rows,
    'videos'    => $catalog->videos(false),
]);
