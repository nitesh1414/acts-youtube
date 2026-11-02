/**
 * Mobile / app-shell audit over the pages rendered by run-pages.mjs.
 *
 * There is no browser in the harness, so this checks the things that make a
 * phone layout work *structurally*:
 *   - every page opts into the device width and the notch safe areas
 *   - the site has a 4-item bottom tab bar with exactly one active tab
 *   - the admin has a 5-item bottom bar plus a drawer with a close button
 *   - every admin table cell carries the label its mobile card needs
 *   - the stylesheet really contains the phone rules (tab bar, action bar,
 *     card tables, 16px inputs, sticky save bar …)
 *
 *   node check-mobile.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import * as csstree from 'css-tree';
import { REPO } from './harness.mjs';

const outDir = path.join(process.cwd(), 'out');
if (!fs.existsSync(outDir)) {
    console.log('no out/ directory — run `node run-pages.mjs` first');
    process.exit(1);
}

const css = fs.readFileSync(path.join(REPO, 'assets/css/app.css'), 'utf8');
const appJs = fs.readFileSync(path.join(REPO, 'assets/js/app.js'), 'utf8');
const adminJs = fs.readFileSync(path.join(REPO, 'assets/js/admin.js'), 'utf8');

const problems = [];
const fail = (page, message) => problems.push(page + ': ' + message);

const count = (haystack, needle) => haystack.split(needle).length - 1;

// ---------------------------------------------------------------------------
// Stylesheet: it must parse, and every declaration must be a real CSS value
// ---------------------------------------------------------------------------
{
    const syntaxErrors = [];
    let ast;
    try {
        ast = csstree.parse(css, { positions: true, onParseError: e => syntaxErrors.push(e.message) });
    } catch (error) {
        syntaxErrors.push(error.message);
    }
    for (const message of syntaxErrors.slice(0, 5)) {
        problems.push('app.css: syntax error — ' + message);
    }

    if (ast) {
        const invalid = [];
        csstree.walk(ast, {
            visit: 'Declaration',
            enter(node) {
                const property = node.property;
                if (property.startsWith('--') || property.startsWith('-webkit') || property.startsWith('-moz')) {
                    return;
                }
                const value = csstree.generate(node.value);
                if (value.includes('var(')) {
                    return; // the lexer cannot resolve custom properties
                }
                const match = csstree.lexer.matchProperty(property, node.value);
                if (match.error) {
                    const line = node.loc && node.loc.start ? node.loc.start.line : '?';
                    invalid.push(line + ': ' + property + ': ' + value);
                }
            }
        });
        for (const entry of invalid.slice(0, 5)) {
            problems.push('app.css: invalid value at line ' + entry);
        }
    }
}

// ---------------------------------------------------------------------------
// Cascade: at a phone width the phone rules must actually win, and at desktop
// width the app chrome must disappear. No browser needed — this walks the
// stylesheet in order, honours max-width/min-width media queries and applies
// specificity the same way the browser would for these single-element probes.
// ---------------------------------------------------------------------------
function mediaMatches(prelude, width) {
    if (!prelude) return true;
    const conditions = prelude.split(',').map(part => part.trim());
    return conditions.some(condition => {
        if (/prefers-|orientation|resolution|print|hover/.test(condition)) {
            // Not something we can evaluate at a width; ignore the block.
            return false;
        }
        const min = condition.match(/min-width:\s*(\d+(?:\.\d+)?)px/);
        const max = condition.match(/max-width:\s*(\d+(?:\.\d+)?)px/);
        if (!min && !max) return true;
        if (min && width < Number(min[1])) return false;
        if (max && width > Number(max[1])) return false;
        return true;
    });
}

function specificity(selector) {
    const body = selector.replace(/::?[a-z-]+(\([^)]*\))?/gi, '');
    const ids = (body.match(/#[\w-]+/g) || []).length;
    const classes = (body.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) || []).length;
    const elements = (body.match(/(^|[\s>+~])[a-z][\w-]*/gi) || []).length;
    return ids * 10000 + classes * 100 + elements;
}

