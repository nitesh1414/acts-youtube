/**
 * Page smoke tests: boot the real entry scripts against the fake database and
 * assert on the rendered HTML (text, ordering, and "must not appear" rules).
 *
 *   node run-pages.mjs [repo-root]
 */
import fs from 'node:fs';
import path from 'node:path';
import { bootApp, assert, contains, notContains, REPO } from './harness.mjs';

const outDir = path.join(process.cwd(), 'out');
fs.mkdirSync(outDir, { recursive: true });

const PRELUDE = `<?php
require '/app/lib/helpers.php';
require '/app/lib/db.php';
require '/app/lib/schema.php';
require '/app/lib/settings.php';
require '/app/tests-support/fakedb.php';
require '/app/tests-support/seed.php';
$_SERVER['HTTP_USER_AGENT'] = 'Harness/1.0';
$db = FakeDatabase::reset();
$db->install();
seed_admin($db);
require '/app/config.php';
`;

const scenarios = [];
/** @param {object} checks { has, hasNot, order } */
function scenario(name, seedCode, script, checks, opts = {}) {
    scenarios.push({ name, seedCode, script, checks, opts });
}

// ---------------------------------------------------------------------------
// Storefront
// ---------------------------------------------------------------------------

scenario('home-library', `
seed_videos($db);
seed_bundle($db);
`, 'index.php', {
    has: [
        'data-product-card="video:1"', 'data-product-card="video:2"', 'data-product-card="bundle:1"',
        'Episode One — The Beginning', 'Episode Two — The Return', 'Season One — Complete',
        'id="pay-product-name"', 'name="item_type"', 'name="item_id"',
        'value="video"', 'value="1"', 'Save ', 'data-buy',
        'data-price="19900"', 'data-price="39900"',
        'Start here · Bundles', 'Or buy a single video', 'Checkout', 'hero__stats',
        // mobile app shell
        'data-tabbar', 'tabbar__item is-active', 'data-buy-bar', 'id="buy-bar-price"',
        'class="site-body has-tabbar',
        // e-mail is optional, name + mobile are not
        'Email address <span class="field__optional">optional</span>',
        'placeholder="you@example.com (not required)"',
        'id="name" name="name" type="text" autocomplete="name" placeholder="Your full name" required',
        'id="mobile" name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="98765 43210" required',
    ],
    hasNot: [
        'placeholder="you@example.com" required',
        'Bonus — Behind the Scenes', 'Fatal error', 'Warning:', 'Notice:',
    ],
    order: [
        // every buying option comes before anything informational
        ['hero__stats', 'id="bundles"'],
        ['id="bundles"', 'id="library"'],
        ['id="library"', 'id="checkout"'],
        ['id="bundles"', 'id="checkout"'],
        ['id="checkout"', 'id="how"'],
        ['id="checkout"', 'id="faq"'],
        // the bundle cards themselves are rendered above the single videos
        ['data-product-card="bundle:1"', 'data-product-card="video:1"'],
        ['Season One — Complete</h3>', 'Episode One — The Beginning</h3>'],
        // the bundle's pictures sit above its title
        ['bundle-card__media', 'Season One — Complete'],
    ],
    count: [
        // both videos in the bundle + both single-video cards
        ['bundle-card__shot', 2],
        ['i.ytimg.com/vi/', 4],
    ],
});

scenario('home-empty', '', 'index.php', {
    has: ['The library is empty'],
    hasNot: ['Fatal error', 'Warning:', 'Undefined', 'id="checkout"'],
});

scenario('home-buy-video', `
seed_videos($db);
seed_bundle($db);
`, 'index.php', {
    has: [
        'id="pay-product-name">Episode Two — The Return',
        'id="item-id" value="2"',
        'data-amount-paise="29900"',
        'value="video"',
    ],
    hasNot: ['Fatal error', 'Warning:'],
}, { query: '?buy=video:2', get: { buy: 'video:2' } });

scenario('home-buy-bundle', `
seed_videos($db);
seed_bundle($db);
`, 'index.php', {
    has: [
        'id="pay-product-name">Season One — Complete',
        'id="item-id" value="1"',
        'data-amount-paise="39900"',
        'value="bundle"',
    ],
    hasNot: ['Fatal error', 'Warning:'],
}, { query: '?buy=bundle:1', get: { buy: 'bundle:1' } });

