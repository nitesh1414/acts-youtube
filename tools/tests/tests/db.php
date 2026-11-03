<?php
/**
 * Library / payment logic tests: pricing, bundles, entitlements, lifetime
 * access, storefront assembly and the payment repository.
 * Executed by run-db.mjs inside php-wasm.
 */

require '/app/lib/helpers.php';
require '/app/lib/db.php';
require '/app/lib/schema.php';
require '/app/lib/settings.php';
require '/app/tests-support/fakedb.php';

$db = FakeDatabase::reset();
$db->install();
require '/app/tests-support/seed.php';
seed_admin($db);
require '/app/config.php';

$pass = 0;
$fail = [];
function check($condition, $label)
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        return;
    }
    $fail[] = $label;
}

/** Empty the catalogue and payments, keep settings + admin. */
function reset_data()
{
    $db = fake_db();
    foreach (['videos', 'bundles', 'bundle_items', 'payment_videos', 'payments', 'payment_events', 'rate_events'] as $table) {
        $db->tables[$table] = [];
    }
    $db->tables['settings'][0]['youtube_video_url'] = '';
    $db->tables['settings'][0]['access_days'] = '0';
    $db->tables['settings'][0]['amount'] = '199.00';
    app()->settings->refresh();
}

// ---------------------------------------------------------------------------
// Videos
// ---------------------------------------------------------------------------
reset_data();
$catalog = app()->catalog;

$firstId = $catalog->createVideo([
    'title' => ' Episode One ', 'description' => 'Intro', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
    'price' => '199.5', 'position' => 2, 'is_active' => true,
]);
$secondId = $catalog->createVideo([
    'title' => 'Episode Two', 'description' => '', 'youtube_url' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
    'price' => 299, 'position' => 1, 'is_active' => true,
]);
$catalog->createVideo([
    'title' => 'Hidden', 'description' => 'x', 'youtube_url' => 'https://youtu.be/ccccccccccc',
    'price' => 99, 'position' => 3, 'is_active' => false,
]);

$video = $catalog->video($firstId);
check($video !== null, 'created video is readable');
check($video['title'] === 'Episode One', 'title trimmed: ' . $video['title']);
check(abs($video['price'] - 199.5) < 0.001, 'price stored as float');
check($video['is_active'] === true, 'is_active is a bool');
check($video['youtube_id'] === 'dQw4w9WgXcQ', 'youtube id derived from the link');
check($video['embed_url'] !== '' && $video['thumbnail'] !== '', 'embed url + thumbnail derived');

$all = $catalog->videos();
check(count($all) === 3, 'three videos listed, got ' . count($all));
check((int) $all[0]['id'] === $secondId, 'ordered by position');
check(count($catalog->videos(true)) === 2, 'activeOnly hides inactive videos');
check($catalog->counts()['videos'] === 2, 'counts() counts videos on sale');

$catalog->updateVideo($secondId, [
    'title' => 'Episode Two (remastered)', 'description' => 'Now in HD', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa',
    'price' => 349, 'position' => 1, 'is_active' => true,
]);
$updated = $catalog->video($secondId);
check($updated['title'] === 'Episode Two (remastered)', 'updateVideo writes the title');
check(abs($updated['price'] - 349) < 0.001, 'updateVideo writes the price');

// ---------------------------------------------------------------------------
// Bundles
// ---------------------------------------------------------------------------
reset_data();
$v1 = $catalog->createVideo(['title' => 'One', 'description' => '', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'price' => 200, 'position' => 1, 'is_active' => true]);
$v2 = $catalog->createVideo(['title' => 'Two', 'description' => '', 'youtube_url' => 'https://youtu.be/bbbbbbbbbbb', 'price' => 300, 'position' => 2, 'is_active' => true]);
$v3 = $catalog->createVideo(['title' => 'Three', 'description' => '', 'youtube_url' => 'https://youtu.be/ccccccccccc', 'price' => 100, 'position' => 3, 'is_active' => true]);

$bundleId = $catalog->createBundle([
    'name' => 'The Complete Set', 'description' => 'Every episode.', 'price' => 450, 'position' => 1, 'is_active' => true,
], [$v1, $v2]);