/** [{ selectors, property, value, order, weight }] in document order. */
const declarations = [];
{
    const ast = csstree.parse(css);
    let order = 0;
    const walkRules = (node, media) => {
        csstree.walk(node, {
            visit: 'Rule',
            enter(rule, item, list) {
                const prelude = rule.prelude ? csstree.generate(rule.prelude) : '';
                const block = rule.block;
                const selectors = prelude.split(',').map(s => s.trim()).filter(Boolean);
                if (block && block.children) {
                    block.children.forEach(decl => {
                        if (decl.type !== 'Declaration') return;
                        selectors.forEach(selector => {
                            declarations.push({
                                selector: selector.replace(/\s+/g, ' '),
                                property: decl.property,
                                value: csstree.generate(decl.value),
                                order: order++,
                                weight: specificity(selector),
                                media
                            });
                        });
                    });
                }
                // Nested rules inside this rule (e.g. @media handled below).
                return csstree.walk.skip;
            }
        });
    };

    ast.children.forEach(node => {
        if (node.type === 'Rule') {
            const selectors = csstree.generate(node.prelude).split(',').map(s => s.trim());
            node.block.children.forEach(decl => {
                if (decl.type !== 'Declaration') return;
                selectors.forEach(selector => declarations.push({
                    selector: selector.replace(/\s+/g, ' '),
                    property: decl.property,
                    value: csstree.generate(decl.value),
                    order: order++,
                    weight: specificity(selector),
                    media: ''
                }));
            });
        } else if (node.type === 'Atrule' && node.name === 'media') {
            const media = csstree.generate(node.prelude);
            node.block.children.forEach(child => {
                if (child.type !== 'Rule') return;
                const selectors = csstree.generate(child.prelude).split(',').map(s => s.trim());
                child.block.children.forEach(decl => {
                    if (decl.type !== 'Declaration') return;
                    selectors.forEach(selector => declarations.push({
                        selector: selector.replace(/\s+/g, ' '),
                        property: decl.property,
                        value: csstree.generate(decl.value),
                        order: order++,
                        weight: specificity(selector),
                        media
                    }));
                });
            });
        }
    });
}

/** Final value of `property` for an element matched by any of `selectors`. */
function resolved(selectors, property, width) {
    let winner = null;
    for (const decl of declarations) {
        if (!selectors.includes(decl.selector)) continue;
        if (decl.property !== property) continue;
        if (!mediaMatches(decl.media, width)) continue;
        if (!winner || decl.weight > winner.weight || (decl.weight === winner.weight && decl.order > winner.order)) {
            winner = decl;
        }
    }
    return winner ? winner.value : null;
}

const PHONE = 390;
const DESKTOP = 1280;

const CASCADE = [
    // [label, selectors, property, width, expected-substring]
    ['phone shows the tab bar', ['.tabbar'], 'display', PHONE, 'grid'],
    ['desktop hides the tab bar', ['.tabbar'], 'display', DESKTOP, 'none'],
    ['phone hides the text nav', ['.site-nav'], 'display', PHONE, 'none'],
    ['desktop shows the text nav', ['.site-nav'], 'display', DESKTOP, 'flex'],
    ['phone shows the action bar', ['.buy-bar'], 'display', PHONE, 'flex'],
    ['desktop hides the action bar', ['.buy-bar'], 'display', DESKTOP, 'none'],
    ['phone shows header shortcuts', ['.site-header__actions'], 'display', PHONE, 'inline-flex'],
    ['desktop hides header shortcuts', ['.site-header__actions'], 'display', DESKTOP, 'none'],
    ['phone reserves space for the tab bar', ['.has-tabbar .site-main'], 'padding-bottom', PHONE, 'var(--tabbar-space)'],
    ['phone hides table headers', ['.data-table thead'], 'display', PHONE, 'none'],
    ['phone cards every table row', ['.data-table tbody td'], 'display', PHONE, 'flex'],
    ['table cell labels come from data-label', ['.data-table tbody td::before'], 'content', PHONE, 'attr(data-label)'],
    ['phone stats are two columns', ['.stat-grid'], 'grid-template-columns', PHONE, 'repeat(2'],
    ['desktop stats are four columns', ['.stat-grid'], 'grid-template-columns', DESKTOP, 'repeat(4'],
    ['phone bundle rows stack', ['.bundle-row'], 'grid-template-columns', PHONE, '1fr'],
    ['phone filters are a grid', ['.filters__form'], 'display', PHONE, 'grid'],
    ['phone save bar is sticky', ['.save-bar'], 'position', PHONE, 'sticky'],
    ['phone footer stacks', ['.site-footer__inner'], 'flex-direction', PHONE, 'column'],
    ['phone library is two-up', ['.library__grid'], 'grid-template-columns', PHONE, 'repeat(2'],
    ['phone keeps the admin sidebar off-canvas', ['.sidebar'], 'transform', PHONE, 'translateX(-100%)'],
    ['desktop pins the admin sidebar', ['.admin-main'], 'margin-left', DESKTOP, '264px'],
    ['phone shows the admin tab bar', ['.admin-tabbar'], 'display', PHONE, 'grid'],
    ['desktop hides the admin tab bar', ['.admin-tabbar'], 'display', DESKTOP, 'none'],
    ['phone shows the drawer close button', ['.sidebar__close'], 'display', PHONE, 'grid'],
];

