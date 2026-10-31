<?php
/** @var array<string, mixed>             $settings */
/** @var array<int, array<string, mixed>> $videos */
$currency = (string) $settings['currency'];
?>
<div class="page-head">
    <div>
        <h2>Video library</h2>
        <p class="muted small">Each video has its own price. Visitors can buy one video or a bundle.</p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('admin/video.php')) ?>"><?= icon('upload', 16) ?> Add video</a>
</div>

<?php if (empty($videos)): ?>
    <div class="card empty">
        <?= icon('youtube', 34) ?>
        <h3>No videos yet</h3>
        <p>Add your first video — paste a YouTube link, set a price and it appears on the site right away.</p>
        <a class="btn btn--primary" href="<?= e(url('admin/video.php')) ?>"><?= icon('upload', 16) ?> Add your first video</a>
    </div>
<?php else: ?>
    <div class="card table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Video</th>
                    <th>Price</th>
                    <th>In bundles</th>
                    <th>Sold</th>
                    <th>Status</th>
                    <th class="right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $video): ?>
                    <tr>
                        <td>
                            <div class="media-cell">
                                <?php if ($video['thumbnail'] !== ''): ?>
                                    <img src="<?= e($video['thumbnail']) ?>" alt="" loading="lazy" width="96" height="54">
                                <?php else: ?>
                                    <span class="media-cell__empty"><?= icon('youtube', 18) ?></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($video['title']) ?></strong>
                                    <span class="muted tiny">
                                        #<?= (int) $video['id'] ?> · order <?= (int) $video['position'] ?>
                                        <?php if (!empty($video['youtube_id'])): ?> · <?= e($video['youtube_id']) ?><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="strong"><?= e(money((float) $video['price'], $currency)) ?></td>
                        <td><?= (int) $video['bundles'] ?></td>
                        <td><?= (int) $video['sales'] ?></td>
                        <td>
                            <span class="badge <?= $video['is_active'] ? 'badge--success' : 'badge--muted' ?>">
                                <?= $video['is_active'] ? 'On sale' : 'Hidden' ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn--ghost btn--xs" href="<?= e(url('admin/video.php?id=' . (int) $video['id'])) ?>">
                                    <?= icon('sliders', 14) ?> Edit
                                </a>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button type="submit" class="btn btn--ghost btn--xs">
                                        <?= $video['is_active'] ? 'Hide' : 'Publish' ?>
                                    </button>
                                </form>
                                <form method="post" class="inline-form" data-confirm="Delete this video? Buyers who already paid keep access.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn--danger btn--xs" title="Delete">
                                        <?= icon('trash', 14) ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<section class="card card--pad">
    <header class="card__head">
        <h2><?= icon('tag', 18) ?> Next step</h2>
    </header>
    <p class="muted small">
        Group several videos into a bundle and sell the set with a single payment —
        the name you give the bundle is what visitors see.
    </p>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/bundles.php')) ?>"><?= icon('tag', 15) ?> Manage bundles</a>
</section>