$bundle = $catalog->bundle($bundleId);
check($bundle !== null, 'bundle created');
check($bundle['name'] === 'The Complete Set', 'bundle keeps its name');
check($bundle['video_count'] === 2, 'bundle has two videos');
check(abs($bundle['single_total'] - 500.0) < 0.001, 'single_total sums the video prices');
check(abs($bundle['savings'] - 50.0) < 0.001, 'savings computed');
check($bundle['thumbnail'] !== '', 'bundle takes the first video thumbnail');

$catalog->setBundleVideos($bundleId, [$v3, $v2, $v3, 0, -5]);
$bundle = $catalog->bundle($bundleId);
check($bundle['video_count'] === 2, 'setBundleVideos de-duplicates: ' . $bundle['video_count']);
check($bundle['video_ids'] === [$v3, $v2], 'setBundleVideos keeps the given order');

$catalog->deleteVideo($v3);
$bundle = $catalog->bundle($bundleId);
check($catalog->video($v3) === null, 'unused/unsold video is really deleted');
check($bundle['video_count'] === 1, 'bundle survives a deleted video');

// ---------------------------------------------------------------------------
// Storefront
// ---------------------------------------------------------------------------
reset_data();
$v1 = $catalog->createVideo(['title' => 'Alpha', 'description' => 'A', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'price' => 150, 'position' => 1, 'is_active' => true]);
$v2 = $catalog->createVideo(['title' => 'Beta', 'description' => 'B', 'youtube_url' => 'https://youtu.be/bbbbbbbbbbb', 'price' => 250, 'position' => 2, 'is_active' => true]);
$v3 = $catalog->createVideo(['title' => 'Gamma', 'description' => 'C', 'youtube_url' => 'https://youtu.be/ccccccccccc', 'price' => 350, 'position' => 3, 'is_active' => false]);

$beforeBundle = catalog_storefront();
check(count($beforeBundle['videos']) === 2, 'storefront lists the active videos');
check($beforeBundle['bundles'] === [], 'no bundles before one is created');
check(!isset($beforeBundle['legacy']), 'the storefront has no fallback product at all');

$bundleId = $catalog->createBundle(['name' => 'Bundle A', 'description' => '', 'price' => 300, 'position' => 1, 'is_active' => true], [$v1]);
$catalog->createBundle(['name' => 'Hidden bundle', 'description' => '', 'price' => 100, 'position' => 2, 'is_active' => false], [$v2]);

$store = catalog_storefront();
check(count($store['videos']) === 2, 'storefront lists only active videos');
check(count($store['bundles']) === 1, 'storefront lists only active bundles');
check($store['bundles'][0]['name'] === 'Bundle A', 'bundle name reaches the shop');
check(count($store) === 2 && isset($store['videos'], $store['bundles']), 'storefront is exactly videos + bundles');

$selected = catalog_selected_product($store, 'video:2');
check($selected['type'] === 'video' && (int) $selected['id'] === $v2, '?buy=video:2 selects that video');
$selected = catalog_selected_product($store, 'video_2');
check($selected['type'] === 'video' && (int) $selected['id'] === $v2, '?buy=video_2 also works');
$selected = catalog_selected_product($store, 'bundle:' . $bundleId);
check($selected['type'] === 'bundle' && (int) $selected['id'] === $bundleId, '?buy=bundle:ID selects the bundle');
$selected = catalog_selected_product($store, 'video:999');
check($selected['type'] === 'video' && (int) $selected['id'] === $v1, 'unknown id falls back to the first video');
$selected = catalog_selected_product($store, 'garbage');
check($selected !== null && $selected['type'] === 'video', 'garbage falls back to a real product');

// --- the "also available in a bundle" hint --------------------------------
$secondBundleId = $catalog->createBundle(['name' => 'Bundle B', 'description' => '', 'price' => 333, 'position' => 3, 'is_active' => true], [$v1, $v2]);
$store = catalog_storefront();

$inBundle = catalog_bundles_for_video($store, $v1);
check(count($inBundle) === 2, 'both bundles contain video 1, got ' . count($inBundle));
check((float) $inBundle[0]['price'] <= (float) $inBundle[1]['price'], 'cheapest bundle comes first');