for (const [label, selectors, property, width, expected] of CASCADE) {
    const value = resolved(selectors, property, width);
    if (value === null) {
        problems.push('cascade: no value for ' + label + ' (' + selectors[0] + ' { ' + property + ' })');
    } else if (!value.includes(expected)) {
        problems.push('cascade: ' + label + ' — expected ' + property + ' to include "' + expected
            + '", got "' + value + '" at ' + width + 'px');
    }
}

// ---------------------------------------------------------------------------
// Stylesheet: the phone layer must exist and cover the app shell
// ---------------------------------------------------------------------------
const REQUIRED_CSS = [
    ['mobile layer marker', 'MOBILE APP LAYER'],
    ['tab bar hidden by default', '.tabbar,\n.admin-tabbar,\n.buy-bar,'],
    ['tab bar layout', '.tabbar {'],
    ['tab bar active state', '.tabbar__item.is-active'],
    ['admin tab bar', '.admin-tabbar {'],
    ['action bar', '.buy-bar {'],
    ['safe-area variables', '--safe-bottom: env(safe-area-inset-bottom'],
    ['tab bar reserved space', 'padding-bottom: calc(var(--tabbar-space)'],
    ['table cards label', 'content: attr(data-label)'],
    ['table cells stacked', '.data-table tbody td {'],
    ['primary cell style', 'td.cell-main {'],
    ['16px inputs (no iOS zoom)', '.filters__form .input { font-size: 16px; }'],
    ['sticky save bar above tab bar', 'bottom: calc(var(--tabbar-h) + var(--safe-bottom) + 8px)'],
    ['two-column phone stats', '.stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr));'],
    ['phone hero stats grid', '.hero__stats {'],
    ['phone library grid', '.library__grid { grid-template-columns: repeat(2'],
    ['phone auth card', '.auth-card { padding: 24px 20px'],
    ['drawer close button', '.sidebar__close {'],
    ['filter form grid', '.filters__form {'],
    ['landscape tab bar', 'orientation: landscape'],
    ['small-phone tier', '@media (max-width: 380px)'],
];
for (const [label, needle] of REQUIRED_CSS) {
    if (!css.includes(needle)) {
        problems.push('app.css: missing ' + label + ' (' + needle.slice(0, 46) + ')');
    }
}

// Tablet/laptop layout must not be swallowed by the phone layer.
if (!css.includes('@media (max-width: 1000px)')) {
    problems.push('app.css: tablet breakpoint missing');
}
// Accessibility guard rails that must survive every redesign.
if (!css.includes('@media (prefers-reduced-motion: reduce)')) {
    problems.push('app.css: reduced-motion block missing');
}
if (!css.includes(':focus-visible')) {
    problems.push('app.css: no :focus-visible styles');
}

// ---------------------------------------------------------------------------
// Scripts: the chrome needs behaviour, not just markup
// ---------------------------------------------------------------------------
const REQUIRED_JS = [
    ['tab bar scroll spy', 'data-tab-anchor'],
    ['empty sections hide their tab', 'item.hidden = true'],
    ['action bar hides at checkout', '[data-buy-bar]'],
    ['action bar price sync', "getElementById('buy-bar-price')"],
    ['drawer close buttons', "[data-sidebar-close]"],
    ['drawer toggles', "[data-sidebar-toggle]"],
];
for (const [label, needle] of REQUIRED_JS) {
    if (!appJs.includes(needle) && !adminJs.includes(needle)) {
        problems.push('apps scripts: missing ' + label + ' (' + needle + ')');
    }
}

