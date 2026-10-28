<?php
/** @var array<string, mixed> $settings */
/** @var array<string, mixed> $payment */
$videoUrl = (string) $settings['youtube_video_url'];
$embedUrl = youtube_embed_url($videoUrl, true);
$channel = (string) $settings['youtube_channel_url'];
$daysLeft = access_days_left($payment);
$accessLink = url('success.php?t=' . $payment['access_token']);
$amountLabel = money_exact($payment['amount'], $payment['currency']);
?>
<section class="section section--tight">
    <div class="wrap success-wrap">
        <div class="success-hero">
            <span class="success-hero__badge"><?= icon('check', 34) ?></span>
            <span class="eyebrow eyebrow--success">Payment verified</span>
            <h1>You're in. Enjoy the video.</h1>
            <p>
                <?= e($amountLabel) ?> received from <strong><?= e($payment['user_name']) ?></strong>.
                Your access is valid for <strong><?= $daysLeft ?> more day<?= $daysLeft === 1 ? '' : 's' ?></strong>.
            </p>
        </div>

        <div class="success-grid">
            <div class="card card--pad video-card">
                <div class="video-card__head">
                    <span class="chip chip--live"><?= icon('play', 13) ?> Now playing</span>
                    <span class="muted small">Private link · please do not share</span>
                </div>
                <?php if ($embedUrl !== ''): ?>
                    <div class="video-embed">
                        <iframe
                            src="<?= e($embedUrl) ?>"
                            title="<?= e($settings['site_name']) ?> — exclusive video"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <div class="alert alert--warning">
                        <?= icon('alert', 18) ?>
                        <span>The video link has not been configured by the admin yet. Please check back later or contact support.</span>
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
                    <div><dt>Amount paid</dt><dd class="strong"><?= e($amountLabel) ?></dd></div>
                    <div><dt>Name</dt><dd><?= e($payment['user_name']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($payment['user_email']) ?></dd></div>
                    <div><dt>Mobile</dt><dd><?= e(display_mobile($payment['user_mobile'])) ?></dd></div>
                    <div><dt>Payment ID</dt><dd class="mono"><?= e($payment['payment_id'] ?: '—') ?></dd></div>
                    <div><dt>Order ID</dt><dd class="mono"><?= e($payment['order_id'] ?: '—') ?></dd></div>
                    <div><dt>Paid on</dt><dd><?= e(date_display($payment['paid_at'] ?: $payment['created_at'])) ?></dd></div>
                    <div><dt>Access until</dt><dd><?= e(date_display($payment['access_expires_at'])) ?></dd></div>
                </dl>

                <div class="receipt__actions">
                    <button type="button" class="btn btn--ghost btn--block btn--sm" data-copy="<?= e($accessLink) ?>">
                        <?= icon('copy', 16) ?> Copy my private access link
                    </button>
                    <p class="muted small">
                        Keep this link private. Access is also remembered on this device for <?= (int) $daysLeft ?> days.
                        <?php if (!empty($settings['support_email'])): ?>
                            Need help? <a href="mailto:<?= e($settings['support_email']) ?>">Contact support</a>.
                        <?php endif; ?>
                    </p>
                </div>
            </aside>
        </div>

        <div class="success-next">
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('star', 20) ?></span>
                <h3>Loved the video?</h3>
                <p>Tell the creator what you think — it helps shape the next episode.</p>
            </div>
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('users', 20) ?></span>
                <h3>Share the channel</h3>
                <p>Send the channel to a friend who would enjoy this content.</p>
            </div>
            <div class="success-next__item">
                <span class="success-next__icon"><?= icon('refresh', 20) ?></span>
                <h3>Need access later?</h3>
                <p><a href="<?= e(url('restore.php')) ?>">Restore your access</a> any time with your e-mail and mobile number.</p>
            </div>
        </div>
    </div>
</section>
