/**
 * Render the public pages to standalone HTML (CSS inlined, external scripts
 * stripped) so the design can be inspected without PHP or MySQL.
 *
 *   node preview.mjs [output-dir]      (default: ../../preview)
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import { bootApp, REPO } from './harness.mjs';

const HERE = path.dirname(url.fileURLToPath(import.meta.url));
const outDir = process.argv[2] ? path.resolve(process.argv[2]) : path.join(REPO, 'preview');
fs.mkdirSync(outDir, { recursive: true });

const css = fs.readFileSync(path.join(REPO, 'assets/css/app.css'), 'utf8');

const BASE = `<?php
require '/app/lib/helpers.php';
require '/app/lib/db.php';
require '/app/lib/schema.php';
require '/app/lib/settings.php';
require '/app/tests-support/fakedb.php';
require '/app/tests-support/seed.php';
$_SERVER['HTTP_USER_AGENT'] = 'Harness/1.0';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$db = FakeDatabase::reset();
$db->install();
seed_admin($db);
require '/app/config.php';
`;

const TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

function polish(html) {
    return html
        .replace(/<link rel="manifest"[^>]*>\s*/g, '')
        .replace(/<link rel="apple-touch-icon"[^>]*>\s*/g, '')
        .replace(/<link rel="icon"[^>]*>\s*/g, '')
        .replace(/<link rel="stylesheet"[^>]*>/g, '<style>\n' + css + '\n</style>')
        .replace(/<script[^>]*>[\s\S]*?<\/script>\s*/g, '');
}

async function render(name, seed, script, get = {}) {
    const php = await bootApp(REPO);
    const json = JSON.stringify(get).replace(/'/g, "\\'");
    const code = BASE + seed + `$_GET = json_decode('${json}', true);\nrequire '/app/${script}';\n`;
    const result = await php.run(code);
    fs.writeFileSync(path.join(outDir, name + '.html'), polish(result.out));
    if (result.err.trim()) console.log(name + ' stderr: ' + result.err.slice(0, 300));
    console.log(name + ': ' + result.out.length + ' bytes');
}

const library = `
seed_videos($db); seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
`;

await render('home', 'seed_videos($db); seed_bundle($db);', 'index.php');
await render('home-buy-video', 'seed_videos($db); seed_bundle($db);', 'index.php', { buy: 'video:1' });
await render('home-buy-bundle', 'seed_videos($db); seed_bundle($db);', 'index.php', { buy: 'bundle:1' });

const buyer = `seed_videos($db); seed_bundle($db);
seed_payment($db);
$db->tables['payments'][0]['access_token'] = '${TOKEN}';
$_SESSION['access_token'] = '${TOKEN}';`;
await render('home-purchased', buyer, 'index.php');
await render('home-purchased-bundle', buyer, 'index.php', { buy: 'bundle:1' });
await render('library', library, 'success.php', { t: TOKEN });
await render('admin-videos', 'seed_videos($db); seed_bundle($db); seed_payment($db); fake_login();', 'admin/videos.php');
await render('admin-bundles', 'seed_videos($db); seed_bundle($db); fake_login();', 'admin/bundles.php');

console.log('\nOpen ' + path.join(outDir, 'home.html'));
