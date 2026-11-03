<?php
/** @var array<string, mixed> $settings */
/** @var string $content */
$brandUrl = url('');
$logo = app() ? app()->settings->logoUrl() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title><?= e($pageTitle) ?></title>
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="<?= e($settings['theme_color']) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex, nofollow">
    <link rel="manifest" href="<?= e(url('manifest.php')) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/icon-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root{--brand: <?= e($settings['theme_color']) ?>}</style>
</head>
<body class="site-body has-tabbar <?= e($bodyClass ?? '') ?>">
    <div class="site-aurora" aria-hidden="true"></div>

    <header class="site-header">
        <div class="wrap site-header__inner">
            <a class="brand" href="<?= e($brandUrl) ?>" aria-label="<?= e($settings['site_name']) ?> home">
                <?php if ($logo !== ''): ?>
                    <img class="brand__logo" src="<?= e($logo) ?>" alt="<?= e($settings['logo_text']) ?>">
                <?php else: ?>
                    <?= brand_mark(40) ?>
                    <span class="brand__name"><?= e($settings['logo_text']) ?></span>
                <?php endif; ?>
            </a>

            <nav class="site-nav" aria-label="Main navigation">
                <a href="<?= e(url('')) ?>">Home</a>
                <?php if (current_access_payment()): ?>
                    <a href="<?= e(url('success.php')) ?>">My library</a>
                <?php else: ?>
                    <a href="<?= e(url('restore.php')) ?>">Restore access</a>
                <?php endif; ?>
                <a href="#faq">FAQ</a>
            </nav>

            <?php // Compact header action: only shown on phones, where the text nav is hidden. ?>
            <div class="site-header__actions">
                <?php if (current_access_payment()): ?>
                    <a class="icon-btn" href="<?= e(url('success.php')) ?>" aria-label="My library">
                        <?= icon('eye', 19) ?>
                    </a>
                <?php else: ?>
                    <a class="icon-btn" href="<?= e(url('restore.php')) ?>" aria-label="Restore access">
                        <?= icon('refresh', 19) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="site-main">
        <?php if (!empty($flashes)): ?>
            <div class="wrap flash-stack" role="status" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert--<?= e($flash['type']) ?>">
                        <?= icon($flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-octagon' : 'info'), 18) ?>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="wrap site-footer__inner">
            <div>
                <strong><?= e($settings['site_name']) ?></strong>
                <span class="muted">· <?= date('Y') ?> · Payments secured by Razorpay</span>
            </div>
            <div class="site-footer__links">
                <?php if (!empty($settings['support_email'])): ?>
                    <a href="mailto:<?= e($settings['support_email']) ?>"><?= icon('mail', 16) ?> <?= e($settings['support_email']) ?></a>
                <?php endif; ?>
                <?php if (!empty($settings['support_phone'])): ?>
                    <a href="tel:<?= e(normalize_mobile($settings['support_phone'])) ?>"><?= icon('phone', 16) ?> <?= e(display_mobile($settings['support_phone'])) ?></a>
                <?php endif; ?>
                <a href="<?= e(url('restore.php')) ?>"><?= icon('refresh', 16) ?> Restore access</a>
            </div>
        </div>
    </footer>

    <?php
    // App-style bottom navigation for phones (hidden from 761px upwards).
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : 'index.php');
    $tabHome = $script === 'index.php' || $script === '' ;
    $hasAccess = current_access_payment() !== null;
    $tabs = [
        [
            'href'   => url(''),
            'label'  => 'Home',
            'icon'   => 'home',
            'active' => $tabHome,
            'anchor' => 'top',
        ],
        [
            'href'   => url('') . '#library',
            'label'  => 'Videos',
            'icon'   => 'play',
            'active' => false,
            'anchor' => 'library',
        ],
        [
            'href'   => url('') . '#bundles',
            'label'  => 'Bundles',
            'icon'   => 'tag',
            'active' => false,
            'anchor' => 'bundles',
        ],
        [
            'href'   => $hasAccess ? url('success.php') : url('restore.php'),
            'label'  => $hasAccess ? 'Library' : 'Restore',
            'icon'   => $hasAccess ? 'eye' : 'refresh',
            'active' => $hasAccess
                ? in_array($script, ['success.php', 'locked.php'], true)
                : in_array($script, ['restore.php', 'locked.php'], true),
            'anchor' => '',
        ],
    ];
    ?>
    <nav class="tabbar" aria-label="Quick navigation" data-tabbar>
        <?php foreach ($tabs as $tab): ?>
            <a class="tabbar__item<?= $tab['active'] ? ' is-active' : '' ?>"
               href="<?= e($tab['href']) ?>"
               data-tab-anchor="<?= e($tab['anchor']) ?>"
               <?= $tab['active'] ? 'aria-current="page"' : '' ?>>
                <span class="tabbar__icon"><?= icon($tab['icon'], 20) ?></span>
                <span class="tabbar__label"><?= e($tab['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (app()->settings->isRazorpayConfigured()): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
    <?php endif; ?>
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
