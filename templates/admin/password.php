<?php
/** @var array<string, mixed>  $admin */
/** @var array<string, string> $errors */
?>
<div class="detail-grid detail-grid--narrow">
    <section class="card card--pad">
        <header class="card__head">
            <div>
                <h2><?= icon('key', 18) ?> Change your password</h2>
                <p class="muted small">Use at least 8 characters with letters and numbers.</p>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--danger">
                <?= icon('alert-octagon', 18) ?>
                <span><?= e($errors['general'] ?? 'Please check the form and try again.') ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="current_password">Current password</label>
                <div class="field__control">
                    <?= icon('lock', 17) ?>
                    <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    <button type="button" class="field__reveal" data-toggle-password="#current_password" aria-label="Show password"><?= icon('eye', 17) ?></button>
                </div>
            </div>
            <div class="field">
                <label for="new_password">New password</label>
                <div class="field__control">
                    <?= icon('key', 17) ?>
                    <input class="input" id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8">
                    <button type="button" class="field__reveal" data-toggle-password="#new_password" aria-label="Show password"><?= icon('eye', 17) ?></button>
                </div>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <div class="field__control">
                    <?= icon('check-circle', 17) ?>
                    <input class="input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn btn--primary"><?= icon('check', 17) ?> Update password</button>
        </form>
    </section>

    <aside class="card card--pad">
        <header class="card__head"><h2>Account</h2></header>
        <dl class="detail-list">
            <div><dt>Name</dt><dd><?= e($admin['name']) ?></dd></div>
            <div><dt>Username</dt><dd class="mono">@<?= e($admin['username']) ?></dd></div>
            <div><dt>E-mail</dt><dd><?= e($admin['email']) ?></dd></div>
            <div><dt>Last sign-in</dt><dd><?= e(date_display($admin['last_login_at'])) ?></dd></div>
            <div><dt>Last IP</dt><dd class="mono small"><?= e($admin['last_login_ip'] ?: '—') ?></dd></div>
        </dl>
        <p class="muted small">
            <?= icon('shield', 15) ?>
            Sessions expire automatically after 45 minutes of inactivity.
        </p>
        <a class="btn btn--ghost btn--block btn--sm" href="<?= e(url('admin/admins.php')) ?>"><?= icon('users', 15) ?> Manage admin users</a>
    </aside>
</div>