// ---------------------------------------------------------------------------
// Rendered pages
// ---------------------------------------------------------------------------
const files = fs.readdirSync(outDir).filter(f => f.endsWith('.html'));

let audited = 0;
let skipped = 0;
for (const file of files) {
    const html = fs.readFileSync(path.join(outDir, file), 'utf8');
    // Redirecting POSTs render only a meta-refresh stub — nothing to audit.
    if (!html.includes('<html') || !html.includes('</body>')) {
        skipped++;
        continue;
    }
    audited++;
    const isAdmin = html.includes('class="admin-body');
    const isAuth = html.includes('class="auth-body');

    // 1. device width + notch handling
    const viewport = (html.match(/<meta name="viewport" content="([^"]*)"/) || [])[1] || '';
    if (!viewport) {
        fail(file, 'no viewport meta');
    } else {
        if (!viewport.includes('width=device-width')) fail(file, 'viewport is not device-width');
        if (!viewport.includes('viewport-fit=cover')) fail(file, 'viewport misses viewport-fit=cover');
    }

    // 2. site shell: bottom tab bar
    if (!isAdmin && !isAuth) {
        if (!html.includes('class="site-body has-tabbar')) fail(file, 'site body is not marked has-tabbar');
        if (!html.includes('data-tabbar')) fail(file, 'no bottom tab bar');
        if (count(html, 'class="tabbar__item') !== 4) {
            fail(file, 'expected 4 tab bar items, found ' + count(html, 'class="tabbar__item'));
        }
        if (count(html, 'tabbar__icon') < 4 || count(html, 'tabbar__label') < 4) {
            fail(file, 'tab bar items need an icon and a label');
        }
        if (count(html, 'tabbar__item is-active') > 1) fail(file, 'more than one active tab');
        if (!html.includes('class="site-header__actions"')) fail(file, 'no compact header actions');
    }

    // 3. admin shell: bottom bar + drawer close button
    if (isAdmin) {
        if (!html.includes('data-admin-tabbar')) fail(file, 'no admin bottom tab bar');
        if (count(html, 'admin-tabbar__item') !== 5) {
            fail(file, 'expected 5 admin tabs, found ' + count(html, 'admin-tabbar__item'));
        }
        if (!html.includes('class="sidebar__close"')) fail(file, 'drawer has no close button');
        if (!html.includes('data-sidebar-close')) fail(file, 'drawer overlay is not wired to close');
        if (count(html, 'admin-tabbar__item is-active') > 1) fail(file, 'more than one active admin tab');
    }

    // 4. admin tables: every cell needs a mobile label
    if (isAdmin) {
        const tables = html.split('<table class="data-table"').slice(1);
        for (const table of tables) {
            const cells = table.split('<td').slice(1);
            for (const cell of cells) {
                const opening = cell.slice(0, cell.indexOf('>'));
                const labelled = opening.includes('data-label=')
                    || opening.includes('cell-main')
                    || opening.includes('cell-actions');
                if (!labelled) {
                    fail(file, 'table cell without data-label / cell-main / cell-actions: <td' + opening.slice(0, 60));
                    break;
                }
            }
        }
    }
}

// The storefront action bar belongs on the shop, not on empty or owned pages.
const home = fs.readFileSync(path.join(outDir, 'home-library.html'), 'utf8');
if (!home.includes('data-buy-bar')) fail('home-library.html', 'no buy bar above the tab bar');
if (!home.includes('id="buy-bar-price"')) fail('home-library.html', 'buy bar has no price slot');

const owned = fs.readFileSync(path.join(outDir, 'home-bundle-purchased.html'), 'utf8');
if (owned.includes('data-buy-bar')) fail('home-bundle-purchased.html', 'buy bar shown for an owned product');

// ---------------------------------------------------------------------------

console.log('\n' + audited + ' pages audited'
    + (skipped ? ' (' + skipped + ' redirect stub(s) skipped)' : '')
    + ', ' + problems.length + ' problem(s)');
for (const problem of problems) {
    console.log('   ✗ ' + problem);
}
process.exit(problems.length === 0 ? 0 : 1);
