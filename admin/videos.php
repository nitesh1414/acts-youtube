<?php
/**
 * Video library: list, publish/unpublish and delete videos.
 */
require_once __DIR__ . '/../config.php';

$admin = app()->auth->requireLogin();
$settings = app()->settings->all();
$catalog = app()->catalog;

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $id = (int) post_str('id', '0');

    $video = $id > 0 ? $catalog->video($id) : null;
    if ($video === null) {
        flash('danger', 'That video no longer exists.');
        redirect('admin/videos.php');
    }

    if ($action === 'toggle') {
        $catalog->updateVideo($id, [
            'title'       => $video['title'],
            'description' => (string) $video['description'],
            'youtube_url' => (string) $video['youtube_url'],
            'price'       => $video['price'],
            'position'    => $video['position'],
            'is_active'   => !$video['is_active'],
        ]);
        app_log('info', 'Video visibility changed', ['video_id' => $id, 'active' => !$video['is_active']]);
        flash('success', $video['is_active'] ? 'The video is now hidden from the shop.' : 'The video is now on sale.');
    } elseif ($action === 'delete') {
        $result = $catalog->deleteVideo($id);
        app_log('warning', 'Video removed', ['video_id' => $id, 'mode' => $result['mode']]);
        flash('success', $result['mode'] === 'hidden'
            ? 'This video has been purchased, so it was hidden instead of deleted (buyers keep their access).'
            : 'The video was deleted.');
    } else {
        flash('danger', 'Unknown action.');
    }

    redirect('admin/videos.php');
}

$videos = $catalog->videos(false);
$rows = [];
foreach ($videos as $video) {
    $video['sales'] = $catalog->videoSales((int) $video['id']);
    $video['bundles'] = $catalog->videoBundleCount((int) $video['id']);
    $rows[] = $video;
}

render('admin/videos', [
    'pageTitle' => 'Videos',
    'activeNav' => 'videos.php',
    'admin'     => $admin,
    'settings'  => $settings,
    'videos'    => $rows,
]);
