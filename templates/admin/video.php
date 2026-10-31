<?php
/** @var array<string, mixed>             $settings */
/** @var array<string, mixed>|null        $video */
/** @var array<string, mixed>             $form */
/** @var array<string, string>            $errors */
$currency = (string) $settings['currency'];
$isEdit = $video !== null;
$value = function ($key, $default = '') use ($form) {
    return isset($form[$key]) ? (string) $form[$key] : (string) $default;
};
$error = function ($key) use ($errors) {
    return isset($errors[$key]) ? $errors[$key] : '';
};
$preview = youtube_thumbnail($value('youtube_url', ''));
$embed = youtube_embed_url($value('youtube_url', ''));
?>
<a class="link small back-link" href="<?= e(url('admin/videos.php')) ?>"><?= icon('arrow-left', 15) ?> All videos</a>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert--danger"><?= icon('alert-octagon', 18) ?><span><?= e($errors['general']) ?></span></div>
<?php endif; ?>

<form method="post" class="settings-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="admin-grid">
        <section class="card card--pad">
            <header class="card__head">
                <div>
                    <h2><?= icon('youtube', 18) ?> <?= $isEdit ? 'Video details' : 'New video' ?></h2>
                    <p class="muted small">Paste an unlisted or public YouTube link — it is embedded privately on the buyer's page.</p>
                </div>
            </header>

            <div class="field">
                <label for="title">Title</label>
                <input class="input" id="title" name="title" type="text" maxlength="160" required
                       value="<?= e($value('title')) ?>" placeholder="e.g. Episode 1 — The Beginning">
                <?php if ($error('title')): ?><p class="field__error"><?= e($error('title')) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="youtube_url">YouTube link</label>
                <input class="input" id="youtube_url" name="youtube_url" type="text" required
                       value="<?= e($value('youtube_url')) ?>" placeholder="https://www.youtube.com/watch?v=VIDEO_ID"
                       data-youtube-input>
                <?php if ($error('youtube_url')): ?><p class="field__error"><?= e($error('youtube_url')) ?></p><?php endif; ?>
                <p class="hint">Works with youtube.com/watch, youtu.be and shorts links, or the 11-character video id.</p>
            </div>

            <div class="video-preview" data-youtube-preview <?= $preview === '' ? 'hidden' : '' ?>>
                <img src="<?= e($preview) ?>" alt="Video thumbnail" data-youtube-thumb>
                <span class="video-preview__badge"><?= icon('play', 13) ?> Preview</span>
            </div>

            <div class="field">
                <label for="description">Description <span class="tiny muted">(optional)</span></label>
                <textarea class="input" id="description" name="description" rows="3" maxlength="1000"
                          placeholder="One or two lines shown under the title."><?= e($value('description')) ?></textarea>
            </div>
        </section>

        <aside class="card card--pad">
            <header class="card__head">
                <h2><?= icon('wallet', 18) ?> Price &amp; visibility</h2>
            </header>

            <div class="field">
                <label for="price">Price (<?= e($currency) ?>)</label>
                <input class="input" id="price" name="price" type="number" min="1" max="500000" step="0.01" required
                       value="<?= e($value('price')) ?>">
                <?php if ($error('price')): ?><p class="field__error"><?= e($error('price')) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="position">Order in the library</label>
                <input class="input" id="position" name="position" type="number" min="0" max="9999" step="1"
                       value="<?= e($value('position', '0')) ?>">
                <?php if ($error('position')): ?><p class="field__error"><?= e($error('position')) ?></p><?php endif; ?>
                <p class="hint">Lower numbers appear first.</p>
            </div>

            <label class="switch">
                <input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>>
                <span class="switch__track"><span class="switch__thumb"></span></span>
                <span class="switch__label">On sale</span>
            </label>
            <p class="hint">Hidden videos stay in existing customers' libraries but disappear from the shop.</p>

            <?php if ($isEdit): ?>
                <dl class="detail-list" style="margin-top:16px">
                    <div><dt>Sold</dt><dd><?= (int) app()->catalog->videoSales((int) $video['id']) ?> time(s)</dd></div>
                    <div><dt>In bundles</dt><dd><?= (int) app()->catalog->videoBundleCount((int) $video['id']) ?></dd></div>
                </dl>
            <?php endif; ?>

            <div class="save-bar__actions" style="margin-top:18px">
                <button type="submit" class="btn btn--primary btn--block">
                    <?= icon('check', 16) ?> <?= $isEdit ? 'Save video' : 'Add video' ?>
                </button>
            </div>
        </aside>
    </div>
</form>

<?php if ($isEdit): ?>
    <?php if ($embed !== ''): ?>
        <p class="muted small"><?= icon('eye', 15) ?> Buyers see this video at <a href="<?= e(url('success.php?v=' . (int) $video['id'])) ?>" target="_blank" rel="noopener">success.php</a> after paying.</p>
    <?php endif; ?>

    <form method="post" class="inline-form" data-confirm="Delete this video? Buyers who already paid keep access.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn--danger btn--sm"><?= icon('trash', 15) ?> Delete video</button>
    </form>
<?php endif; ?>