scenario('home-single-video-savings', `
seed_videos($db);
seed_bundle($db);
`, 'index.php', {
    has: [
        'Also in <strong>Season One — Complete</strong>',
        'id="save-hint"',
        'Season One — Complete includes this video',
        'keep just this one if that is all you want',
        'data-suggest-id="1"',
        'data-suggest-badge="Bundle saves ₹99"',
        'id="save-hint-badge-text">Bundle saves ₹99',
        '₹399 for 2 videos · ₹199.50 each (₹498 separately)',
        'Choose the bundle instead',
        'Pay ₹199',
    ],
    hasNot: ['Fatal error', 'Warning:'],
}, { query: '?buy=video:1', get: { buy: 'video:1' } });

scenario('home-bundle-savings-box', `
seed_videos($db);
seed_bundle($db);
`, 'index.php', {
    has: [
        'You save ₹99',
        'or pick a single video above if that suits you better',
        'data-savings="9900"',
        // the checkout box describes the bundle itself, not an upgrade offer
        'id="save-hint-badge-text">You save ₹99',
        'id="save-hint-title">',
        'Season One — Complete',
        '₹399 for 2 videos',
    ],
    hasNot: ['Fatal error', 'Warning:'],
}, { query: '?buy=bundle:1', get: { buy: 'bundle:1' } });

scenario('home-video-without-bundle', `
seed_videos($db);
`, 'index.php', {
    has: ['id="save-hint"'],
    hasNot: ['Fatal error', 'Warning:', 'includes this video', 'data-suggest-id'],
}, { query: '?buy=video:2', get: { buy: 'video:2' } });

scenario('home-bundle-many-videos', `
seed_videos($db);
$db->tables['videos'][2]['is_active'] = 1;
seed_bundle($db);
$db->add('bundle_items', ['bundle_id' => 1, 'video_id' => 3, 'position' => 3, 'created_at' => now()]);
`, 'index.php', {
    has: ['bundle-card__media', 'bundle-card__more', '+1', 'Bundle · 3 videos'],
    hasNot: ['Fatal error', 'Warning:'],
    count: [['bundle-card__shot', 2]],
});

scenario('home-limited-access', `
$db->tables["settings"][0]["access_days"] = "30";
seed_videos($db);
`, 'index.php', {
    has: ['30 days', '30-day access'],
    hasNot: ['Fatal error', 'Warning:'],
});

// ---------------------------------------------------------------------------
// Buyer library
// ---------------------------------------------------------------------------

const TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

// ---------------------------------------------------------------------------
// Already purchased: bundles and the videos inside them show as owned
// ---------------------------------------------------------------------------

scenario('home-bundle-purchased', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
$_SESSION['access_token'] = '${TOKEN}';
`, 'index.php', {
    has: [
        'bundle-card is-owned',
        'chip--owned">', 'Purchased',
        'Purchased — 2 of 2 videos in your library',
        'Saved ₹99',
        'id="owned-card"', 'You already own this', 'Already in your library',
        'Watch now', 'Paid for — nothing more to pay',
        '2 videos and 1 bundle are still on sale',
        // the checkout opens on video 1, which came with the bundle
        'You already own this video as part of a bundle',
        'Included in <strong>Season One — Complete</strong>',
        '2 owned',
    ],
    count: [['bundle-card__shot', 2]],
    hasNot: [
        'id="payment-form"', 'name="item_type"', 'Pay ₹',
        'Choose this bundle', 'id="save-hint"',
        'data-buy-bar',
        'Fatal error', 'Warning:', 'Notice:',
    ],
});

scenario('home-bundle-purchased-selected', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
$_SESSION['access_token'] = '${TOKEN}';
`, 'index.php', {
    has: [
        'id="owned-card"',
        'This bundle is already paid for — all 2 videos inside it are unlocked',
        'Purchased — 2 of 2 videos in your library',
    ],
    hasNot: ['id="payment-form"', 'Choose this bundle', 'Fatal error', 'Warning:'],
}, { get: { buy: 'bundle:1' }, query: '?buy=bundle:1' });