$best = catalog_best_bundle_for_video($store, $v1);
check($best !== null && $best['name'] === 'Bundle A', 'the cheaper bundle is offered first');
check(catalog_best_bundle_for_video($store, $v3) === null, 'a video outside every bundle has no hint');
check(catalog_bundles_for_video($store, 999) === [], 'unknown video has no bundles');

// The hint must never change what was chosen: the video stays purchasable.
$videoProduct = catalog_product('video', $v1);
check(abs($videoProduct['price'] - 150.0) < 0.001, 'the single video keeps its own price');
check($videoProduct['type'] === 'video', 'the single video stays type video');
$bundleProduct = catalog_product('bundle', $secondBundleId);
check(abs($bundleProduct['savings'] - 67.0) < 0.001, 'bundle product exposes the saving: ' . $bundleProduct['savings']);
check(abs($bundleProduct['single_total'] - 400.0) < 0.001, 'bundle product exposes the separate total');
// A bundle priced above its parts simply shows no saving.
check(abs(catalog_product('bundle', $bundleId)['savings']) < 0.001, 'over-priced bundle reports zero saving');

// catalog_product() re-reads prices from the database (never the client).
check(abs(catalog_product('video', $v2)['price'] - 250.0) < 0.001, 'catalog_product returns the stored price');
check(catalog_product('video', $v3) === null, 'catalog_product skips hidden products for the shop');
check(catalog_product('video', $v3, false) !== null, 'catalog_product can include hidden products');
check(catalog_product('bundle', $bundleId)['name'] === 'Bundle A', 'bundle product carries its name');
check(catalog_product('nosuch', 1) === null, 'unknown product type is rejected');

// No fallback product: an empty library means nothing is on sale.
$db = fake_db();
foreach (['videos', 'bundles', 'bundle_items'] as $table) {
    $db->tables[$table] = [];
}
$emptyStore = catalog_storefront();
check($emptyStore['videos'] === [] && $emptyStore['bundles'] === [], 'an empty library lists nothing');
check(catalog_selected_product($emptyStore, '') === null, 'nothing is preselected when nothing is on sale');
check(catalog_selected_product($emptyStore, 'video:1') === null, 'a stale ?buy= link selects nothing');
check(catalog_product('video', 1) === null, 'a missing video cannot be bought');
check(function_exists('catalog_legacy_product') === false, 'the legacy product helper was deleted');

// The store-wide price setting is gone for good.
$settingsModel = (string) file_get_contents('/app/lib/settings.php');
check(strpos($settingsModel, 'function amount(') === false, 'Settings::amount() was deleted');
$settingsAll = app()->settings->all();
check(!array_key_exists('amount', $settingsAll), 'settings no longer expose a fallback price');
check(!array_key_exists('youtube_video_url', $settingsAll), 'settings no longer expose a fallback video link');

// ---------------------------------------------------------------------------
// Payments, entitlements, lifetime access
// ---------------------------------------------------------------------------
reset_data();
$db = fake_db();
$v1 = $catalog->createVideo(['title' => 'One', 'description' => '', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'price' => 200, 'position' => 1, 'is_active' => true]);
$v2 = $catalog->createVideo(['title' => 'Two', 'description' => '', 'youtube_url' => 'https://youtu.be/bbbbbbbbbbb', 'price' => 300, 'position' => 2, 'is_active' => true]);
$bundleId = $catalog->createBundle(['name' => 'Pair', 'description' => '', 'price' => 400, 'position' => 1, 'is_active' => true], [$v1, $v2]);

$payments = app()->payments;
$paymentId = $payments->create([
    'order_id' => 'order_1', 'user_name' => 'Test Buyer', 'user_email' => 'buyer@example.com',
    'user_mobile' => '9876543210', 'amount' => 400, 'currency' => 'INR',
    'product_type' => 'bundle', 'product_id' => $bundleId, 'product_name' => 'Pair',
]);

$payment = $payments->find($paymentId);
check($payment['product_type'] === 'bundle', 'payment stores the product type');
check((int) $payment['product_id'] === $bundleId, 'payment stores the product id');
check($payment['product_name'] === 'Pair', 'payment stores the product name snapshot');
check($payment['status'] === 'pending', 'new payment is pending');
check((int) $db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [$paymentId], 0) === 0, 'no entitlement before success');

