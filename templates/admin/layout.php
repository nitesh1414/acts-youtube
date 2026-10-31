<?php
/** @var array<string, mixed>      $settings */
/** @var array<string, mixed>|null $admin */
/** @var string                    $content */
$current = isset($activeNav) && $activeNav !== '' ? (string) $activeNav : basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'index.php');
$logo = app()->settings->logoUrl();
$nav = [
    ['file' => 'index.php',    'label' => 'Dashboard',    'icon' => 'grid'],
    ['file' => 'videos.php',   'label' => 'Videos',       'icon' => 'youtube'],
    ['file' => 'bundles.php',  'label' => 'Bundles',      'icon' => 'tag'],
    ['file' => 'payments.php', 'label' => 'Payments',     'icon' => 'credit-card'],
    ['file' => 'settings.php', 'label' => 'Site settings','icon' => 'sliders'],
    ['file' => 'admins.php',   'label' => 'Admin users',  'icon' => 'users'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e($settings['site_name']) ?> Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root{--brand: <?= e($settings['theme_color']) ?>}</style>
</head>
<body class="admin-body">
<div class="admin-shell">
    <div class="admin-overlay" data-sidebar-close hidden></div>

    <aside class="sidebar" id="admin-sidebar">
        <div class="sidebar__brand">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($settings['logo_text']) ?>" class="sidebar__logo">
            <?php else: ?>
                <?= brand_mark(36) ?>
            <?php endif; ?>
            <div class="sidebar__brand-text">
                <strong><?= e($settings['site_name']) ?></strong>
                <span>Admin panel</span>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Admin navigation">
            <?php foreach ($nav as $item): ?>
                <a class="sidebar__link <?= $current === $item['file'] ? 'is-active' : '' ?>"
                   href="<?= e(url('admin/' . $item['file'])) ?>">
                    <?= icon($item['icon'], 18) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <span class="sidebar__divider">Account</span>
            <a class="sidebar__link <?= $current === 'password.php' ? 'is-active' : '' ?>" href="<?= e(url('admin/password.php')) ?>">
                <?= icon('key', 18) ?><span>Change password</span>
            </a>
            <a class="sidebar__link" href="<?= e(url('')) ?>" target="_blank" rel="noopener">
                <?= icon('external-link', 18) ?><span>View site</span>
            </a>
        </nav>

        <div class="sidebar__foot">
            <?php if ($admin): ?>
                <div class="sidebar__user">
                    <span class="avatar avatar--sm"><?= e(initials($admin['name'])) ?></span>
                    <div>
                        <strong><?= e($admin['name']) ?></strong>
                        <span>@<?= e($admin['username']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--block btn--sm">
                    <?= icon('logout', 16) ?> Sign out
                </button>
            </form>
            <p class="sidebar__version">v<?= e(APP_VERSION) ?></p>
        </div>
    </aside>

    <div class="admin-main">
        <header class="topbar">
            <button type="button" class="icon-btn topbar__burger" data-sidebar-toggle aria-label="Open menu" aria-controls="admin-sidebar">
                <?= icon('menu', 20) ?>
            </button>
            <div class="topbar__titles">
                <span class="topbar__eyebrow">Admin</span>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar__actions">
                <?php if (!empty($toolbar)): ?>
                    <?= $toolbar ?>
                <?php endif; ?>
                <?php if ($admin): ?>
                    <a class="topbar__avatar" href="<?= e(url('admin/password.php')) ?>" title="<?= e($admin['name']) ?>">
                        <?= e(initials($admin['name'])) ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="admin-content">
            <?php if (!empty($flashes)): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert--<?= e($flash['type']) ?>">
                            <?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-octagon' : 'info'), 18) ?>
                            <span><?= e($flash['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>

            <footer class="admin-footer">
                <span><?= e($settings['site_name']) ?> · PayAccess v<?= e(APP_VERSION) ?></span>
                <span><?= e(date('d M Y')) ?></span>
            </footer>
        </main>
    </div>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
