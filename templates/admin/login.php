<?php
/** @var array<string, mixed> $settings */
$logo = app()->settings->logoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in · <?= e($settings['site_name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style>:root{--brand: <?= e($settings['theme_color']) ?>}</style>
</head>
<body class="auth-body">
    <div class="auth-aurora" aria-hidden="true"></div>

    <main class="auth-card">
        <div class="auth-brand">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($settings['logo_text']) ?>" class="auth-brand__logo">
            <?php else: ?>
                <?= brand_mark(46) ?>
                <span><?= e($settings['logo_text']) ?></span>
            <?php endif; ?>
        </div>

        <h1>Welcome back</h1>
        <p class="muted">Sign in to manage payments and site settings.</p>

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

        <?php if (!empty($error)): ?>
            <div class="alert alert--danger">
                <?= icon('alert-octagon', 18) ?>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="form" autocomplete="on">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next ?? '') ?>">

            <div class="field">
                <label for="username">Username</label>
                <div class="field__control">
                    <?= icon('user', 18) ?>
                    <input id="username" name="username" type="text" autocomplete="username" required
                           value="<?= e(old('username')) ?>" <?= empty($error) ? 'autofocus' : '' ?>>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="field__control">
                    <?= icon('lock', 18) ?>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="field__reveal" data-toggle-password="#password" aria-label="Show password">
                        <?= icon('eye', 18) ?>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <?= icon('logout', 18) ?> Sign in
            </button>
        </form>

        <p class="auth-foot">
            <a href="<?= e(url('')) ?>"><?= icon('arrow-left', 15) ?> Back to site</a>
            <span class="muted small">Protected area · attempts are rate limited</span>
        </p>
    </main>
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
