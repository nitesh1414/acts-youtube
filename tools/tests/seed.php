<?php
/**
 * Fixtures for the harness scenarios. Plain functions so any scenario can call
 * them after the fake database exists.
 */

/** Three priced videos: two on sale, one hidden. */
function seed_videos(FakeDatabase $db)
{
    $db->add('videos', [
        'title' => 'Episode One — The Beginning', 'description' => 'How it all started.',
        'youtube_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
        'price' => '199.00', 'position' => 1, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $db->add('videos', [
        'title' => 'Episode Two — The Return', 'description' => 'The story continues.',
        'youtube_url' => 'https://youtu.be/bbbbbbbbbbb',
        'price' => '299.00', 'position' => 2, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $db->add('videos', [
        'title' => 'Bonus — Behind the Scenes', 'description' => 'Hidden from the shop.',
        'youtube_url' => 'https://youtu.be/ccccccccccc',
        'price' => '99.00', 'position' => 3, 'is_active' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** "Season One — Complete": videos 1 + 2 for 399 (498 separately). */
function seed_bundle(FakeDatabase $db)
{
    $id = $db->add('bundles', [
        'name' => 'Season One — Complete', 'description' => 'Both episodes, one payment.',
        'price' => '399.00', 'position' => 1, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $db->add('bundle_items', ['bundle_id' => $id, 'video_id' => 1, 'position' => 1, 'created_at' => now()]);
    $db->add('bundle_items', ['bundle_id' => $id, 'video_id' => 2, 'position' => 2, 'created_at' => now()]);
    return $id;
}

/** A successful payment with product snapshot + entitlements. */
function seed_payment(FakeDatabase $db, array $overrides = [], array $videoIds = [1, 2])
{
    $defaults = [
        'order_id' => 'order_TEST123', 'payment_id' => 'pay_TEST123',
        'user_name' => 'Asha Verma', 'user_email' => 'asha@example.com', 'user_mobile' => '9876543210',
        'amount' => '399.00', 'currency' => 'INR', 'status' => 'success', 'method' => 'upi',
        'access_token' => bin2hex(random_bytes(16)),
        'access_expires_at' => null,
        'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        'product_type' => 'bundle', 'product_id' => 1, 'product_name' => 'Season One — Complete',
        'ip_address' => '203.0.113.9', 'user_agent' => 'Harness/1.0',
    ];
    $id = $db->add('payments', array_merge($defaults, $overrides));
    foreach ($videoIds as $videoId) {
        $db->add('payment_videos', ['payment_id' => $id, 'video_id' => $videoId, 'created_at' => now()]);
    }
    return $id;
}

function seed_admin(FakeDatabase $db)
{
    return $db->add('admin_users', [
        'username' => 'admin', 'name' => 'Site Owner', 'email' => 'owner@example.com',
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'is_active' => 1, 'last_login_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Sign the current request in as admin #1 (harness only). */
function fake_login()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : 'cli';
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_last_seen'] = time();
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_ua'] = hash('sha256', $ua . '|PayAccess');
}
