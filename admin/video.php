<?php
/**
 * Add or edit a single video.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();
$catalog = app()->catalog;

$id = query_int('id', 0);
$video = $id > 0 ? $catalog->video($id) : null;

if ($id > 0 && $video === null) {
    abort(404, 'Video not found.');
}

$form = $video !== null ? $video : [
    'title'       => '',
    'description' => '',
    'youtube_url' => '',
    'price'       => (string) $settings['amount'],
    'position'    => count($catalog->videos(false)) + 1,
    'is_active'   => true,
];
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'delete' && $video !== null) {
        $result = $catalog->deleteVideo((int) $video['id']);
        app_log('warning', 'Video removed', ['video_id' => (int) $video['id'], 'admin_id' => $admin['id'], 'mode' => $result['mode']]);
        flash('success', $result['mode'] === 'hidden'
            ? 'This video has been purchased, so it was hidden instead of deleted.'
            : 'The video was deleted.');
        redirect('admin/videos.php');
    }

    if ($action === 'save') {
        $form = [
            'title'       => post_str('title'),
            'description' => post_str('description'),
            'youtube_url' => post_str('youtube_url'),
            'price'       => post_str('price'),
            'position'    => post_str('position', '0'),
            'is_active'   => !empty($_POST['is_active']),
        ];

        if (text_length($form['title']) < 2 || text_length($form['title']) > 160) {
            $errors['title'] = 'Give the video a title (2–160 characters).';
        }
        if (youtube_id($form['youtube_url']) === null) {
            $errors['youtube_url'] = 'Paste a YouTube link (youtube.com/watch?v=…, youtu.be/… or the 11-character id).';
        }
        if (!is_numeric($form['price']) || (float) $form['price'] < 1 || (float) $form['price'] > 500000) {
            $errors['price'] = 'Enter a price between 1 and 5,00,000.';
        }
        if (!preg_match('/^\d{1,4}$/', (string) $form['position'])) {
            $errors['position'] = 'Order must be a whole number (0–9999).';
        }

        if (empty($errors)) {
            $data = [
                'title'       => $form['title'],
                'description' => $form['description'],
                'youtube_url' => $form['youtube_url'],
                'price'       => (float) $form['price'],
                'position'    => (int) $form['position'],
                'is_active'   => $form['is_active'],
            ];

            try {
                if ($video === null) {
                    $newId = $catalog->createVideo($data);
                    app_log('info', 'Video created', ['video_id' => $newId, 'admin_id' => $admin['id']]);
                    flash('success', 'Video added to the library.');
                } else {
                    $catalog->updateVideo((int) $video['id'], $data);
                    app_log('info', 'Video updated', ['video_id' => (int) $video['id'], 'admin_id' => $admin['id']]);
                    flash('success', 'Video saved.');
                }
                clear_old_input();
                redirect('admin/videos.php');
            } catch (Throwable $e) {
                app_log('error', 'Could not save video: ' . $e->getMessage());
                $errors['general'] = 'The video could not be saved. Please try again.';
            }
        }
    }
}

render('admin/video', [
    'pageTitle' => $video === null ? 'Add video' : 'Edit video',
    'activeNav' => 'videos.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'video'     => $video,
    'form'      => $form,
    'errors'    => $errors,
]);
