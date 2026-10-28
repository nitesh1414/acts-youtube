<?php
/** @var array<string, mixed>             $admin */
/** @var array<int, array<string, mixed>> $admins */
/** @var array<string, string>            $errors */
?>
<div class="detail-grid">
    <section class="card">
        <header class="card__head card__head--pad">
            <div>
                <h2><?= icon('users', 18) ?> Admin accounts</h2>
                <p class="muted small"><?= count($admins) ?> account<?= count($admins) === 1 ? '' : 's' ?> · passwords are stored as bcrypt hashes</p>
            </div>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="avatar"><?= e(initials($row['name'])) ?></span>
                                    <div>
                                        <strong><?= e($row['name']) ?><?= (int) $row['id'] === (int) $admin['id'] ? ' <span class="tiny muted">(you)</span>' : '' ?></strong>
                                        <span class="muted small"><?= e($row['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="mono small">@<?= e($row['username']) ?></td>
                            <td>
                                <span class="badge <?= (int) $row['is_active'] ? 'badge--success' : 'badge--muted' ?>">
                                    <?= (int) $row['is_active'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td class="muted small"><?= e(date_display($row['last_login_at'], 'Never')) ?></td>
                            <td class="right">
                                <div class="row-actions">
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="active" value="<?= (int) $row['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn--ghost btn--xs" <?= (int) $row['id'] === (int) $admin['id'] ? 'disabled title="You cannot disable your own account"' : '' ?>>
                                            <?= (int) $row['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="inline-form" data-confirm="Delete this admin account? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn--danger btn--xs" <?= (int) $row['id'] === (int) $admin['id'] ? 'disabled title="You cannot delete your own account"' : '' ?>>
                                            <?= icon('trash', 14) ?> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="card card--pad">
        <header class="card__head">
            <h2><?= icon('user', 18) ?> Add an admin</h2>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--danger">
                <?= icon('alert-octagon', 18) ?>
                <span><?= e($errors['general'] ?? 'Please check the form.') ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label for="username">Username</label>
                <input class="input" id="username" name="username" required minlength="3" maxlength="32" autocomplete="off"
                       value="<?= e(old('username')) ?>" placeholder="e.g. nitesh">
            </div>
            <div class="field">
                <label for="name">Full name</label>
                <input class="input" id="name" name="name" required maxlength="100" value="<?= e(old('name')) ?>">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input class="input" id="email" name="email" type="email" required maxlength="190" value="<?= e(old('email')) ?>">
            </div>
            <div class="field">
                <label for="password">Temporary password</label>
                <div class="field__control">
                    <?= icon('key', 17) ?>
                    <input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="field__reveal" data-toggle-password="#password" aria-label="Show password"><?= icon('eye', 17) ?></button>
                </div>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm password</label>
                <input class="input" id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn--primary btn--block"><?= icon('check', 17) ?> Create admin</button>
            <p class="tiny muted">Share the password securely and ask them to change it after signing in.</p>
        </form>
    </aside>
</div>
