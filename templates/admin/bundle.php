<?php
/** @var array<string, mixed>             $settings */
/** @var array<string, mixed>|null        $bundle */
/** @var array<string, mixed>             $form */
/** @var array<string, string>            $errors */
/** @var array<int, array<string, mixed>> $videos */
/** @var array<int, bool>                 $chosen */
$currency = (string) $settings['currency'];
$isEdit = $bundle !== null;
$value = function ($key, $default = '') use ($form) {
    return isset($form[$key]) ? (string) $form[$key] : (string) $default;
};
$error = function ($key) use ($errors) {
    return isset($errors[$key]) ? $errors[$key] : '';
};
$prices = [];
foreach ($videos as $video) {
    $prices[(int) $video['id']] = (int) round((float) $video['price'] * 100);
}
?>
<a class="link small back-link" href="<?= e(url('admin/bundles.php')) ?>"><?= icon('arrow-left', 15) ?> All bundles</a>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert--danger"><?= icon('alert-octagon', 18) ?><span><?= e($errors['general']) ?></span></div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="admin-grid">
        <section class="card card--pad">
            <header class="card__head">
                <div>
                    <h2><?= icon('tag', 18) ?> Bundle details</h2>
                    <p class="muted small">The name below is shown to visitors on the home page and on their receipt.</p>
                </div>
            </header>

            <div class="field">
                <label for="name">Bundle name</label>
                <input class="input" id="name" name="name" type="text" maxlength="160" required
                       value="<?= e($value('name')) ?>" placeholder="e.g. Season 1 — Complete Series">
                <?php if ($error('name')): ?><p class="field__error"><?= e($error('name')) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Description <span class="tiny muted">(optional)</span></label>
                <textarea class="input" id="description" name="description" rows="3" maxlength="1000"
                          placeholder="What does the buyer get?"><?= e($value('description')) ?></textarea>
            </div>

            <div class="field">
                <label>Videos in this bundle</label>
                <?php if ($error('video_ids')): ?><p class="field__error"><?= e($error('video_ids')) ?></p><?php endif; ?>
                <?php if (empty($videos)): ?>
                    <div class="alert alert--info">
                        <?= icon('info', 18) ?>
                        <span>There are no videos yet. <a href="<?= e(url('admin/video.php')) ?>">Add one first</a>.</span>
                    </div>
                <?php else: ?>
                    <p class="hint">For each video, choose whether buyers may also purchase it on its own. This setting applies everywhere that video appears, not only in this bundle.</p>
                    <div class="picker" data-prices="<?= e(json_encode($prices)) ?>">
                        <?php foreach ($videos as $video): ?>
                            <?php
                            $videoId = (int) $video['id'];
                            $allowSingle = array_key_exists($videoId, $form['allow_single_purchase'])
                                ? !empty($form['allow_single_purchase'][$videoId])
                                : !empty($video['allow_single_purchase']);
                            ?>
                            <div class="picker__item <?= !$video['is_active'] ? 'is-muted' : '' ?>">
                                <label class="picker__select" for="bundle-video-<?= $videoId ?>">
                                    <input id="bundle-video-<?= $videoId ?>" type="checkbox" name="video_ids[]" value="<?= $videoId ?>"
                                        <?= isset($chosen[$videoId]) ? 'checked' : '' ?>>
                                    <?php if ($video['thumbnail'] !== ''): ?>
                                        <img src="<?= e($video['thumbnail']) ?>" alt="" loading="lazy" width="80" height="45">
                                    <?php else: ?>
                                        <span class="picker__thumb"><?= icon('youtube', 16) ?></span>
                                    <?php endif; ?>
                                    <span class="picker__text">
                                        <strong><?= e($video['title']) ?></strong>
                                        <span class="muted tiny">
                                            <?= e(money((float) $video['price'], $currency)) ?>
                                            <?= $video['is_active'] ? '' : ' · hidden from shop' ?>
                                        </span>
                                    </span>
                                    <span class="picker__check"><?= icon('check', 14) ?></span>
                                </label>
                                <label class="picker__single" for="allow-single-<?= $videoId ?>">
                                    <span>Single purchase</span>
                                    <select class="input" id="allow-single-<?= $videoId ?>" name="allow_single_purchase[<?= $videoId ?>]"
                                            aria-label="Single purchase availability for <?= e($video['title']) ?>">
                                        <option value="1" <?= $allowSingle ? 'selected' : '' ?>>Also sell on its own</option>
                                        <option value="0" <?= !$allowSingle ? 'selected' : '' ?>>Bundle only</option>
                                    </select>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint">
                        Total for the selected videos that are available individually:
                        <strong id="bundle-single-total" data-currency="<?= e($currency) ?>">—</strong>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <aside class="card card--pad">
            <header class="card__head">
                <h2><?= icon('wallet', 18) ?> Price &amp; visibility</h2>
            </header>

            <div class="field">
                <label for="price">Bundle price (<?= e($currency) ?>)</label>
                <input class="input" id="price" name="price" type="number" min="1" max="500000" step="0.01" required
                       value="<?= e($value('price')) ?>">
                <?php if ($error('price')): ?><p class="field__error"><?= e($error('price')) ?></p><?php endif; ?>
                <p class="hint">One payment for every video. When all included videos are available individually, the bundle price must not exceed their combined single prices.</p>
                <p class="hint">
                    <?= icon('refresh', 14) ?>
                    Saving is live: everyone who already paid for this bundle gets any video you add here, straight away.
                </p>
            </div>

            <div class="field">
                <label for="position">Order on the home page</label>
                <input class="input" id="position" name="position" type="number" min="0" max="9999" step="1"
                       value="<?= e($value('position', '0')) ?>">
                <?php if ($error('position')): ?><p class="field__error"><?= e($error('position')) ?></p><?php endif; ?>
            </div>

            <label class="switch">
                <input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>>
                <span class="switch__track"><span class="switch__thumb"></span></span>
                <span class="switch__label">On sale</span>
            </label>

            <?php if ($isEdit): ?>
                <dl class="detail-list" style="margin-top:16px">
                    <div><dt>Sold</dt><dd><?= (int) app()->catalog->bundleSales((int) $bundle['id']) ?> time(s)</dd></div>
                    <div><dt>Videos</dt><dd><?= (int) $bundle['video_count'] ?></dd></div>
                    <div><dt>Update reaches</dt>
                        <dd><?= (int) app()->catalog->bundleSales((int) $bundle['id']) ?> buyer(s)</dd>
                    </div>
                </dl>
            <?php endif; ?>

            <div class="save-bar__actions" style="margin-top:18px">
                <button type="submit" class="btn btn--primary btn--block">
                    <?= icon('check', 16) ?> <?= $isEdit ? 'Save bundle' : 'Create bundle' ?>
                </button>
            </div>
        </aside>
    </div>
</form>

<?php if ($isEdit): ?>
    <form method="post" class="inline-form" data-confirm="Delete this bundle? Buyers keep their videos.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn--danger btn--sm"><?= icon('trash', 15) ?> Delete bundle</button>
    </form>
<?php endif; ?>