scenario('home-single-video-owned', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['product_type' => 'video', 'product_id' => 1, 'product_name' => 'Episode One — The Beginning', 'amount' => '199.00'], [1]);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
$_SESSION['access_token'] = '${TOKEN}';
`, 'index.php', {
    has: [
        'id="owned-card"', 'You already paid for this video',
        '1 owned',
        // the bundle is still on sale and still nudges
        'Choose this bundle',
        'Buy this video',
    ],
    hasNot: [
        'id="payment-form"', 'Purchased —', 'bundle-card is-owned',
        'Fatal error', 'Warning:',
    ],
}, { get: { buy: 'video:1' }, query: '?buy=video:1' });

scenario('home-owner-buys-missing-video', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['product_type' => 'video', 'product_id' => 1, 'product_name' => 'Episode One — The Beginning', 'amount' => '199.00'], [1]);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
$_SESSION['access_token'] = '${TOKEN}';
`, 'index.php', {
    has: [
        // the video they do not own is still sold normally
        'id="payment-form"', 'id="pay-product-name">Episode Two — The Return',
        'id="item-id" value="2"', 'data-amount-paise="29900"', 'Pay ₹299',
        'Episode One — The Beginning',
    ],
    hasNot: ['id="owned-card"', 'Fatal error', 'Warning:'],
}, { get: { buy: 'video:2' }, query: '?buy=video:2' });

scenario('library-bundle', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: [
        'Episode One — The Beginning', 'Episode Two — The Return',
        'Your videos are ready.', 'Season One — Complete',
        'does not expire', 'id="player"',
        'youtube-nocookie.com/embed/aaaaaaaaaaa', 'library__item',
        'Copy my private access link',
        'data-tabbar', 'tabbar__label">Library',
    ],
    hasNot: ['Fatal error', 'Warning:', 'day(s)'],
}, { query: `?t=${TOKEN}`, get: { t: TOKEN } });

scenario('library-selected-video', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: ['youtube-nocookie.com/embed/bbbbbbbbbbb', 'Episode Two — The Return', 'Playing now'],
    hasNot: ['Fatal error', 'Warning:'],
}, { get: { t: TOKEN, v: '2' }, query: `?t=${TOKEN}&v=2` });

