<?php
/** @var array<string, mixed>  $settings */
/** @var array<string, mixed>  $form */
/** @var array<string, string> $errors */
$logo = app()->settings->logoUrl();
$webhookUrl = url('api/webhook.php');
$currency = (string) $settings['currency'];
$value = function ($key, $default = '') use ($form) {
    return isset($form[$key]) ? (string) $form[$key] : (string) $default;
};
$error = function ($key) use ($errors) {
    return isset($errors[$key]) ? $errors[$key] : '';
};
$secretSet = trim((string) $settings['razorpay_key_secret']) !== '';
$webhookSet = trim((string) $settings['razorpay_webhook_secret']) !== '';
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert--danger">
        <?= icon('alert-octagon', 18) ?>
        <span><?= e($errors['general'] ?? 'Please correct the highlighted fields and save again.') ?></span>
    </div>
<?php endif; ?>

<nav class="tabs" aria-label="Settings sections">
    <a href="#branding"><?= icon('image', 16) ?> Branding</a>
    <a href="#payments"><?= icon('credit-card', 16) ?> Payments</a>
    <a href="#content"><?= icon('youtube', 16) ?> Video</a>
    <a href="#access"><?= icon('lock', 16) ?> Access</a>
    <a href="#contact"><?= icon('mail', 16) ?> Support</a>
</nav>

