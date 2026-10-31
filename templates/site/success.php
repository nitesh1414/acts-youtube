<?php
/**
 * The buyer's private library: the player for the selected video plus every
 * other video the same payment unlocked.
 *
 * @var array<string, mixed>             $settings
 * @var array<string, mixed>             $payment
 * @var array<int, array<string, mixed>> $videos       the whole library
 * @var array<int, array<string, mixed>> $purchased    unlocked by this payment
 * @var array<int, int>                  $purchasedIds
 * @var array<string, mixed>|null        $current
 * @var string                           $token
 */
$channel = (string) $settings['youtube_channel_url'];
$daysLeft = access_days_left($payment);
$lifetime = $daysLeft === null;
$accessLink = url('success.php?t=' . $payment['access_token']);
$currency = isset($payment['currency']) && $payment['currency'] !== '' ? $payment['currency'] : 'INR';
$amountLabel = money_exact($payment['amount'], $currency);
$isBundle = isset($payment['product_type']) && $payment['product_type'] === 'bundle';
$productName = isset($payment['product_name']) && $payment['product_name'] !== ''
    ? (string) $payment['product_name']
    : (string) $settings['site_name'];
$embedUrl = $current !== null ? (string) $current['embed_url'] : '';
$others = [];
foreach ($videos as $video) {
    if ($current === null || (int) $video['id'] !== (int) $current['id']) {
        $others[] = $video;
    }
}
$libraryCount = count($videos);
$purchasedCount = count($purchased);
?>
<section class="section section--tight">
    <div class="wrap success-wrap">
        <div class="success-hero">
            <span class="success-hero__badge"><?= icon('check', 34) ?></span>
            <span class="eyebrow eyebrow--success">Payment verified</span>
            <h1><?= $purchasedCount > 1 ? 'Your videos are ready.' : 'You\'re in. Enjoy the video.' ?></h1>
            <p>
                <strong><?= e($productName) ?></strong> · <?= e($amountLabel) ?> received from
                <strong><?= e($payment['user_name']) ?></strong>.
                <?php if ($lifetime): ?>
                    Your access <strong>does not expire</strong> — come back any time.
                <?php else: ?>
                    Your access is valid for <strong><?= (int) $daysLeft ?> more day<?= $daysLeft === 1 ? '' : 's' ?></strong>.
                <?php endif; ?>
            </p>
        </div>

        <div class="success-grid">
            <div class="card card--pad video-card" id="player">
                <div class="video-card__head">
                    <span class="chip chip--live"><?= icon('play', 13) ?> Now playing</span>
                    <span class="muted small">
                        <?= $purchasedCount ?> new · <?= $libraryCount ?> in your library · private
                    </span>
                </div>

                <?php if ($embedUrl !== ''): ?>
                    <div class="video-embed">
                        <iframe
                            src="<?= e($embedUrl) ?>"
                            title="<?= e($current['title']) ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                    </div>
                    <div class="video-card__title">
                        <h2><?= e($current['title']) ?></h2>
                        <?php if (trim((string) ($current['description'] ?? '')) !== ''): ?>
                            <p class="muted small"><?= e($current['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert--warning">
                        <?= icon('alert', 18) ?>
                        <span>This video has no playable link yet. Please check back later or contact support.</span>
                    </div>
                <?php endif; ?>

                <div class="video-card__foot">
                    <p class="muted small">
                        <?= icon('info', 15) ?>
                        Streaming issues? Try a different browser or disable any ad-blocker for this page.
                    </p>
                    <?php if ($channel !== ''): ?>
                        <a class="btn btn--youtube btn--sm" href="<?= e($channel) ?>" target="_blank" rel="noopener">
                            <?= icon('youtube', 16) ?> Subscribe
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="card card--pad receipt">
                <h2 class="receipt__title"><?= icon('credit-card', 18) ?> Your receipt</h2>
                <dl class="receipt__list">
                    <div><dt>Purchase</dt><dd class="strong"><?= e($productName) ?></dd></div>
                    <div><dt>Type</dt><dd><?= $isBundle ? 'Bundle · ' . count($videos) . ' videos' : 'Single video' ?></dd></div>
                    <div><dt>Amount paid</dt><dd class="strong"><?= e($amountLabel) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($payment['user_email']) ?></dd></div>
                    <div><dt>Mobile</dt><dd><?= e(display_mobile($payment['user_mobile'])) ?></dd></div>
                    <div><dt>Payment ID</dt><dd class="mono"><?= e(!empty($payment['payment_id']) ? $payment['payment_id'] : '—') ?></dd></div>
                    <div><dt>Paid on</dt><dd><?= e(date_display(!empty($payment['paid_at']) ? $payment['paid_at'] : $payment['created_at'])) ?></dd></div>
                    <div><dt>Access</dt><dd><?= $lifetime ? 'No expiry' : e(date_display($payment['access_expires_at'])) ?></dd></div>
                </dl>

                <div class="receipt__actions">
                    <button type="button" class="btn btn--ghost btn--block btn--sm" data-copy="<?= e($accessLink) ?>">
                        <?= icon('copy', 16) ?> Copy my private access link
                    </button>
                    <p class="muted small">
                        Bookmark this page. The link works <?= $lifetime ? 'any time' : 'until ' . e(date_display($payment['access_expires_at'])) ?>,
                        and you can always recover it from <a href="<?= e(url('restore.php')) ?>">Restore access</a>.
                    </p>
                </div>
            </aside>
        </div>

        <?php if ($libraryCount > 1): ?>
            <section class="library" aria-label="Your video library">
                <header class="library__head">
                    <h2><?= icon('youtube', 18) ?> Your library</h2>
                    <p class="muted small">
                        Everything you have bought with <?= e($payment['user_email']) ?> — pick any video to watch it.
                    </p>
                </header>

                <div class="library__grid">
                    <?php if ($current !== null): ?>
                        <a class="library__item is-current"
                           href="<?= e(url('success.php?v=' . (int) $current['id'] . ($token !== '' ? '&t=' . urlencode((string) $payment['access_token']) : '') . '#player')) ?>">
                            <span class="library__thumb">
                                <?php if (!empty($current['thumbnail'])): ?>
                                    <img src="<?= e($current['thumbnail']) ?>" alt="" loading="lazy" width="240" height="135">
                                <?php endif; ?>
                                <span class="library__play"><?= icon('play', 18) ?></span>
                            </span>
                            <span class="library__title"><?= e($current['title']) ?></span>
                            <span class="library__state">Playing now</span>
                        </a>
                    <?php endif; ?>

                    <?php foreach ($others as $video): ?>
                        <a class="library__item"
                           href="<?= e(url('success.php?v=' . (int) $video['id'] . ($token !== '' ? '&t=' . urlencode((string) $payment['access_token']) : '') . '#player')) ?>">
                            <span class="library__thumb">
                                <?php if (!empty($video['thumbnail'])): ?>
                                    <img src="<?= e($video['thumbnail']) ?>" alt="" loading="lazy" width="240" height="135">
                                <?php endif; ?>
                                <span class="library__play"><?= icon('play', 18) ?></span>
                            </span>
                            <span class="library__title"><?= e($video['title']) ?></span>
                            <span class="library__state">
                                <?= in_array((int) $video['id'], $purchasedIds, true) ? 'Just bought' : 'Watch' ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="success-next">
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('clock', 20) ?></span>
                <h3>Watch any time</h3>
                <p><?= $lifetime ? 'This library has no expiry date.' : (int) $daysLeft . ' days of access left.' ?> Sign in from another device with your e-mail and mobile number.</p>
            </div>
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('tag', 20) ?></span>
                <h3>Want more videos?</h3>
                <p><a href="<?= e(url('')) ?>#library">Browse the library</a> — every video and bundle is available with one payment.</p>
            </div>
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('refresh', 20) ?></span>
                <h3>Need access later?</h3>
                <p><a href="<?= e(url('restore.php')) ?>">Restore your access</a> any time with your e-mail and mobile number.</p>
            </div>
        </div>
    </div>
</section>