scenario('library-single-video', `
seed_videos($db);
seed_payment($db, ['product_type' => 'video', 'product_id' => 1, 'product_name' => 'Episode One — The Beginning', 'amount' => '199.00', 'access_expires_at' => date('Y-m-d H:i:s', strtotime('+20 days'))], [1]);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: ['Episode One — The Beginning', 'more days', 'Your receipt'],
    hasNot: ['Fatal error', 'Warning:', 'day(s)'],
}, { get: { t: TOKEN } });

scenario('library-across-purchases', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['order_id' => 'order_A', 'payment_id' => 'pay_A'], [1]);
seed_payment($db, ['order_id' => 'order_B', 'payment_id' => 'pay_B', 'product_type' => 'video', 'product_id' => 2, 'product_name' => 'Episode Two — The Return', 'amount' => '299.00'], [2]);
$db->tables['payments'][1]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: ['Episode One — The Beginning', 'Episode Two — The Return', '1 new', '2 in your library', 'Your library'],
    hasNot: ['Fatal error', 'Warning:'],
}, { get: { t: TOKEN }, query: `?t=${TOKEN}` });

scenario('library-expired', `
seed_videos($db);
seed_payment($db, ['access_expires_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: ['locked'],
    hasNot: ['Fatal error', 'Warning:'],
}, { get: { t: TOKEN } });

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------

scenario('admin-videos', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
`, 'admin/videos.php', {
    has: ['Video library', 'Episode One — The Beginning', 'Bonus — Behind the Scenes', 'On sale', 'Hidden', 'Add video'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-videos-empty', '', 'admin/videos.php', {
    has: ['No videos yet', 'Add your first video'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-video-new', '', 'admin/video.php', {
    has: ['New video', 'YouTube link', 'name="price"', 'name="youtube_url"', 'Order in the library'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-video-edit', `seed_videos($db);`, 'admin/video.php', {
    has: ['Video details', 'Episode One', 'value="199"', 'Delete video', 'Sold'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true, get: { id: '1' }, query: '?id=1' });

scenario('admin-bundles', `
seed_videos($db);
seed_bundle($db);
`, 'admin/bundles.php', {
    has: ['Bundles', 'Season One — Complete', 'Save ', '399', '498', '2 video(s)', 'New bundle'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-bundles-empty', '', 'admin/bundles.php', {
    has: ['No bundles yet', 'Add a video'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-bundle-new', `seed_videos($db);`, 'admin/bundle.php', {
    has: ['New bundle', 'name="name"', 'name="video_ids[]"', 'Episode One', 'Bundle price', 'data-prices='],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-bundle-edit', `
seed_videos($db);
seed_bundle($db);
`, 'admin/bundle.php', {
    has: ['Bundle details', 'Season One — Complete', 'checked', 'Delete bundle'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true, get: { id: '1' }, query: '?id=1' });

scenario('admin-payment-lifetime', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
`, 'admin/payment.php', {
    has: ['Asha Verma', 'Season One — Complete', 'Lifetime access', 'no expiry', 'Unlocked videos', 'Episode One', 'Episode Two', 'value="0"'],
    hasNot: ['Fatal error', 'Warning:', 'day(s)', 'works until'],
}, { login: true, get: { id: '1' }, query: '?id=1' });

scenario('admin-payment-limited', `
seed_videos($db);
seed_payment($db, ['access_expires_at' => date('Y-m-d H:i:s', strtotime('+12 days'))], [1]);
`, 'admin/payment.php', {
    has: ['day', 'left', 'works until'],
    hasNot: ['Fatal error', 'Warning:', 'Lifetime access'],
}, { login: true, get: { id: '1' }, query: '?id=1' });

scenario('admin-payments-list', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
seed_payment($db, ['order_id' => 'order_B', 'payment_id' => 'pay_B', 'status' => 'pending', 'product_type' => 'video', 'product_id' => 1, 'product_name' => 'Episode One — The Beginning', 'user_name' => 'Ravi Kumar', 'user_email' => 'ravi@example.com', 'access_expires_at' => null, 'paid_at' => null], []);
`, 'admin/payments.php', {
    has: [
        'Season One — Complete', 'Episode One — The Beginning', 'Bundle', 'Video', 'Asha Verma', 'Ravi Kumar',
        // phone layout: bottom bar, drawer close button and labelled card cells
        'data-admin-tabbar', 'admin-tabbar__item is-active', 'sidebar__close',
        'data-label="Product"', 'data-label="Amount"', 'data-label="Status"', 'cell-main',
    ],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-settings', '', 'admin/settings.php', {
    has: ['Legacy single video', '0 = forever', 'access_days', 'Fallback price', 'Bundles'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

scenario('admin-dashboard', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
`, 'admin/index.php', {
    has: ['Dashboard', '399', 'Videos on sale'],
    hasNot: ['Fatal error', 'Warning:'],
}, { login: true });

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

scenario('restore-page', '', 'restore.php', {
    has: [
        'Restore access',
        'Enter the <strong>mobile number and name</strong> you used at checkout',
        'No e-mail or password needed',
        'id="mobile" name="mobile" type="tel" autocomplete="tel" inputmode="tel" required',
        'id="name" name="name" type="text" autocomplete="name" required',
        'Email address <span class="field__optional">optional</span>',
        'placeholder="you@example.com (not required)"',
        'Your access does not expire',
    ],
    hasNot: [
        'placeholder="you@example.com" required',
        'Fatal error', 'Warning:',
    ],
});

// ---------------------------------------------------------------------------
// Restore access: mobile + name, e-mail optional (real POST through the form)
// ---------------------------------------------------------------------------

scenario('restore-post-mobile-name', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['user_email' => '']);
`, 'restore.php', {
    has: ['http-equiv="refresh"', 'success.php?t='],
    hasNot: ['Fatal error', 'Warning:', 'could not find'],
}, {
    post: { name: 'asha   verma', mobile: '98765 43210', email: '' },
    csrf: true,
    redirect: true,
});

scenario('restore-post-email-proof', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
`, 'restore.php', {
    has: ['http-equiv="refresh"', 'success.php?t='],
    hasNot: ['Fatal error', 'Warning:', 'could not find'],
}, {
    post: { name: 'A. Verma', mobile: '9876543210', email: 'Asha@Example.com' },
    csrf: true,
    redirect: true,
});

