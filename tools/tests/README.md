# Developer test harness

Optional, **developer-only**. The application itself has no Node.js dependency —
it runs on plain PHP + MySQL. These scripts exist so the real controllers,
templates and SQL paths can be exercised in CI or locally *without* a MySQL
server, using [`php-wasm`](https://www.npmjs.com/package/php-wasm) to run PHP in
Node.

## Setup

```bash
cd tools/tests
npm install          # php-wasm (runs the PHP) + css-tree (parses the stylesheet)
```

## Commands

| Command | What it checks |
|---|---|
| `node lint.mjs` | Every `.php` file parses (`token_get_all(..., TOKEN_PARSE)`). |
| `node run-schema.mjs` | Schema v4 definition, drift detection, v3→v4 and v2→v4 upgrades, fresh install, `sql/schema.sql` in sync. |
| `node run-db.mjs` | Library logic: per-video single-sale eligibility (including backend product lookup), pricing, truthful bundle comparisons, entitlements, lifetime vs. limited access, payment search/export, and the "price always comes from the database" guarantees. |
| `node run-pages.mjs` | Boots the real entry scripts (`index.php`, `success.php`, `restore.php`, `admin/*.php`) against the fake DB and asserts on the rendered HTML — including **section order**, bundle-only storefront behavior and admin controls, the bundle-savings nudge, the buy-gate that keeps the checkout closed on every screen size (`#checkout.is-unpicked`, plus a CSS check that the rule is not phone-only) and real POSTs through the restore form (mobile number only; CSRF token injected with `csrf: true`). Writes `out/<scenario>.html`. |
| `node check-html.mjs` | On the pages in `out/`: no leaked PHP, balanced tags, and every CSS class exists in `assets/css/app.css` or the JS. |
| `node check-mobile.mjs` | Mobile/app-shell audit: the stylesheet parses and every declaration is valid, the phone rules actually win at 390 px and lose at 1280 px (a small cascade resolver), every page carries the device viewport + safe areas, the site has a 4-tab bottom bar and the admin a 5-tab one, and every admin table cell has the `data-label` its card layout needs. |
| `node run-tools.mjs` | Runs `php tools/build-schema.php` and copies the regenerated `sql/schema.sql` back. |
| `node preview.mjs` | Renders `home`, the bundle/video checkout states, the owned state, the buyer library, six admin screens and the sign-in page to `preview/*.html` with the CSS inlined, for a quick visual check. |
| `node preview-mobile.mjs` | Builds `preview/mobile.html`: every screen inside a 390 × 844 phone frame (`<iframe srcdoc>`), so the phone layout can be reviewed in a desktop browser without a device. |

`npm run all` runs lint → schema → db → pages → html → mobile.

Add a repository path as the last argument to point at another checkout, e.g.
`node run-pages.mjs /var/www/payaccess`.

## How it works

- `harness.mjs` copies the repository into PHP's virtual filesystem under
  `/app` and replaces `Database::fromConfig()` with `fake_db()` (the repository
  is never modified — the patch only exists inside the sandbox).
- `fakedb.php` is an in-memory `DatabaseInterface` implementation with a tiny SQL
  evaluator that understands exactly the statements the app issues. It
  deliberately **rejects mixed named/positional placeholders**, which is what
  produced the `SQLSTATE[HY093]` bug once.
- `seed.php` holds the fixtures: videos, a named bundle, a successful payment,
  an admin account and `fake_login()`.

Because the fake database is a test double, it is not proof that MySQL behaves
identically — run the manual checklist in the README (§16) against a real
database before going live.
