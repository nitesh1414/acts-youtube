<?php
/**
 * Add or edit a bundle (name + price + the videos it contains).
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();
$catalog = app()->catalog;

$id = query_int('id', 0);
$bundle = $id > 0 ? $catalog->bundle($id) : null;

if ($id > 0 && $bundle === null) {
    abort(404, 'Bundle not found.');
}

$allVideos = $catalog->videos(false);
$form = $bundle !== null ? $bundle : [
    'name'        => '',
    'description' => '',
    'price'       => '',
    'position'    => count($catalog->bundles(false)) + 1,
    'is_active'   => true,
    'video_ids'   => [],
];
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'delete' && $bundle !== null) {
        $catalog->deleteBundle((int) $bundle['id']);
        app_log('warning', 'Bundle deleted', ['bundle_id' => (int) $bundle['id'], 'admin_id' => $admin['id']]);
        flash('success', 'The bundle was deleted.');
        redirect('admin/bundles.php');
    }

    if ($action === 'save') {
        $selected = isset($_POST['video_ids']) && is_array($_POST['video_ids']) ? $_POST['video_ids'] : [];
        $selected = array_values(array_unique(array_filter(array_map('intval', $selected), function ($v) {
            return $v > 0;
        })));

        $form = [
            'name'        => post_str('name'),
            'description' => post_str('description'),
            'price'       => post_str('price'),
            'position'    => post_str('position', '0'),
            'is_active'   => !empty($_POST['is_active']),
            'video_ids'   => $selected,
        ];

        $known = [];
        foreach ($allVideos as $video) {
            $known[(int) $video['id']] = $video;
        }

        if (text_length($form['name']) < 2 || text_length($form['name']) > 160) {
            $errors['name'] = 'Give the bundle a name your customers will understand (2–160 characters).';
        }
        if (count($selected) < 1) {
            $errors['video_ids'] = 'Select at least one video.';
        }
        foreach ($selected as $videoId) {
            if (!isset($known[$videoId])) {
                $errors['video_ids'] = 'One of the selected videos no longer exists. Please try again.';
                break;
            }
        }
        if (!is_numeric($form['price']) || (float) $form['price'] < 1 || (float) $form['price'] > 500000) {
            $errors['price'] = 'Enter a price between 1 and 5,00,000.';
        }
        if (!preg_match('/^\d{1,4}$/', (string) $form['position'])) {
            $errors['position'] = 'Order must be a whole number (0–9999).';
        }

        // Warn (but do not block) when the bundle costs more than buying separately.
        $singleTotal = 0.0;
        foreach ($selected as $videoId) {
            if (isset($known[$videoId])) {
                $singleTotal += (float) $known[$videoId]['price'];
            }
        }
        if (empty($errors['price']) && $singleTotal > 0 && (float) $form['price'] > $singleTotal) {
            $errors['price'] = 'This bundle costs more than the ' . count($selected) . ' videos bought separately ('
                . money($singleTotal, (string) $settings['currency']) . '). Lower the bundle price.';
        }

        if (empty($errors)) {
            $data = [
                'name'        => $form['name'],
                'description' => $form['description'],
                'price'       => (float) $form['price'],
                'position'    => (int) $form['position'],
                'is_active'   => $form['is_active'],
            ];

            try {
                if ($bundle === null) {
                    $newId = $catalog->createBundle($data, $selected);
                    app_log('info', 'Bundle created', ['bundle_id' => $newId, 'videos' => $selected, 'admin_id' => $admin['id']]);
                    flash('success', 'Bundle created — it is now visible on the home page.');
                } else {
                    $catalog->updateBundle((int) $bundle['id'], $data, $selected);
                    app_log('info', 'Bundle updated', ['bundle_id' => (int) $bundle['id'], 'videos' => $selected, 'admin_id' => $admin['id']]);
                    flash('success', 'Bundle saved.');
                }
                redirect('admin/bundles.php');
            } catch (Throwable $e) {
                app_log('error', 'Could not save bundle: ' . $e->getMessage());
                $errors['general'] = 'The bundle could not be saved. Please try again.';
            }
        }
    }
}

$chosen = [];
foreach ($form['video_ids'] as $videoId) {
    $chosen[(int) $videoId] = true;
}

render('admin/bundle', [
    'pageTitle' => $bundle === null ? 'New bundle' : 'Edit bundle',
    'activeNav' => 'bundles.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'bundle'    => $bundle,
    'form'      => $form,
    'errors'    => $errors,
    'videos'    => $allVideos,
    'chosen'    => $chosen,
]);