<form method="post" enctype="multipart/form-data" class="settings-form" id="settings-form">
    <?= csrf_field() ?>

    <!-- Branding ---------------------------------------------------------- -->
    <section class="card card--pad" id="branding">
        <header class="card__head">
            <div>
                <h2><?= icon('image', 18) ?> Branding</h2>
                <p class="muted small">Your logo appears on the checkout page, the video page and this admin panel.</p>
            </div>
        </header>

        <div class="logo-upload">
            <div class="logo-upload__preview <?= $logo !== '' ? 'has-image' : '' ?>" data-logo-preview>
                <?php if ($logo !== ''): ?>
                    <img src="<?= e($logo) ?>" alt="Current logo" data-logo-image>
                <?php else: ?>
                    <?= brand_mark(64) ?>
                    <span class="tiny muted">No logo yet</span>
                <?php endif; ?>
            </div>

            <div class="logo-upload__body">
                <label class="dropzone" for="logo-input" data-dropzone>
                    <?= icon('upload', 26) ?>
                    <strong>Upload a new logo</strong>
                    <span>Drag &amp; drop an image here, or click to browse</span>
                    <span class="tiny muted">PNG, JPG, WEBP, GIF or ICO · max <?= e(format_bytes(LOGO_MAX_BYTES)) ?> · square PNG with transparent background works best (512 × 512)</span>
                    <input type="file" id="logo-input" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/x-icon,.ico" data-logo-input hidden>
                </label>

                <?php if ($error('logo')): ?>
                    <p class="field__error"><?= icon('alert', 14) ?> <?= e($error('logo')) ?></p>
                <?php endif; ?>

                <?php if ($logo !== ''): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="remove_logo" value="1">
                        <span>Remove the current logo and use the brand mark instead</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="logo_text">Logo text / brand name</label>
                <input class="input" id="logo_text" name="logo_text" value="<?= e($value('logo_text', $settings['logo_text'])) ?>" maxlength="120">
                <p class="hint">Shown when no logo image is uploaded.</p>
                <?php if ($error('logo_text')): ?><p class="field__error"><?= e($error('logo_text')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="site_name">Site name</label>
                <input class="input" id="site_name" name="site_name" value="<?= e($value('site_name', $settings['site_name'])) ?>" maxlength="120" required>
                <?php if ($error('site_name')): ?><p class="field__error"><?= e($error('site_name')) ?></p><?php endif; ?>
            </div>
            <div class="field field--full">
                <label for="tagline">Tagline</label>
                <input class="input" id="tagline" name="tagline" value="<?= e($value('tagline', $settings['tagline'])) ?>" maxlength="190">
                <p class="hint">One line under the headline, e.g. “Get instant access to our exclusive video”.</p>
                <?php if ($error('tagline')): ?><p class="field__error"><?= e($error('tagline')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="theme_color">Accent colour</label>
                <div class="color-input">
                    <input type="color" id="theme_color" name="theme_color" value="<?= e($value('theme_color', $settings['theme_color'])) ?>">
                    <span class="mono small"><?= e($value('theme_color', $settings['theme_color'])) ?></span>
                </div>
                <?php if ($error('theme_color')): ?><p class="field__error"><?= e($error('theme_color')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Payments ---------------------------------------------------------- -->
    <section class="card card--pad" id="payments">
        <header class="card__head">
            <div>
                <h2><?= icon('credit-card', 18) ?> Payments</h2>
                <p class="muted small">Razorpay credentials. Keys are stored in your database — keep this page private.</p>
            </div>
            <span class="badge <?= $secretSet ? 'badge--success' : 'badge--warning' ?>"><?= $secretSet ? 'Configured' : 'Not configured' ?></span>
        </header>

        <div class="form-grid">
            <div class="field">
                <label for="amount">Price (<?= e($currency) ?>)</label>
                <div class="input-prefix">
                    <span><?= e(currency_symbol($currency)) ?></span>
                    <input class="input" id="amount" name="amount" type="number" min="1" max="500000" step="0.01"
                           value="<?= e($value('amount', $settings['amount'])) ?>" required>
                </div>
                <?php if ($error('amount')): ?><p class="field__error"><?= e($error('amount')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="demo_mode">Demo mode</label>
                <label class="switch">
                    <input type="checkbox" id="demo_mode" name="demo_mode" value="1" <?= $value('demo_mode', (string) $settings['demo_mode']) === '1' ? 'checked' : '' ?>>
                    <span class="switch__track"><span class="switch__thumb"></span></span>
                    <span class="switch__label">Simulate payments (no money charged)</span>
                </label>
                <p class="hint">Useful for testing. Turn it off before going live.</p>
            </div>

            <div class="field">
                <label for="razorpay_key_id">Razorpay Key ID</label>
                <input class="input mono" id="razorpay_key_id" name="razorpay_key_id" value="<?= e($value('razorpay_key_id', $settings['razorpay_key_id'])) ?>" maxlength="120" placeholder="rzp_live_xxxxxxxxxxxx">
                <?php if ($error('razorpay_key_id')): ?><p class="field__error"><?= e($error('razorpay_key_id')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="razorpay_key_secret">Razorpay Key Secret</label>
                <div class="field__control">
                    <?= icon('key', 17) ?>
                    <input class="input" id="razorpay_key_secret" name="razorpay_key_secret" type="password" autocomplete="new-password"
                           placeholder="<?= $secretSet ? '•••••••••• (leave blank to keep current)' : 'Enter your key secret' ?>" maxlength="190">
                    <button type="button" class="field__reveal" data-toggle-password="#razorpay_key_secret" aria-label="Show secret"><?= icon('eye', 17) ?></button>
                </div>
                <p class="hint">Leave blank to keep the saved secret.</p>
                <?php if ($error('razorpay_key_secret')): ?><p class="field__error"><?= e($error('razorpay_key_secret')) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="razorpay_webhook_secret">Webhook secret <span class="muted small">(optional)</span></label>
                <div class="field__control">
                    <?= icon('shield', 17) ?>
                    <input class="input" id="razorpay_webhook_secret" name="razorpay_webhook_secret" type="password" autocomplete="new-password"
                           placeholder="<?= $webhookSet ? '•••••••••• (leave blank to keep current)' : 'Webhook secret from Razorpay' ?>" maxlength="190">
                    <button type="button" class="field__reveal" data-toggle-password="#razorpay_webhook_secret" aria-label="Show secret"><?= icon('eye', 17) ?></button>
                </div>
                <p class="hint">Recommended. Payments are confirmed even if the buyer closes the tab.</p>
                <?php if ($error('razorpay_webhook_secret')): ?><p class="field__error"><?= e($error('razorpay_webhook_secret')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label>Webhook URL</label>
                <div class="copy-row">
                    <input class="input mono small" type="text" readonly value="<?= e($webhookUrl) ?>" data-select-on-focus aria-label="Razorpay webhook URL">
                    <button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($webhookUrl) ?>"><?= icon('copy', 15) ?></button>
                </div>
                <p class="hint">In Razorpay → Settings → Webhooks, add this URL and subscribe to <code>payment.captured</code> and <code>payment.failed</code>.</p>
            </div>
        </div>
    </section>

    <!-- Video ------------------------------------------------------------- -->
    <section class="card card--pad" id="content">
        <header class="card__head">
            <div>
                <h2><?= icon('youtube', 18) ?> Video content</h2>
                <p class="muted small">Use an <strong>unlisted</strong> YouTube video so only paying customers can watch it.</p>
            </div>
        </header>

        <div class="form-grid">
            <div class="field field--full">
                <label for="youtube_video_url">YouTube video link</label>
                <input class="input" id="youtube_video_url" name="youtube_video_url" value="<?= e($value('youtube_video_url', $settings['youtube_video_url'])) ?>"
                       placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX" data-youtube-input>
                <p class="hint">Accepts watch, youtu.be, shorts, live and embed links.</p>
                <?php if ($error('youtube_video_url')): ?><p class="field__error"><?= e($error('youtube_video_url')) ?></p><?php endif; ?>
                <div class="video-preview" data-youtube-preview <?= youtube_thumbnail($settings['youtube_video_url']) === '' ? 'hidden' : '' ?>>
                    <img src="<?= e(youtube_thumbnail($settings['youtube_video_url'])) ?>" alt="Video thumbnail" data-youtube-thumb>
                    <span class="video-preview__badge"><?= icon('play', 14) ?> Preview</span>
                </div>
            </div>

            <div class="field field--full">
                <label for="youtube_channel_url">YouTube channel link <span class="muted small">(optional)</span></label>
                <input class="input" id="youtube_channel_url" name="youtube_channel_url" value="<?= e($value('youtube_channel_url', $settings['youtube_channel_url'])) ?>"
                       placeholder="https://www.youtube.com/@yourchannel">
                <p class="hint">Used for the “Subscribe” buttons after payment and on the home page.</p>
                <?php if ($error('youtube_channel_url')): ?><p class="field__error"><?= e($error('youtube_channel_url')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Access ------------------------------------------------------------ -->
    <section class="card card--pad" id="access">
        <header class="card__head">
            <div>
                <h2><?= icon('lock', 18) ?> Access</h2>
                <p class="muted small">How long a buyer keeps access to the video after paying.</p>
            </div>
        </header>

        <div class="form-grid">
            <div class="field">
                <label for="access_days">Access duration (days)</label>
                <input class="input" id="access_days" name="access_days" type="number" min="1" max="3650" step="1"
                       value="<?= e($value('access_days', (string) $settings['access_days'])) ?>" required>
                <p class="hint">Buyers can restore their link within this window using e-mail + mobile.</p>
                <?php if ($error('access_days')): ?><p class="field__error"><?= e($error('access_days')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Support ----------------------------------------------------------- -->
    <section class="card card--pad" id="contact">
        <header class="card__head">
            <div>
                <h2><?= icon('mail', 18) ?> Support details</h2>
                <p class="muted small">Shown in the footer and on the access-restore page.</p>
            </div>
        </header>

        <div class="form-grid">
            <div class="field">
                <label for="support_email">Support e-mail</label>
                <input class="input" id="support_email" name="support_email" type="email"
                       value="<?= e($value('support_email', $settings['support_email'])) ?>" placeholder="support@example.com" maxlength="190">
                <?php if ($error('support_email')): ?><p class="field__error"><?= e($error('support_email')) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="support_phone">Support phone <span class="muted small">(optional)</span></label>
                <input class="input" id="support_phone" name="support_phone" type="tel"
                       value="<?= e($value('support_phone', $settings['support_phone'])) ?>" placeholder="98765 43210" maxlength="30">
                <?php if ($error('support_phone')): ?><p class="field__error"><?= e($error('support_phone')) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="save-bar">
        <span class="muted small"><?= icon('info', 15) ?> Changes apply immediately.</span>
        <div class="save-bar__actions">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><?= icon('external-link', 15) ?> Preview site</a>
            <button type="submit" class="btn btn--primary"><?= icon('check', 17) ?> Save settings</button>
        </div>
    </div>
</form>
