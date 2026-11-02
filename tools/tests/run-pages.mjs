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
    ],
    hasNot: ['Bonus — Behind the Scenes', 'Fatal error', 'Warning:', 'Notice:'],
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
    has: ['Season One — Complete', 'Episode One — The Beginning', 'Bundle', 'Video', 'Asha Verma', 'Ravi Kumar'],
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
    has: ['Restore'],
    hasNot: ['Fatal error', 'Warning:'],
});

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
    code += `$_REQUEST = array_merge($_GET, $_POST);\n`;
    code += `$_SERVER['QUERY_STRING'] = ${JSON.stringify(s.opts.query ?? '')};\n`;
    if (s.opts.login) code += 'fake_login();\n';
    code += `require '/app/${s.script}';\n`;

    const result = await php.run(code);
    const html = result.out;
    fs.writeFileSync(path.join(outDir, s.name + '.html'), html);

    try {
        assert(result.err.trim() === '', 'stderr: ' + result.err.slice(0, 400));
        assert(html.length > 200, 'page looks empty (' + html.length + ' bytes)');
        assert(!/Fatal error|Uncaught (Error|Exception)|Parse error/i.test(html), 'PHP fatal in output');
        for (const needle of s.checks.has) contains(html, needle);
        for (const needle of s.checks.hasNot) notContains(html, needle);
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
