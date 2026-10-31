<?php
/** @var array<string, mixed>             $settings */
/** @var array<int, array<string, mixed>> $bundles */
/** @var array<int, array<string, mixed>> $videos */
$currency = (string) $settings['currency'];
?>
<div class="page-head">
    <div>
        <h2>Bundles</h2>
        <p class="muted small">One payment, several videos. The bundle name is what visitors see.</p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('admin/bundle.php')) ?>"><?= icon('plus', 16) ?> New bundle</a>
</div>

<?php if (empty($bundles)): ?>
    <div class="card empty">
        <?= icon('tag', 34) ?>
        <h3>No bundles yet</h3>
        <p>Pick two or more videos, give the set a name and sell it as a single purchase.</p>
        <?php if (empty($videos)): ?>
            <p class="muted small">You need at least one video first.</p>
            <a class="btn btn--primary" href="<?= e(url('admin/videos.php')) ?>"><?= icon('upload', 16) ?> Add a video</a>
        <?php else: ?>
            <a class="btn btn--primary" href="<?= e(url('admin/bundle.php')) ?>"><?= icon('plus', 16) ?> Create your first bundle</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bundle-list">
        <?php foreach ($bundles as $bundle): ?>
            <article class="card card--pad bundle-row">
                <div class="bundle-row__main">
                    <div class="bundle-row__head">
                        <h3><?= e($bundle['name']) ?></h3>
                        <span class="badge <?= $bundle['is_active'] ? 'badge--success' : 'badge--muted' ?>">
                            <?= $bundle['is_active'] ? 'On sale' : 'Hidden' ?>
                        </span>
                        <?php if ($bundle['savings'] > 0): ?>
                            <span class="save-badge">Save <?= e(money($bundle['savings'], $currency)) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ((string) $bundle['description'] !== ''): ?>
                        <p class="muted small"><?= e($bundle['description']) ?></p>
                    <?php endif; ?>
                    <ul class="bundle-row__videos">
                        <?php foreach ($bundle['videos'] as $video): ?>
                            <li><?= icon('play', 12) ?> <?= e($video['title']) ?> <span class="muted tiny"><?= e(money((float) $video['price'], $currency)) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (empty($bundle['videos'])): ?>
                            <li class="muted">No videos selected.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="bundle-row__side">
                    <p class="price-tag">
                        <?= e(money((float) $bundle['price'], $currency)) ?>
                        <?php if ($bundle['single_total'] > 0): ?>
                            <span class="price-tag__was"><?= e(money($bundle['single_total'], $currency)) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="muted tiny"><?= (int) $bundle['video_count'] ?> video(s) · <?= (int) $bundle['sales'] ?> sold</p>
                    <div class="row-actions">
                        <a class="btn btn--ghost btn--xs" href="<?= e(url('admin/bundle.php?id=' . (int) $bundle['id'])) ?>">
                            <?= icon('sliders', 14) ?> Edit
                        </a>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $bundle['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit" class="btn btn--ghost btn--xs"><?= $bundle['is_active'] ? 'Hide' : 'Publish' ?></button>
                        </form>
                        <form method="post" class="inline-form" data-confirm="Delete this bundle? Buyers keep their videos.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $bundle['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn--danger btn--xs" title="Delete"><?= icon('trash', 14) ?></button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
