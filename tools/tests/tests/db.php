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
check($beforeBundle['legacy'] === null, 'no legacy product while videos exist');

$bundleId = $catalog->createBundle(['name' => 'Bundle A', 'description' => '', 'price' => 300, 'position' => 1, 'is_active' => true], [$v1]);
$catalog->createBundle(['name' => 'Hidden bundle', 'description' => '', 'price' => 100, 'position' => 2, 'is_active' => false], [$v2]);

$store = catalog_storefront();
check(count($store['videos']) === 2, 'storefront lists only active videos');
check(count($store['bundles']) === 1, 'storefront lists only active bundles');
check($store['bundles'][0]['name'] === 'Bundle A', 'bundle name reaches the shop');
check($store['legacy'] === null, 'no legacy product while the library has videos');

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

// The legacy single-video fallback.
$db = fake_db();
$db->tables['settings'][0]['youtube_video_url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$db->tables['settings'][0]['amount'] = '499.00';
app()->settings->refresh();
foreach (['videos', 'bundles', 'bundle_items'] as $table) {
    $db->tables[$table] = [];
}
$legacyStore = catalog_storefront();
check($legacyStore['legacy'] !== null, 'legacy product appears when the library is empty');
check(abs($legacyStore['legacy']['price'] - 499.0) < 0.001, 'legacy product uses the settings price');
check((int) $legacyStore['legacy']['videos'][0]['id'] === 0, 'legacy video has no id');
$legacySelected = catalog_selected_product($legacyStore, '');
check($legacySelected['type'] === 'single', 'legacy product is sold as type single');

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

$db->tables['settings'][0]['youtube_video_url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
app()->settings->refresh();
$legacyId = $payments->create([
    'order_id' => 'order_3', 'user_name' => 'Old Buyer', 'user_email' => 'old@example.com',
    'user_mobile' => '9000000002', 'amount' => 199, 'currency' => 'INR',
]);
$payments->markSuccess($legacyId, ['payment_id' => 'pay_3', 'method' => 'upi']);
$legacy = $payments->find($legacyId);
catalog_grant_for_payment($legacy);
check(catalog_videos_for_payment($legacy) !== [], 'legacy payment still gets the settings video');

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
check(abs($stats['revenue'] - 1599.0) < 0.01, 'stats sums revenue: ' . $stats['revenue']);
check($stats['active_access'] >= 1, 'lifetime access counts as active');

$export = $payments->exportRows([]);
check(count($export) === $allPayments, 'export returns every row');
$names = array_values(array_filter(array_map(function ($row) {
    return $row['product_name'];
}, $export)));
check(in_array('Pair', $names, true), 'export includes the product name: ' . json_encode($names));

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

// ---------------------------------------------------------------------------
echo $pass . " passed, " . count($fail) . " failed\n";
foreach ($fail as $message) {
    echo "FAIL " . $message . "\n";
}
