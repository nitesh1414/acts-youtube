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
$form['allow_single_purchase'] = [];
foreach ($allVideos as $video) {
    $form['allow_single_purchase'][(int) $video['id']] = !empty($video['allow_single_purchase']);
}
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

        $known = [];
        foreach ($allVideos as $video) {
            $known[(int) $video['id']] = $video;
        }

        // The individual-sale choice belongs to the video itself (not this
        // particular bundle), so it follows that video across all bundles.
        $postedAvailability = isset($_POST['allow_single_purchase']) && is_array($_POST['allow_single_purchase'])
            ? $_POST['allow_single_purchase']
            : [];
        $availability = [];
        foreach ($known as $videoId => $video) {
            $existing = !empty($video['allow_single_purchase']);
            $raw = isset($postedAvailability[$videoId]) && is_scalar($postedAvailability[$videoId])
                ? (string) $postedAvailability[$videoId]
                : '';
            $availability[$videoId] = $raw === '1' ? true : ($raw === '0' ? false : $existing);
        }

        $form = [
            'name'                   => post_str('name'),
            'description'            => post_str('description'),
            'price'                  => post_str('price'),
            'position'               => post_str('position', '0'),
            'is_active'              => !empty($_POST['is_active']),
            'video_ids'              => $selected,
            'allow_single_purchase'  => $availability,
        ];

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

        // Compare the bundle price only when every video is currently offered
        // as a single purchase. Bundle-only/hidden videos cannot be counted as
        // a separately purchasable price.
        $singleTotal = 0.0;
        $singleCount = 0;
        foreach ($selected as $videoId) {
            if (isset($known[$videoId]) && !empty($form['allow_single_purchase'][$videoId])
                && !empty($known[$videoId]['is_active'])) {
                $singleTotal += (float) $known[$videoId]['price'];
                $singleCount++;
            }
        }
        if (empty($errors['price']) && count($selected) > 0 && $singleCount === count($selected)
            && $singleTotal > 0 && (float) $form['price'] > $singleTotal) {
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
                // Save the bundle membership and every video-level single-sale
                // choice together so a failed write cannot leave a half-applied
                // configuration.
                $saved = app()->db->transaction(function () use ($catalog, $bundle, $data, $selected, $availability) {
                    foreach ($availability as $videoId => $available) {
                        $catalog->setSinglePurchaseAvailability((int) $videoId, $available);
                    }
                    if ($bundle === null) {
                        return ['id' => $catalog->createBundle($data, $selected), 'sync' => null];
                    }
                    return [
                        'id' => (int) $bundle['id'],
                        'sync' => $catalog->updateBundle((int) $bundle['id'], $data, $selected),
                    ];
                });

                if ($bundle === null) {
                    app_log('info', 'Bundle created', ['bundle_id' => (int) $saved['id'], 'videos' => $selected, 'admin_id' => $admin['id']]);
                    flash('success', 'Bundle created — it is now visible on the home page.');
                } else {
                    $sync = $saved['sync'];
                    app_log('info', 'Bundle updated', [
                        'bundle_id' => (int) $bundle['id'],
                        'videos'    => $selected,
                        'buyers'    => (int) $sync['payments'],
                        'grants'    => (int) $sync['grants'],
                        'admin_id'  => (int) $admin['id'],
                    ]);

                    // Say it out loud: a bundle edit is a live change for its buyers.
                    $message = 'Bundle saved.';
                    if ($sync['payments'] > 0) {
                        $message .= ' The ' . (int) $sync['payments'] . ' customer(s) who already bought it see the new video list on their next visit.';
                        if ($sync['grants'] > 0) {
                            $message .= ' ' . (int) $sync['grants'] . ' new video unlock(s) were added to their library.';
                        }
                    }
                    flash('success', $message);
                }
                redirect('admin/bundles.php');
            } catch (Throwable $e) {
                if ($e instanceof DatabaseException) {
                    $errors['general'] = database_error_hint($e);
                } else {
                    app_log('error', 'Could not save bundle: ' . $e->getMessage());
                    $errors['general'] = 'The bundle could not be saved because of an unexpected application error. Please try again.';
                }
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