scenario('restore-post-wrong-name', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['user_email' => '']);
`, 'restore.php', {
    has: [
        'We could not find a successful payment for that mobile number and name',
        'value="Ravi Kumar"',
        'value="9876543210"',
    ],
    hasNot: ['http-equiv="refresh"', 'Fatal error', 'Warning:'],
}, {
    post: { name: 'Ravi Kumar', mobile: '9876543210', email: '' },
    csrf: true,
});

scenario('restore-post-bad-email-format', `
seed_videos($db);
seed_bundle($db);
seed_payment($db);
`, 'restore.php', {
    has: ['That e-mail address does not look right'],
    hasNot: ['http-equiv="refresh"', 'Fatal error', 'Warning:'],
}, {
    post: { name: 'Asha Verma', mobile: '9876543210', email: 'not-an-email' },
    csrf: true,
});

scenario('library-no-email', `
seed_videos($db);
seed_bundle($db);
seed_payment($db, ['user_email' => '']);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`, 'success.php', {
    has: [
        'Episode One — The Beginning', 'Episode Two — The Return',
        'Mobile', '+91 98765 43210', 'Your receipt',
        // the library blurb falls back to the mobile number, never an empty gap
        'mobile number +91 98765 43210',
    ],
    hasNot: ['Fatal error', 'Warning:', '<dt>Email</dt>', 'Undefined'],
}, { get: { t: TOKEN } });

scenario('login-page', '', 'admin/login.php', {
    has: ['Sign in', 'username'],
    hasNot: ['Fatal error', 'Warning:'],
});

scenario('locked-page', '', 'success.php', {
    has: ['locked'],
    hasNot: ['Fatal error', 'Warning:'],
});

// ---------------------------------------------------------------------------
// Runner
// ---------------------------------------------------------------------------

const phpArray = value => "json_decode('" + JSON.stringify(value).replace(/'/g, "\\'") + "', true)";

let failures = 0;
for (const s of scenarios) {
    const php = await bootApp(REPO);

    let code = PRELUDE + s.seedCode;
    code += `$_GET = ${phpArray(s.opts.get ?? {})};\n`;
    code += `$_POST = ${phpArray(s.opts.post ?? {})};\n`;
    if (s.opts.post && Object.keys(s.opts.post).length) code += "$_SERVER['REQUEST_METHOD'] = 'POST';\n";
    if (s.opts.csrf) code += "$_POST['_token'] = csrf_token();\n";
    code += `$_REQUEST = array_merge($_GET, $_POST);\n`;
    code += `$_SERVER['QUERY_STRING'] = ${JSON.stringify(s.opts.query ?? '')};\n`;
    if (s.opts.login) code += 'fake_login();\n';
    code += `require '/app/${s.script}';\n`;

    const result = await php.run(code);
    const html = result.out;
    fs.writeFileSync(path.join(outDir, s.name + '.html'), html);

    try {
        assert(result.err.trim() === '', 'stderr: ' + result.err.slice(0, 400));
        // A redirecting POST legitimately renders only a tiny meta-refresh page.
        assert(html.length > 200 || s.opts.redirect, 'page looks empty (' + html.length + ' bytes)');
        assert(!/Fatal error|Uncaught (Error|Exception)|Parse error/i.test(html), 'PHP fatal in output');
        for (const needle of s.checks.has) contains(html, needle);
        for (const needle of s.checks.hasNot) notContains(html, needle);
        for (const [needle, expected] of s.checks.count ?? []) {
            const seen = html.split(needle).length - 1;
            assert(seen === expected, JSON.stringify(needle) + ' should appear ' + expected + ' time(s), saw ' + seen);
        }
        for (const [first, second] of s.checks.order ?? []) {
            const a = html.indexOf(first);
            const b = html.indexOf(second);
            assert(a >= 0, 'missing ' + JSON.stringify(first));
            assert(b >= 0, 'missing ' + JSON.stringify(second));
            assert(a < b, JSON.stringify(first) + ' should come before ' + JSON.stringify(second));
        }
    } catch (e) {
        failures++;
        console.log('FAIL ' + s.name + ': ' + e.message);
        if (result.err.trim()) console.log('   stderr: ' + result.err.trim().slice(0, 600));
        continue;
    }
    console.log('ok   ' + s.name + '  (' + html.length + ' bytes)');
}

console.log('\n' + (scenarios.length - failures) + '/' + scenarios.length + ' scenarios passed, ' + failures + ' failed');
process.exit(failures === 0 ? 0 : 1);
