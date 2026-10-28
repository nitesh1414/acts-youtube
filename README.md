# PayAccess — Core PHP + MySQL Paywall for Exclusive YouTube Videos

A complete, production-ready pay-per-view app in **Core PHP (no framework) and MySQL**.
Visitors pay through **Razorpay** and unlock a private (unlisted) YouTube video; the
admin panel manages payments, branding, pricing and the video link.

Built for shared hosting (cPanel / XAMPP / WAMP / LAMP / any PHP 7.4+ host) — no Composer,
no build step, no Node.js required.

---

## Table of contents

1. [What you get](#1-what-you-get)
2. [Quick start (5 minutes)](#2-quick-start-5-minutes)
3. [Requirements](#3-requirements)
4. [The admin panel](#4-the-admin-panel)
5. [Uploading the logo (replaces the old "Logo URL" field)](#5-uploading-the-logo-replaces-the-old-logo-url-field)
6. [Razorpay setup + webhooks](#6-razorpay-setup--webhooks)
7. [How the money + access flow works](#7-how-the-money--access-flow-works)
8. [Security model](#8-security-model)
9. [File structure](#9-file-structure)
10. [Database schema](#10-database-schema)
11. [Configuration reference](#11-configuration-reference)
12. [Nginx configuration](#12-nginx-configuration)
13. [Customising the design](#13-customising-the-design)
14. [Troubleshooting](#14-troubleshooting)
15. [Upgrading from v1 / the database updater](#15-upgrading-from-v1--the-database-updater)
16. [Manual QA checklist](#16-manual-qa-checklist)

---

## 1. What you get

**Public site**

- Conversion-focused landing page: price, benefits, locked video preview, FAQ, "how it works".
- Checkout form (name, e-mail, mobile) with inline validation and a Razorpay popup.
- Private video page that only opens after a verified payment (embedded via
  `youtube-nocookie.com`, autoplay, subscribe CTA, receipt with payment IDs).
- **Access restore**: buyers who clear cookies or switch devices recover their link with
  the e-mail + mobile number used at checkout (rate limited).
- **Demo mode**: test the complete payment flow with no Razorpay account and no money.
- Installable PWA (dynamic manifest, offline fallback page, service worker).

**Admin panel** (`/admin/`)

- Dashboard: revenue KPIs (today / 7 days / 30 days / all time), payment counts,
  conversion rate, active access passes, 14-day revenue chart, recent payments.
- Payments: search (name, e-mail, mobile, order id, payment id), status + date filters,
  sortable columns, pagination, CSV export, per-payment detail view with audit timeline.
- **Logo uploader** (drag & drop, validated) — replaces the old "Logo URL" text field.
- Site settings: branding (logo, logo text, site name, tagline, accent colour), price,
  access duration, Razorpay keys, webhook secret + copyable webhook URL, YouTube video
  and channel links, support e-mail/phone, demo mode toggle.
- Admin users: multiple accounts, bcrypt hashes, add / enable / disable / delete,
  change-own-password. Throttled login with session fingerprinting and idle timeout.

**Engineering**

- Zero dependencies: no Composer, no npm, no CDN framework or icon font. All icons are
  inline SVG, all CSS/JS is local, all PWA icons ship in the repo.
- PDO prepared statements everywhere, output escaping helper (`e()`), CSRF on every
  state-changing request, security headers + Content-Security-Policy, rate limiting,
  upload validation (real MIME detection, random filenames, no execution in `/uploads`).

---

## 2. Quick start (5 minutes)

1. **Upload** all files to your document root (e.g. `public_html/`, or `htdocs/payaccess/`).
2. **Create the database** (optional — the installer can do it) and make sure you have the
   MySQL user + password.
3. **Run the installer**: open `https://yourdomain.com/install.php`.
   - It checks server requirements (`pdo_mysql`, upload folder permissions, …).
   - It writes `config.local.php`, creates all tables and your first admin account
     (choose a strong password).
   - It prints next steps and locks itself (`install.lock`).
4. **Delete `install.php`** from the server.
5. **Sign in** at `/admin/login.php` and complete:
   - *Site settings → Payments*: Razorpay Key ID + Key Secret (and webhook secret).
   - *Site settings → Branding*: upload your logo, set site name/colour.
   - *Site settings → Video content*: paste your unlisted YouTube link.
6. **Test**: enable *Demo mode*, buy on the public page, confirm the video unlocks,
   then turn demo mode off and do one real ₹1 test payment.

> Prefer manual setup? Copy `config.example.php` → `config.local.php`, import
> `sql/schema.sql` into an empty database, then open the site. Create the first admin
> account by running the installer (recommended) — it hashes the password for you.

---

## 3. Requirements

| Requirement | Notes |
|---|---|
| PHP **7.4+** (8.1–8.4 supported) | `pdo_mysql` required |
| MySQL **5.7+** / MariaDB **10.3+** | utf8mb4 tables |
| Extensions | `pdo_mysql`, `json`, `session`, and either `curl` **or** `allow_url_fopen`; `fileinfo` **or** `getimagesize` for logo validation; `mbstring` recommended |
| Writable folders | `/uploads` (logo storage), `/` (only during installation, to write `config.local.php`), `/storage/logs` |
| Web server | Apache with `mod_rewrite` (a `.htaccess` ships) or Nginx (see [§12](#12-nginx-configuration)) |
| HTTPS | Required by Razorpay for live payments and by the service worker |

`upload_max_filesize` / `post_max_size` must be at least 2 MB for the logo uploader
(the app enforces a 2 MB limit itself).

---

## 4. The admin panel

| Page | URL | What it does |
|---|---|---|
| Sign in | `/admin/login.php` | Throttled login (5 tries / 10 min per user+IP, 25 per IP) |
| Dashboard | `/admin/index.php` | KPIs, revenue chart, store status, recent payments |
| Payments | `/admin/payments.php` | Search, filters, sorting, pagination, CSV export |
| Payment detail | `/admin/payment.php?id=…` | Full record, audit timeline, regenerate access link |
| Site settings | `/admin/settings.php` | Branding (logo upload), payments, video, access, support |
| Admin users | `/admin/admins.php` | Add / enable / disable / delete admin accounts |
| Change password | `/admin/password.php` | Update your own password |
| CSV export | `/admin/export.php` | Filtered export, formula-injection safe, Excel-friendly |

Sessions: fingerprint-bound, 45-minute idle timeout, regenerated on login.
Passwords: bcrypt (`password_hash`), rehashed automatically when PHP's default changes.
At least one active admin always remains (the last one cannot be disabled or deleted).

---

## 5. Uploading the logo (replaces the old "Logo URL" field)

**Where:** Admin → *Site settings* → **Branding** → *Upload a new logo*.

**How it behaves**

- Click the drop zone or drag an image onto it; the preview updates instantly.
- Files are validated server-side:
  - Real MIME type detected with `finfo` (fallback `getimagesize`) — the file extension
    supplied by the browser is ignored, and the saved extension comes from the detected type.
  - Allowed: **PNG, JPEG, GIF, WEBP, ICO**. SVG is intentionally rejected (it can carry scripts).
  - Maximum 2 MB (`LOGO_MAX_BYTES` in `config.php`) and 6000 px per side.
  - Random file name (`logo_20250101-120000_a1b2c3d4.png`), written to `/uploads/logo/`.
- Saving replaces the old file automatically; ticking *"Remove the current logo"* deletes it
  and falls back to the built-in brand mark.
- When no logo is uploaded, the app renders its own inline SVG mark plus the **Logo text**.

**Storage & safety**

- `/uploads/.htaccess` disables PHP/CGI execution and serves `X-Content-Type-Options: nosniff`
  with a sandboxing CSP, so an uploaded file can never be executed even if the MIME checks
  were bypassed.
- The database stores a relative path (`uploads/logo/logo_….png`) — moving the site to another
  domain does not break the logo.
- The uploaded logo also becomes the PWA/app icon (`/manifest.php`) and is shown in the admin
  sidebar and login screen.

**To change the limit**, edit `config.php`:

```php
define('LOGO_MAX_BYTES', 4 * 1024 * 1024); // 4 MB
```

---

## 6. Razorpay setup + webhooks

1. Create an account at <https://dashboard.razorpay.com> and finish KYC.
2. *Settings → API Keys → Generate key*; copy the **Key ID** (`rzp_live_…`) and **Key Secret**.
3. Paste both into *Admin → Site settings → Payments* and save.
4. (Recommended) *Settings → Webhooks → Add new webhook*:
   - **URL**: copy it from the settings page — `https://yourdomain.com/api/webhook.php`
   - **Secret**: any long random string; paste the same value into *Webhook secret*.
   - **Active events**: `payment.captured`, `payment.failed`, `order.paid`.
5. Start with test keys (`rzp_test_…`) and Razorpay's test cards, then switch to live keys.

**Why webhooks matter:** the browser confirms the payment right after checkout, but if the
buyer closes the tab too early the webhook still marks the payment as paid and issues access.
Both paths verify a signature (`HMAC-SHA256`) before touching the database.

---

## 7. How the money + access flow works

```
Visitor                App                                  Razorpay
   │  name/email/mobile │                                      │
   ├───────────────────►│ POST /api/create_order.php           │
   │                    │  • validate input, rate limit        │
   │                    │  • INSERT payment (status=pending)   │
   │                    │  • create order ────────────────────►│
   │                    │◄──────────── order_id ───────────────│
   │◄──── order ────────│                                      │
   │  checkout popup ──────────────────────────────────────────►│
   │◄── order_id|payment_id|signature ─────────────────────────│
   ├───────────────────►│ POST /api/verify_payment.php         │
   │                    │  • HMAC signature check             │
   │                    │  • fetch payment (amount/status) ───►│
   │                    │  • status=success, paid_at, token    │
   │◄─ redirect ────────│                                      │
   │  /success.php?t=…  │  access token valid for N days       │
   │                    │  + HttpOnly cookie for the device    │
```

- Amounts and the video link live only on the server; the browser never decides the price.
- `success.php` refuses to render the video without a **valid, unexpired, successful** payment
  token — the page itself is not enough (this is the biggest difference from v1).
- Buyers can recover access at `/restore.php` with the same e-mail + mobile (6 tries / 15 min).
- Every step is written to `payment_events` and shown in the admin timeline.

---

## 8. Security model

| Area | Measure |
|---|---|
| SQL injection | PDO prepared statements; identifiers whitelisted (`Payments::SORTABLE`) |
| XSS | Every dynamic value passes through `e()` (`htmlspecialchars`), strict CSP, no `innerHTML` with server data |
| CSRF | Per-session token on all forms + `X-CSRF-Token` on JSON calls (`csrf_verify()`) |
| Admin auth | bcrypt, throttled logins, session regeneration, UA fingerprint, 45-min idle timeout |
| Paid content | Access requires a 64-char token tied to a successful payment and an expiry date |
| File uploads | MIME sniffing, size/dimension limits, random names, upload folder cannot execute code |
| Webhooks | `HMAC-SHA256` of the raw body against your webhook secret |
| Payments | Signature + API lookup + amount/order match before access is granted |
| Headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP |
| Rate limits | Checkout (10/10 min per IP), access restore (6/15 min), admin login (5 per user+IP) |
| Secrets | Razorpay secrets never re-rendered into HTML; logs exclude passwords and card data |
| Enumeration | No public list of payments; tokens are 64 hex chars of `random_bytes` |

**Operational advice**

- Serve the site over HTTPS (Razorpay requires it; cookies use `Secure` automatically).
- Delete `install.php` after setup and keep `config.local.php` out of version control
  (both are in `.gitignore` and blocked by the shipped `.htaccess`).
- Rotate the Razorpay keys and webhook secret if a device is lost.
- `storage/logs/app-YYYY-MM-DD.log` records warnings, failed logins and payment events
  (no card data). Review it when something "doesn't work".
- Set `APP_DEBUG` to `false` in production (it is the default).

---

## 9. File structure

```
├── index.php                 Landing page (price + checkout form)
├── success.php               Private video page (token protected)
├── restore.php               Access recovery (e-mail + mobile)
├── demo_pay.php              Simulated checkout (demo mode only)
├── manifest.php              Dynamic PWA manifest (uses the uploaded logo)
├── sw.js                     Service worker (offline fallback)
├── offline.html              Offline page
├── install.php               Installer — delete after use
├── update.php                Database updater (adds missing tables/columns) — delete after use
├── config.php                Bootstrap + defaults (rarely edited)
├── config.example.php        Template for config.local.php (manual installs)
├── config.local.php          Your credentials (created by the installer, git-ignored)
│
├── lib/                      Application code (never web-accessible)
│   ├── bootstrap.php         Errors, headers, session, DB connection
│   ├── app.php               Service container (db, settings, auth, payments)
│   ├── db.php                PDO wrapper (query/all/first/value/insert/update/tx)
│   ├── schema.php            Schema definition + verifier + additive migrator
│   ├── helpers.php           Escaping, URLs, money, dates, validation, logging
│   ├── csrf.php              CSRF tokens
│   ├── auth.php              Admin accounts, login throttling, password policy
│   ├── settings.php          Settings model + validation + save (logo included)
│   ├── uploads.php           ImageUploader (MIME sniffing, safe storage)
│   ├── payments.php          Payment repository, filters, stats, audit events
│   ├── razorpay.php          Razorpay Orders/Payments client + signature helpers
│   ├── youtube.php           Video id / embed / thumbnail helpers
│   ├── access.php            Buyer access tokens + cookie
│   ├── throttle.php          Database-backed rate limiter
│   ├── icons.php             Inline SVG icon set + brand mark
│   └── view.php              Tiny view layer (templates + layout + abort())
│
├── templates/
│   ├── site/                 layout, home, success, locked, restore, demo-checkout, error
│   └── admin/                layout, login, dashboard, payments, payment, settings, admins, password
│
├── admin/                    index · payments · payment · settings · admins · password · export · login · logout
├── api/                      create_order · verify_payment · webhook
├── assets/
│   ├── css/app.css           Design system (site + admin, light/dark, responsive, print)
│   ├── js/app.js             Checkout, copy buttons, PWA registration
│   ├── js/admin.js           Sidebar, uploads preview, YouTube preview, confirms
│   └── img/                  App icons + favicon
├── uploads/logo/             Uploaded logos (writable, protected)
├── storage/logs/             Application log
├── sql/schema.sql            Full schema + default row (generated — see note below)
├── tools/build-schema.php    Regenerates sql/schema.sql from lib/schema.php (dev only)
└── .htaccess                 Apache hardening (deny lib/sql/templates/storage, headers, caching)
```

---

## 10. Database schema

| Table | Purpose |
|---|---|
| `settings` | Single row (`id = 1`): branding, price, access days, Razorpay keys, video link, demo mode |
| `admin_users` | Admin accounts (`password_hash`, `is_active`, last login metadata) |
| `payments` | One row per checkout: customer, amount, status, Razorpay ids, access token + expiry, IP/UA |
| `payment_events` | Audit trail (order created, payment captured, webhook, access granted/regenerated) |
| `rate_events` | Rate-limit buckets (admin login, checkout, access restore); pruned automatically |

Statuses: `pending` → `success` | `failed`. Access is granted only for `success` rows with a
future `access_expires_at`.

The schema is defined **once**, in `lib/schema.php`. The app compares the live database against
that definition on every boot (cached in `storage/schema-*.ok`), and `update.php` brings an older
database up to date by only *adding* tables, columns and indexes. `sql/schema.sql` is a generated
copy of the same definition — after editing `Schema::tables()`, regenerate it with:

```bash
php tools/build-schema.php
```

---

## 11. Configuration reference

`config.php` holds safe defaults; `config.local.php` (written by the installer) overrides them:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'pay_access_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_URL', '');          // empty = auto-detect (recommended)
define('APP_DEBUG', false);      // true only while debugging locally
define('APP_TIMEZONE', 'Asia/Kolkata');
// define('LOGO_MAX_BYTES', 2 * 1024 * 1024);
// define('ENABLE_CSP', false);  // only if a gateway script is blocked
```

Everything customer-facing (price, logo, video, keys, access days, colours) is edited in the
admin panel and stored in MySQL — no file editing needed after installation.

---

## 12. Nginx configuration

```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;
    root /var/www/payaccess;
    index index.php;

    location ~ /\.            { deny all; }
    location ~ ^/(lib|sql|templates|storage|tools)/ { deny all; }
    location ~ ^/(config(\.local|\.example)?\.php|install\.lock)$ { deny all; }

    location ^~ /uploads/ {
        location ~ \.php$ { deny all; }
        add_header X-Content-Type-Options nosniff;
        expires 30d;
    }

    location ~ \.(css|js|png|jpg|jpeg|webp|gif|ico|svg)$ {
        expires 30d;
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

PHP-FPM pool: ensure `session.save_path` is writable. If sessions are unavailable the app
still renders, but admin sign-in is disabled with an explanatory message (by design).

---

## 13. Customising the design

- **Colours**: set the accent colour in *Site settings → Branding*; it drives `--brand` on
  every page. The rest of the palette is CSS custom properties at the top of
  `assets/css/app.css` (`.site-body` = dark public theme, `.admin-body` = light admin theme).
- **Copy**: `templates/site/home.php` (hero, benefits, FAQ), `templates/site/success.php`,
  `templates/site/locked.php`, `templates/site/restore.php`.
- **Layout**: `templates/site/layout.php` and `templates/admin/layout.php`.
- **Icons**: inline SVG set in `lib/icons.php` (`icon('name', size)`); add new paths there.
- **Emails**: this build shows receipts on screen and supports printing the payment detail
  page; hook your mailer into `api/verify_payment.php` after `grantAccess()` if you need
  e-mail delivery.

---

## 14. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Setup required" page | Database not reachable or tables missing → run `install.php`, check `config.local.php` |
| **"Database update required" page** | The database was created by v1 or an interrupted install. Open `update.php`, press **Run the update**, then delete the file |
| **Checkout fails: "The server returned an unexpected response"** | The API returned a non-JSON error (usually a database column/table missing). Open `update.php`; details are in `storage/logs`. Run the updater even if you already ran `install.php` |
| API returns `{"error":…,"code":"database_error","ref":"ABC123"}` | The message names the cause; `ref` finds the statement in `storage/logs`. Most map to "open `update.php`", permission errors point at `config.local.php` |
| **`"Column 'x' cannot be null"` / `"Field 'x' doesn't have a default value"`** | Your tables came from v1, where columns were `NOT NULL`. Press **Run the update** in `update.php` — it makes those columns optional without touching data |
| Still failing after `update.php` | Open `update.php` → **Test the payment write**. It performs the whole checkout write (INSERT → audit row → order-id UPDATE) in a transaction, rolls it back and prints the exact MySQL error, plus the last log lines to copy |
| `SQLSTATE HY093` / "MySQL error unknown" | A query mixed `:named` and `?` placeholders — fixed in 2.0.0; make sure every file (especially `lib/db.php`) came from the same package version |
| Admin sign-in says sessions are unavailable | `session.save_path` not writable → fix PHP config (the app tries the temp dir first) |
| Pay button disabled / "Payments are not configured" | Razorpay Key ID or Secret empty in *Site settings → Payments* |
| Razorpay popup does not open | `checkout.razorpay.com` blocked (ad-blocker, CSP, no internet) — check the browser console |
| Payment succeeds but no access | Webhook secret mismatch, or amount/order mismatch — check `storage/logs` and *Payment detail → timeline* |
| Logo upload rejected | Not a PNG/JPEG/GIF/WEBP/ICO, larger than 2 MB, or over 6000 px; check folder permissions |
| Upload folder errors | `chmod 755 uploads uploads/logo` (or `775` when PHP runs as another user) |
| `install.lock` blocks reinstalling | Delete `install.lock` (and `install.php` afterwards) |
| PHP warnings/notices printed at the top of a page | `display_errors` is `On` (default in XAMPP). Set `display_errors = Off` in `php.ini`; the app already logs everything to `storage/logs` |
| Buyer lost the link | *Payments → open payment → copy access link*, or send them `/restore.php` |
| Demo mode left on | *Site settings → Payments → Demo mode off* — otherwise visitors get free access |

---

## 15. Upgrading from v1 / the database updater

Version 2 replaced the `logo` URL column with an uploaded file stored in `logo_path`, and added
`admin_users`, `payment_events` and `rate_events`. Fresh installs get everything from
`install.php`.

### The easy path (recommended)

1. Upload the new files over the old ones — keep `config.local.php` and `uploads/`.
2. Open **`https://your-site/update.php`** in a browser.
   * It lists exactly what is missing (tables, columns, indexes, settings row).
   * Press **Run the update**. Everything is additive: no table, column or row is ever dropped,
     and old payments/settings are copied into the new columns where it makes sense
     (`logo_text` → `site_name`, the old `logo` URL → `logo_path`, `youtube_link` →
     `youtube_video_url`).
   * A v1 database has no admin accounts, so the same page then asks you to create the first one.
3. Delete `update.php` (and `install.php`) once the site works.

Besides adding what is missing, the updater also **relaxes `NOT NULL` constraints that v2
cannot honour** — v1 required `payments.order_id` up front, while v2 inserts the pending row
before Razorpay is called. Without that step every checkout fails with *"Column 'order_id'
cannot be null"*. Widening a column to `NULL` keeps all existing rows.

Running the updater twice is harmless — the second run reports *"Everything is up to date"*.
A **Diagnose checkout** panel on the same page replays the whole checkout write — the pending
`payments` row, the `payment_events` audit entry and the `UPDATE` that stores the Razorpay order
id — inside a transaction, then rolls it back. If any step fails it prints the exact MySQL error
with a reference code, and the latest log lines are shown in a copyable box. An **Environment check**
lists the PHP/MySQL versions, the PDO driver, the Razorpay HTTPS client and whether `uploads/`
and `storage/` are writable — the usual reasons a local XAMPP install still misbehaves.

> If the site shows **"Database update required"** instead of a page, that *is* the updater asking
> to be run. It is shown whenever the database is behind, so a half-finished install or a
> restored backup can never turn into a checkout 500.

### Why the updater matters for payments

`api/create_order.php` writes to the `payments` table. If a column such as `currency` is missing,
MySQL rejects the INSERT, the API used to answer with an HTML error page, and the browser showed
*"The server returned an unexpected response."* The updater fixes the root cause; the API now also
answers with a JSON `database_error` message that names the problem and links here.

### Manual SQL (only if you cannot run update.php)

```sql
ALTER TABLE settings
    ADD COLUMN site_name VARCHAR(120) NOT NULL DEFAULT 'PayAccess',
    ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN tagline VARCHAR(190) NOT NULL DEFAULT 'Get instant access to our exclusive video',
    ADD COLUMN razorpay_webhook_secret VARCHAR(190) DEFAULT NULL,
    ADD COLUMN youtube_channel_url VARCHAR(255) DEFAULT NULL,
    ADD COLUMN support_email VARCHAR(190) DEFAULT NULL,
    ADD COLUMN support_phone VARCHAR(30) DEFAULT NULL,
    ADD COLUMN access_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ADD COLUMN demo_mode TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN theme_color CHAR(7) NOT NULL DEFAULT '#4f46e5';

UPDATE settings SET site_name = logo_text WHERE site_name = 'PayAccess' AND logo_text <> 'PayAccess';

ALTER TABLE payments
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR',
    ADD COLUMN signature VARCHAR(191) DEFAULT NULL,
    ADD COLUMN method VARCHAR(32) DEFAULT NULL,
    ADD COLUMN access_token CHAR(64) DEFAULT NULL,
    ADD COLUMN access_expires_at DATETIME DEFAULT NULL,
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL,
    ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL,
    ADD COLUMN notes VARCHAR(255) DEFAULT NULL,
    ADD COLUMN paid_at DATETIME DEFAULT NULL,
    ADD COLUMN updated_at DATETIME DEFAULT NULL;
```

Then create `admin_users`, `payment_events` and `rate_events` from `sql/schema.sql`, and create an
admin with `update.php` (or let `install.php` add one). Old rows have no access token — open
*Payments → payment → Regenerate access link* for customers who need access, or let them use
`/restore.php`.

> **Keeping the old logo:** a v1 `logo` URL keeps working (it is carried into `logo_path` and
> rendered as a remote image). Upload a file in *Site settings → Branding* when convenient.

---

## 16. Manual QA checklist

Because payments touch money, verify these after any change:

1. **Landing** — price, logo, video preview, mobile layout (375 px), demo-mode banner.
2. **Validation** — empty form, bad e-mail, 3-digit mobile → inline errors, no order created.
3. **Payment (test keys)** — successful payment → redirect to the video page → video plays.
4. **Signature** — tamper with `razorpay_signature` (DevTools) → API returns an error, no access.
5. **Locked page** — open `/success.php` in a private window → "This video is locked".
6. **Restore** — clear cookies, visit `/restore.php`, enter the same e-mail + mobile →
   access restored; wrong mobile → generic error; 7 attempts → rate-limit message.
7. **Admin** — wrong password 5× → throttled; sign in → dashboard numbers match the payments.
8. **Filters** — search by partial e-mail, filter by status and date range, sort each column,
   paginate, export CSV and open it in Excel.
9. **Logo upload** — PNG under 2 MB replaces the logo everywhere (site, admin, manifest);
   `.php` renamed to `.png` is rejected; 3 MB file is rejected; remove checkbox restores the mark.
10. **Webhook** — Razorpay dashboard "Send test webhook" → 200 response, event in the timeline.
11. **Demo mode** — on → simulated checkout works; off → button/endpoint refuse without keys.
12. **Upgrade path** — restore a v1 dump into a test database, load the site → "Database update
    required", open `update.php` → items listed → Run the update → old payment still visible in
    *Admin → Payments*, old logo still shown → checkout completes. Run `update.php` again →
    "Everything is up to date".

---

Built with Core PHP + MySQL · Razorpay · no frameworks, no build step.
