/**
 * Build a self-contained phone preview: every important screen rendered at a
 * real 390×844 viewport inside a device frame, so the mobile layer (media
 * queries and all) can be reviewed in a desktop browser without a real phone.
 *
 * Each phone is an <iframe srcdoc="…"> — the CSS is already inlined by
 * preview.mjs, so nothing needs to load from disk.
 *
 *   node preview-mobile.mjs [output-file]      (default: ../../preview/mobile.html)
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import { REPO } from './harness.mjs';

const HERE = path.dirname(url.fileURLToPath(import.meta.url));
const outFile = process.argv[2]
    ? path.resolve(process.argv[2])
    : path.join(REPO, 'preview', 'mobile.html');

const source = path.join(REPO, 'preview');
if (!fs.existsSync(source)) {
    console.log('no preview/ renders yet — run `node preview.mjs` first');
    process.exit(1);
}

/** Screens to show, in review order. */
const screens = [
    ['home.html', 'Storefront', 'Bundles first, then videos, then checkout'],
    ['home-buy-video.html', 'Single video picked', 'Bundle nudge under the pay card'],
    ['home-purchased.html', 'Already bought', 'Owned state — no pay form'],
    ['library.html', 'Buyer library', 'Player, receipt and video shelf'],
    ['admin-dashboard.html', 'Admin · dashboard', 'Stats, chart and recent payments'],
    ['admin-videos.html', 'Admin · videos', 'Table becomes labelled cards'],
    ['admin-payments.html', 'Admin · payments', 'Filters, cards and paging'],
    ['admin-settings.html', 'Admin · settings', 'Long form with a sticky save bar'],
    ['login.html', 'Admin sign in', 'Full-bleed auth card'],
];

const esc = value => value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

const phones = [];
const missing = [];
for (const [file, title, note] of screens) {
    const full = path.join(source, file);
    if (!fs.existsSync(full)) { missing.push(file); continue; }
    let html = fs.readFileSync(full, 'utf8');
    // Inside the frame, tapping a link would try to reach the real site host.
    // Keep the preview self-contained by neutralising navigation.
    html = html.replace('</body>',
        '<script>document.addEventListener("click",function(e){if(e.target.closest("a[href]")){e.preventDefault();}},true);</script></body>');
    phones.push(`
        <figure class="device">
            <figcaption>
                <strong>${esc(title)}</strong>
                <span>${esc(note)}</span>
            </figcaption>
            <div class="phone">
                <div class="phone__notch" aria-hidden="true"></div>
                <iframe title="${esc(title)}" width="390" height="844" loading="lazy" srcdoc="${esc(html)}"></iframe>
                <div class="phone__home" aria-hidden="true"></div>
            </div>
        </figure>`);
}

const page = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PayAccess — phone preview (390 × 844)</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 34px 22px 60px;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        color: #e8eefb; background: #070b14;
        background-image:
            radial-gradient(50% 40% at 15% 0%, rgba(99, 102, 241, .22), transparent 70%),
            radial-gradient(40% 40% at 90% 30%, rgba(139, 92, 246, .16), transparent 70%);
    }
    header { max-width: 1280px; margin: 0 auto 30px; }
    h1 { margin: 0 0 6px; font-size: 1.5rem; letter-spacing: -.02em; }
    header p { margin: 0; color: #93a5c1; font-size: .9rem; }
    .grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(390px, 1fr));
        gap: 40px 28px; max-width: 1280px; margin: 0 auto; align-items: start;
    }
    figure.device { margin: 0; display: grid; gap: 12px; justify-items: center; }
    figcaption { display: grid; gap: 2px; text-align: center; }
    figcaption strong { font-size: .95rem; }
    figcaption span { font-size: .78rem; color: #93a5c1; }
    .phone {
        position: relative; width: 410px; padding: 10px;
        border-radius: 42px; background: #0b1224;
        box-shadow: 0 0 0 1px rgba(148, 163, 184, .22), 0 30px 70px rgba(2, 6, 18, .6);
    }
    .phone iframe {
        display: block; width: 390px; height: 844px; border: 0;
        border-radius: 32px; background: #070b14;
    }
    .phone__notch {
        position: absolute; top: 16px; left: 50%; transform: translateX(-50%);
        width: 108px; height: 22px; border-radius: 12px; background: #05080f; z-index: 2;
    }
    .phone__home {
        position: absolute; bottom: 17px; left: 50%; transform: translateX(-50%);
        width: 120px; height: 4px; border-radius: 3px; background: rgba(148, 163, 184, .45); z-index: 2;
    }
    footer { max-width: 1280px; margin: 44px auto 0; color: #63748f; font-size: .78rem; }
    @media (max-width: 460px) {
        body { padding: 20px 10px 40px; }
        .grid { grid-template-columns: 1fr; }
        .phone { width: min(410px, 96vw); }
        .phone iframe { width: 100%; height: 780px; }
    }
</style>
</head>
<body>
<header>
    <h1>Phone preview — 390 × 844</h1>
    <p>Every screen at a real phone viewport, so the mobile app layer is active (bottom tab bar,
       action bar, card tables). Scroll inside each phone.</p>
</header>
<main class="grid">
${phones.join('\n')}
</main>
<footer>
    Rendered from the fixtures in <code>tools/tests/seed.php</code> ·
    regenerate with <code>node preview.mjs &amp;&amp; node preview-mobile.mjs</code>.
</footer>
</body>
</html>
`;

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, page);
console.log('wrote ' + outFile + ' (' + Math.round(page.length / 1024) + ' KB, ' + phones.length + ' screens)');
if (missing.length) {
    console.log('missing renders (run preview.mjs): ' + missing.join(', '));
}
