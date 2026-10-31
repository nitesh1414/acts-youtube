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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="<?= e($settings['theme_color']) ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="manifest" href="<?= e(url('manifest.php')) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/icon-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root{--brand: <?= e($settings['theme_color']) ?>}</style>
</head>
<body class="site-body <?= e($bodyClass ?? '') ?>">
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
                <a class="site-nav__admin" href="<?= e(url('admin/login.php')) ?>" title="Admin panel">
                    <?= icon('lock', 16) ?><span>Admin</span>
                </a>
            </nav>
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
                <a href="<?= e(url('admin/login.php')) ?>"><?= icon('lock', 16) ?> Admin</a>
            </div>
        </div>
    </footer>

    <?php if (app()->settings->isRazorpayConfigured()): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
    <?php endif; ?>
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