check((int) $payments->paginate(['q' => 'Pair'])['total'] === 1, 'searching by product name finds the payment');
check((int) $payments->paginate(['q' => 'nothing-like-this'])['total'] === 0, 'unrelated searches match nothing');

$payments->markSuccess($paymentId, ['payment_id' => 'pay_1', 'method' => 'upi']);
check($payments->find($paymentId)['status'] === 'success', 'markSuccess sets success');
catalog_grant_for_payment($payments->find($paymentId));
catalog_grant_for_payment($payments->find($paymentId)); // webhook + verify both call it

$unlocked = catalog_videos_for_payment($payments->find($paymentId));
check(count($unlocked) === 2, 'bundle unlocks both videos, got ' . count($unlocked));
check((int) $db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [$paymentId], 0) === 2, 'entitlements are not duplicated');
check($catalog->paymentHasEntitlements($paymentId), 'paymentHasEntitlements true');

$catalog->deleteVideo($v1);
check($catalog->video($v1) !== null, 'purchased video is hidden, not deleted');
check((int) $catalog->video($v1)['is_active'] === 0, 'purchased video was set inactive');
check(count(catalog_videos_for_payment($payments->find($paymentId))) === 2, 'hidden past purchase still plays');

// --- editing a bundle reaches the people who already bought it -------------
$v3b = $catalog->createVideo(['title' => 'Three', 'description' => '', 'youtube_url' => 'https://youtu.be/ccccccccccc', 'price' => 100, 'position' => 3, 'is_active' => true]);
$catalog->setBundleVideos($bundleId, [$v1, $v2, $v3b]);
$afterEdit = catalog_videos_for_payment($payments->find($paymentId));
check(count($afterEdit) === 3, 'a video added to the bundle reaches the existing buyer, got ' . count($afterEdit));
check(in_array($v3b, array_map('intval', array_column($afterEdit, 'id')), true), 'the new video is in the buyer\'s library');
check((int) $db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [$paymentId], 0) === 3, 'the snapshot was brought up to date too');

// The admin "what did this purchase unlock" view reads the same live list.
check((int) $catalog->videoSales($v3b) === 1, 'the added video shows the existing buyer as a sale');

// A video taken out of the bundle stops being offered, but is never revoked.
$catalog->setBundleVideos($bundleId, [$v1, $v2]);
check(count(catalog_videos_for_payment($payments->find($paymentId))) === 2, 'a removed video leaves the live bundle list');
check((int) $db->value('SELECT COUNT(*) FROM payment_videos WHERE payment_id = ?', [$paymentId], 0) === 3, 'the snapshot keeps the old row (nothing is revoked)');

// Renaming / repricing reaches buyers too.
$catalog->updateBundle($bundleId, ['name' => 'Pair — Deluxe', 'description' => '', 'price' => 350, 'position' => 1, 'is_active' => true], [$v1, $v2]);
check($catalog->bundle($bundleId)['name'] === 'Pair — Deluxe', 'the bundle can be renamed');
check(count(catalog_videos_for_payment($payments->find($paymentId))) === 2, 'renaming keeps both videos');
$catalog->updateBundle($bundleId, ['name' => 'Pair', 'description' => '', 'price' => 400, 'position' => 1, 'is_active' => true], [$v1, $v2]);

