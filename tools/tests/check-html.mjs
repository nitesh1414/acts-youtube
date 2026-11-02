/**
 * Static checks on the pages rendered by run-pages.mjs (./out/*.html):
 *  - no PHP tags or diagnostics leaked into the HTML
 *  - balanced block-level tags
 *  - every CSS class used exists in app.css (or is added by JavaScript)
 */
import fs from 'node:fs';
import path from 'node:path';
import { REPO } from './harness.mjs';

const outDir = path.join(process.cwd(), 'out');
if (!fs.existsSync(outDir)) {
    console.log('no out/ directory — run `node run-pages.mjs` first');
    process.exit(1);
}

const css = fs.readFileSync(path.join(REPO, 'assets/css/app.css'), 'utf8');
const js = fs.readFileSync(path.join(REPO, 'assets/js/app.js'), 'utf8')
    + fs.readFileSync(path.join(REPO, 'assets/js/admin.js'), 'utf8');

const ALLOWED = new Set([
    'is-selected', 'is-current', 'is-owned', 'is-muted', 'is-disabled', 'is-open', 'is-loading',
    'has-error', 'is-hidden', 'is-active', 'is-visible', 'is-fixed', 'is-copied', 'js-only',
]);
const TAGS = ['div', 'section', 'form', 'table', 'aside', 'article', 'header', 'footer', 'main', 'nav', 'ol', 'ul', 'dl'];

const files = fs.readdirSync(outDir).filter(f => f.endsWith('.html'));
let problems = 0;
const missingCss = new Map();

for (const file of files) {
    const html = fs.readFileSync(path.join(outDir, file), 'utf8');
    const issues = [];

    if (/<\?(php|=)/.test(html)) issues.push('PHP tag leaked');
    if (/Fatal error|Parse error|Warning: |Notice: |Deprecated: /i.test(html)) issues.push('PHP diagnostic leaked');

    for (const tag of TAGS) {
        const open = (html.match(new RegExp('<' + tag + '(?=[\\s>])', 'g')) || []).length;
        const close = (html.match(new RegExp('</' + tag + '>', 'g')) || []).length;
        if (open !== close) issues.push(`<${tag}> ${open} open / ${close} close`);
    }

    for (const match of html.matchAll(/class="([^"]*)"/g)) {
        for (const cls of match[1].split(/\s+/)) {
            if (!cls || cls.startsWith('js-') || ALLOWED.has(cls)) continue;
            const known = css.includes('.' + cls) || js.includes('.' + cls)
                || js.includes("'" + cls + "'") || js.includes('"' + cls + '"');
            if (!known && !missingCss.has(cls)) missingCss.set(cls, new Set());
            if (!known) missingCss.get(cls).add(file);
        }
    }

    if (issues.length) {
        problems++;
        console.log('FAIL ' + file + ': ' + issues.join('; '));
    }
}

if (missingCss.size) {
    problems++;
    console.log('FAIL classes without CSS (' + missingCss.size + '):');
    for (const [cls, where] of [...missingCss].sort()) {
        console.log('   .' + cls + '  (' + [...where].slice(0, 3).join(', ') + ')');
    }
}

console.log('\n' + files.length + ' pages checked, ' + problems + ' problem(s)');
process.exit(problems === 0 ? 0 : 1);