// Deleting a bundle falls back to the snapshot: access survives.
$goneBundle = $catalog->createBundle(['name' => 'Doomed', 'description' => '', 'price' => 100, 'position' => 9, 'is_active' => true], [$v1, $v2]);
$goneId = $payments->create([
    'order_id' => 'order_gone', 'user_name' => 'Gone Buyer', 'user_email' => '',
    'user_mobile' => '9000000009', 'amount' => 100, 'currency' => 'INR',
    'product_type' => 'bundle', 'product_id' => $goneBundle, 'product_name' => 'Doomed',
]);
$payments->markSuccess($goneId, ['payment_id' => 'pay_gone', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($goneId));
check(count(catalog_videos_for_payment($payments->find($goneId))) === 2, 'the doomed bundle unlocks two videos');
$catalog->deleteBundle($goneBundle);
check(count(catalog_videos_for_payment($payments->find($goneId))) === 2, 'a deleted bundle falls back to the snapshot: access survives');

// Saving a bundle nobody bought is a no-op, not an error.
$quiet = $catalog->syncBundleEntitlements(999);
check($quiet['payments'] === 0 && $quiet['grants'] === 0, 'syncing an unknown bundle does nothing');

$singleId = $payments->create([
    'order_id' => 'order_2', 'user_name' => 'Single Buyer', 'user_email' => 'single@example.com',
    'user_mobile' => '9000000001', 'amount' => 300, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $v2, 'product_name' => 'Two',
]);
$payments->markSuccess($singleId, ['payment_id' => 'pay_2', 'method' => 'card']);
catalog_grant_for_payment($payments->find($singleId));
$singleVideos = catalog_videos_for_payment($payments->find($singleId));
check(count($singleVideos) === 1, 'single purchase unlocks one video');
check((int) $singleVideos[0]['id'] === $v2, 'the right video');

// A payment that names no product unlocks nothing on its own — it is never
// silently pointed at a store-wide video.
$orphanId = $payments->create([
    'order_id' => 'order_3', 'user_name' => 'Old Buyer', 'user_email' => 'old@example.com',
    'user_mobile' => '9000000002', 'amount' => 199, 'currency' => 'INR',
]);
$payments->markSuccess($orphanId, ['payment_id' => 'pay_3', 'method' => 'upi']);
$orphan = $payments->find($orphanId);
catalog_grant_for_payment($orphan);
check(catalog_videos_for_payment($orphan) === [], 'a payment without a product unlocks nothing');

// --- a buyer's library keeps earlier purchases ----------------------------
$buyer = ['user_email' => 'repeat@example.com', 'user_mobile' => '9111111111'];
$firstBuy = $payments->create(array_merge($buyer, [
    'order_id' => 'order_r1', 'user_name' => 'Repeat Buyer', 'amount' => 300, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $v2, 'product_name' => 'Two',
]));
$payments->markSuccess($firstBuy, ['payment_id' => 'pay_r1', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($firstBuy));

$secondBuy = $payments->create(array_merge($buyer, [
    'order_id' => 'order_r2', 'user_name' => 'Repeat Buyer', 'amount' => 400, 'currency' => 'INR',
    'product_type' => 'bundle', 'product_id' => $bundleId, 'product_name' => 'Pair',
]));
$payments->markSuccess($secondBuy, ['payment_id' => 'pay_r2', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($secondBuy));

$library = catalog_library_for_payment($payments->find($secondBuy));
check(count($library) === 2, 'library merges both purchases, got ' . count($library));
check((int) $library[0]['id'] === $v1, 'newest purchase comes first');
check(count(catalog_videos_for_payment($payments->find($secondBuy))) === 2, 'this purchase unlocked the bundle');

// --- lifetime vs limited access -------------------------------------------
$lifetime = $payments->grantAccess($paymentId, 0);
check($lifetime['expires_at'] === null, 'grantAccess(0) issues a lifetime token');
check(strlen($lifetime['token']) === 64, 'lifetime token is 64 hex chars');
$loaded = $payments->find($paymentId);
check($loaded['access_expires_at'] === null, 'lifetime expiry stored as NULL');
check(Payments::accessIsValid($loaded), 'lifetime access is valid');
check(access_days_left($loaded) === null, 'access_days_left() is null for lifetime');

$limited = $payments->grantAccess($paymentId, 30);
check($limited['expires_at'] !== null, 'grantAccess(30) sets an expiry');
$loaded = $payments->find($paymentId);
check(Payments::accessIsValid($loaded), 'limited access is valid today');
$days = access_days_left($loaded);
check(is_int($days) && $days > 28 && $days <= 30, 'access_days_left counts days: ' . var_export($days, true));

foreach ($db->tables['payments'] as $index => $row) {
    if ((int) $row['id'] === (int) $paymentId) {
        $db->tables['payments'][$index]['access_expires_at'] = date('Y-m-d H:i:s', strtotime('-1 hour'));
    }
}
check(!Payments::accessIsValid($payments->find($paymentId)), 'expired access is invalid');

$payments->grantAccess($singleId, 0);
$pending = $payments->find($singleId);
$pending['status'] = 'pending';
check(!Payments::accessIsValid($pending), 'pending payments never have access');
$noToken = $payments->find($singleId);
$noToken['access_token'] = '';
check(!Payments::accessIsValid($noToken), 'missing token means no access');
$justNow = $payments->find($singleId);
$justNow['access_expires_at'] = date('Y-m-d H:i:s');
check(Payments::accessIsValid($justNow), 'a token expiring this second still works');

// --- dashboard stats -------------------------------------------------------
$stats = $payments->stats();
$allPayments = (int) $db->value('SELECT COUNT(*) FROM payments', [], 0);
check($stats['total'] === $allPayments, 'stats counts every payment');
check($stats['success_count'] === $allPayments, 'stats counts successes');
// 400 (Pair) + 300 (single) + 400 (repeat) + 100 (Doomed) + 199 + 200 (orphan) = 1599 + 100
check(abs($stats['revenue'] - 1699.0) < 0.01, 'stats sums revenue: ' . $stats['revenue']);
check($stats['active_access'] >= 1, 'lifetime access counts as active');

$export = $payments->exportRows([]);
check(count($export) === $allPayments, 'export returns every row');
$names = array_values(array_filter(array_map(function ($row) {
    return $row['product_name'];
}, $export)));
check(in_array('Pair', $names, true), 'export includes the product name: ' . json_encode($names));

// ---------------------------------------------------------------------------
// Purchased state: a bundle marks itself and every video inside it as owned
// ---------------------------------------------------------------------------
reset_data();
$db = fake_db();
$catalog = app()->catalog;
$ep1 = $catalog->createVideo(['title' => 'Ep 1', 'description' => '', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'price' => 199, 'position' => 1, 'is_active' => true]);
$ep2 = $catalog->createVideo(['title' => 'Ep 2', 'description' => '', 'youtube_url' => 'https://youtu.be/bbbbbbbbbbb', 'price' => 299, 'position' => 2, 'is_active' => true]);
$season = $catalog->createBundle(['name' => 'Season One', 'description' => '', 'price' => 399, 'position' => 1, 'is_active' => true], [$ep1, $ep2]);
$payments = app()->payments;
$buyer = ['user_name' => 'Owner Buyer', 'user_email' => 'owner@example.com', 'user_mobile' => '9222222222'];

$empty = catalog_owned_state(null);
check($empty['videos'] === [] && $empty['bundles'] === [] && $empty['covered'] === [], 'anonymous visitors own nothing');

$bundlePay = $payments->create(array_merge($buyer, [
    'order_id' => 'order_o1', 'amount' => 399, 'currency' => 'INR',
    'product_type' => 'bundle', 'product_id' => $season, 'product_name' => 'Season One',
]));
$payments->markSuccess($bundlePay, ['payment_id' => 'pay_o1', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($bundlePay));

$state = catalog_owned_state($payments->find($bundlePay));
sort($state['videos']);
check($state['videos'] === [$ep1, $ep2], 'bundle purchase owns every video in it: ' . json_encode($state['videos']));
check($state['bundles'] === [$season], 'the bundle itself is reported as purchased');
check($state['covered'] === [$season], 'a bought bundle also counts as covered');

// Buying one of the videos separately must not mark the bundle as purchased.
$singleBuyer = ['user_name' => 'Single Buyer', 'user_email' => 'one@example.com', 'user_mobile' => '9333333333'];
$singlePay = $payments->create(array_merge($singleBuyer, [
    'order_id' => 'order_o2', 'amount' => 199, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $ep1, 'product_name' => 'Ep 1',
]));
$payments->markSuccess($singlePay, ['payment_id' => 'pay_o2', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($singlePay));

$state = catalog_owned_state($payments->find($singlePay));
check($state['videos'] === [$ep1], 'single video purchase owns exactly that video');
check($state['bundles'] === [], 'the bundle is still on sale');
check($state['covered'] === [], 'a partly-owned bundle is not covered');

// Buy the rest separately: now the bundle is fully owned, but never "purchased".
$secondPay = $payments->create(array_merge($singleBuyer, [
    'order_id' => 'order_o3', 'amount' => 299, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $ep2, 'product_name' => 'Ep 2',
]));
$payments->markSuccess($secondPay, ['payment_id' => 'pay_o3', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($secondPay));

$state = catalog_owned_state($payments->find($secondPay));
sort($state['videos']);
check($state['videos'] === [$ep1, $ep2], 'library merges both single purchases');
check($state['bundles'] === [], 'owning the videos separately does not fake a bundle purchase');
check($state['covered'] === [$season], 'a fully-owned bundle is marked covered');

// A failed payment never grants ownership.
$failedBuyer = ['user_name' => 'Failed Buyer', 'user_email' => 'failed@example.com', 'user_mobile' => '9444444444'];
$failedPay = $payments->create(array_merge($failedBuyer, [
    'order_id' => 'order_o4', 'amount' => 399, 'currency' => 'INR',
    'product_type' => 'bundle', 'product_id' => $season, 'product_name' => 'Season One',
]));
$payments->markFailed($failedPay, 'card declined by the bank');
$state = catalog_owned_state($payments->find($failedPay));
check($state['videos'] === [] && $state['bundles'] === [], 'a failed payment owns nothing');

// The storefront renders those states (and never a pay form for an owned item).
$home = (string) file_get_contents('/app/templates/site/home.php');
check(strpos($home, 'catalog_owned_state(') !== false, 'storefront reads the owned state');
check(strpos($home, 'owned-card') !== false, 'owned products get their own panel instead of the pay form');
check(strpos($home, 'Purchased') !== false, 'purchased bundles are labelled');
check(strpos($home, '$selectedOwned') !== false && strpos($home, 'id="payment-form"') !== false, 'the pay form only renders for products that are not owned');

// ---------------------------------------------------------------------------
// Identity: mobile number is required, e-mail is optional
// ---------------------------------------------------------------------------
reset_data();
$db = fake_db();
$catalog = app()->catalog;
$payments = app()->payments;
$ep1 = $catalog->createVideo(['title' => 'Ep 1', 'description' => '', 'youtube_url' => 'https://youtu.be/aaaaaaaaaaa', 'price' => 199, 'position' => 1, 'is_active' => true]);
$ep2 = $catalog->createVideo(['title' => 'Ep 2', 'description' => '', 'youtube_url' => 'https://youtu.be/bbbbbbbbbbb', 'price' => 299, 'position' => 2, 'is_active' => true]);

// A checkout with no e-mail at all must be accepted and stored as ''.
$noEmail = $payments->create([
    'order_id' => 'order_ne1', 'user_name' => 'Asha Verma', 'user_mobile' => '+91 98765 43210',
    'amount' => 199, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $ep1, 'product_name' => 'Ep 1',
]);
$payments->markSuccess($noEmail, ['payment_id' => 'pay_ne1', 'method' => 'upi']);
catalog_grant_for_payment($payments->find($noEmail));

$stored = $payments->find($noEmail);
check($stored['user_email'] === '', 'a checkout without e-mail is stored as an empty string');
check($stored['user_mobile'] === '9876543210', 'the mobile number is normalised: ' . $stored['user_mobile']);

// The same number with a different (or no) e-mail is the same buyer.
$withEmail = $payments->create([
    'order_id' => 'order_ne2', 'user_name' => 'Asha Verma', 'user_email' => 'Asha@Example.com',
    'user_mobile' => '98765 43210', 'amount' => 299, 'currency' => 'INR',
    'product_type' => 'video', 'product_id' => $ep2, 'product_name' => 'Ep 2',
]);
$payments->markSuccess($withEmail, ['payment_id' => 'pay_ne2', 'method' => 'card']);
catalog_grant_for_payment($payments->find($withEmail));
check($payments->find($withEmail)['user_email'] === 'asha@example.com', 'the e-mail is lower-cased when given');

$buyerPayments = $payments->allSuccessfulForMobile('9876543210');
check(count($buyerPayments) === 2, 'both purchases are found by mobile, got ' . count($buyerPayments));
check($payments->allSuccessfulForMobile('') === [], 'an empty mobile matches nothing');

$library = catalog_library_for_payment($payments->find($withEmail));
check(count($library) === 2, 'the library merges the e-mail-less purchase, got ' . count($library));

$state = catalog_owned_state($payments->find($noEmail));
sort($state['videos']);
check($state['videos'] === [$ep1, $ep2], 'ownership merges across e-mail-less and e-mailed purchases: ' . json_encode($state['videos']));

// Restore access: the mobile number alone is enough (no name is asked for).
$restored = $payments->findRestorablePayment('9876543210');
check($restored !== null, 'restore works with the mobile number only');
check((int) $restored['id'] === $withEmail, 'restore returns the newest payment for that number');
$restored = $payments->findRestorablePayment('+91 98765 43210');
check($restored !== null && (int) $restored['id'] === $withEmail, 'restore is forgiving about spacing and the country code');
check($payments->findRestorablePayment('9876543210', 'asha@example.com') !== null, 'a matching e-mail is accepted as well');
check($payments->findRestorablePayment('9876543210', 'someone@example.com') === null, 'a wrong e-mail is rejected');
check($payments->findRestorablePayment('9000000000') === null, 'an unknown mobile finds nothing');
check($payments->findRestorablePayment('') === null, 'an empty mobile finds nothing');
check(display_email('') === '—' && display_email('a@b.in') === 'a@b.in', 'display_email hides the optional field');

// The name is no longer part of the lookup at all.
$paymentsModel = (string) file_get_contents('/app/lib/payments.php');
check(strpos($paymentsModel, 'findForRestore') === false, 'the name-based restore lookup is gone');
check(strpos($paymentsModel, 'findRestorablePayment') !== false, 'the mobile-based restore lookup exists');
check(!function_exists('names_match') && !function_exists('name_key'), 'the name-matching helpers were removed');

// A pending payment never restores access.
$pendingOnly = $payments->create([
    'order_id' => 'order_ne3', 'user_name' => 'Pending Person', 'user_mobile' => '9555555555',
    'amount' => 199, 'currency' => 'INR', 'product_type' => 'video', 'product_id' => $ep1, 'product_name' => 'Ep 1',
]);
check($payments->findRestorablePayment('9555555555') === null, 'pending payments cannot be restored');

// Customers are counted by mobile number, not by an optional e-mail.
$stats = $payments->stats();
check($stats['customers'] === 1, 'customers counts distinct mobile numbers, got ' . $stats['customers']);

// The checkout controller must not require an e-mail either.
$createOrder = (string) file_get_contents('/app/api/create_order.php');
check(
    strpos($createOrder, 'if ($email !== \'\' && !valid_email($email))') !== false,
    'create_order only validates an e-mail that was actually given'
);
$restoreController = (string) file_get_contents('/app/restore.php');
check(strpos($restoreController, 'findRestorablePayment(') !== false, 'restore looks buyers up by mobile number');
check(strpos($restoreController, 'valid_email($email)') !== false, 'restore still validates an optional e-mail');

// ---------------------------------------------------------------------------
// Safety: the checkout price always comes from the database
// ---------------------------------------------------------------------------
$createOrder = (string) file_get_contents('/app/api/create_order.php');
check(strpos($createOrder, "post_str('amount')") === false, 'create_order never trusts a client amount');
check(strpos($createOrder, "post_str('price')") === false, 'create_order never trusts a client price');
check(strpos($createOrder, 'catalog_product(') !== false, 'create_order resolves the product server-side');
check(strpos($createOrder, 'item_type') !== false && strpos($createOrder, 'item_id') !== false, 'create_order accepts item_type/item_id');

$verify = (string) file_get_contents('/app/api/verify_payment.php');
check(strpos($verify, 'catalog_grant_for_payment(') !== false, 'verify grants the bought videos');

$webhook = (string) file_get_contents('/app/api/webhook.php');
check(strpos($webhook, 'catalog_grant_for_payment(') !== false, 'webhook grants the bought videos');

// The storefront never decides a price in the browser.
$home = (string) file_get_contents('/app/templates/site/home.php');
check(strpos($home, 'catalog_best_bundle_for_video(') !== false, 'storefront offers bundles per video');
check(strpos($home, "data-price=") !== false, 'cards carry the price for the client-side switcher');

// An owned page has no pay form, so the buy links must keep working as plain
// links there (the client-side switcher would otherwise swallow the click).
$appjs = (string) file_get_contents('/app/assets/js/app.js');
check(
    strpos($appjs, "if (!document.getElementById('payment-form')) { return; }") !== false,
    'buy links still navigate when no pay form is rendered'
);

// ---------------------------------------------------------------------------
echo $pass . " passed, " . count($fail) . " failed\n";
foreach ($fail as $message) {
    echo "FAIL " . $message . "\n";
}
