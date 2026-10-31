<?php
/**
 * Landing page: hero + checkout card, the video library, bundles, how it works.
 *
 * @var array<string, mixed>            $settings
 * @var array<string, mixed>            $storefront videos, bundles, legacy
 * @var array<string, mixed>|null       $selected   product preselected for checkout
 */
$currency = (string) $settings['currency'];
$accessDays = (int) $settings['access_days'];
$demoMode = (int) $settings['demo_mode'] === 1;
$razorpayReady = app()->settings->isRazorpayConfigured();
$channel = (string) $settings['youtube_channel_url'];

$videos = $storefront['videos'];
$bundles = $storefront['bundles'];
$legacy = $storefront['legacy'];
$hasProducts = $selected !== null;
$totalVideos = count($videos);

$access = current_access_payment();
$ownedIds = [];
if ($access !== null) {
    foreach (catalog_videos_for_payment($access) as $ownedVideo) {
        $ownedIds[] = (int) $ownedVideo['id'];
    }
}

$accessNote = $accessDays > 0
    ? 'Watch anytime for ' . $accessDays . ' day' . ($accessDays === 1 ? '' : 's')
    : 'Watch anytime — your access does not expire';

$priceLabel = $selected !== null ? money((float) $selected['price'], $currency) : '';
$videoCount = $selected !== null ? count($selected['videos']) : 0;
$periodLabel = $videoCount > 1 ? $videoCount . ' videos' : '1 video';
?>

<section class="hero">
    <div class="wrap hero__grid">
        <div class="hero__copy">
            <span class="eyebrow"><?= icon('sparkles', 15) ?> Premium video library</span>
            <h1 class="hero__title">
                <?= e($settings['logo_text']) ?>
                <span class="hero__title-accent">unlocked in 30 seconds.</span>
            </h1>
            <p class="hero__lede"><?= e($settings['tagline']) ?></p>

            <ul class="hero__features">
                <li><?= icon('check-circle', 18) ?> Instant access right after payment</li>
                <li><?= icon('shield', 18) ?> Secure checkout — UPI, cards, netbanking &amp; wallets</li>
                <li><?= icon('clock', 18) ?> <?= e($accessNote) ?></li>
                <li><?= icon('youtube', 18) ?> <?= $totalVideos > 0 ? $totalVideos . ' video' . ($totalVideos === 1 ? '' : 's') . ' to choose from' : 'Buy a video or a whole bundle' ?></li>
            </ul>

            <div class="hero__actions">
                <a class="btn btn--primary" href="#library">
                    <?= icon('play', 17) ?> Browse the library
                </a>
                <?php if ($access !== null): ?>
                    <a class="btn btn--ghost" href="<?= e(url('success.php')) ?>">
                        <?= icon('eye', 17) ?> Open my library
                    </a>
                <?php elseif ($bundles !== []): ?>
                    <a class="btn btn--ghost" href="#bundles">
                        <?= icon('tag', 17) ?> See bundles
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($legacy !== null && $legacy['thumbnail'] !== ''): ?>
                <div class="preview">
                    <img src="<?= e($legacy['thumbnail']) ?>" alt="Video preview" loading="lazy" class="preview__img">
                    <div class="preview__overlay">
                        <span class="preview__play"><?= icon('play', 22) ?></span>
                        <span class="preview__text">Preview locked · unlock to watch in HD</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hero__card" id="checkout">
            <?php if ($access !== null && $videos === [] && $bundles === []): ?>
                <div class="card card--pad">
                    <div class="state state--success">
                        <?= icon('check-circle', 30) ?>
                        <h2>You already have access</h2>
                        <p>Your payment is verified. Jump straight back into your videos.</p>
                    </div>
                    <a class="btn btn--primary btn--block" href="<?= e(url('success.php')) ?>">
                        <?= icon('play', 18) ?> Watch my videos
                    </a>
                </div>
            <?php elseif (!$hasProducts): ?>
                <div class="card card--pad">
                    <div class="state">
                        <?= icon('youtube', 30) ?>
                        <h2>Nothing on sale yet</h2>
                        <p>The video library is being set up. Please check back soon<?= !empty($settings['support_email']) ? ', or contact ' . e($settings['support_email']) : '' ?>.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card card--pad paycard">
                    <div class="paycard__head">
                        <div>
                            <p class="paycard__label">You are buying</p>
                            <p class="paycard__product" id="pay-product-name"><?= e($selected['name']) ?></p>
                            <p class="paycard__price">
                                <span id="pay-price"><?= e($priceLabel) ?></span>
                                <span class="paycard__period" id="pay-period">/ <?= e($periodLabel) ?></span>
                            </p>
                        </div>
                        <span class="chip chip--secure"><?= icon('lock', 14) ?> Secure</span>
                    </div>

                    <?php if (!$razorpayReady && !$demoMode): ?>
                        <div class="alert alert--warning">
                            <?= icon('alert', 18) ?>
                            <span>Payments are not configured yet. Please check back soon or contact support.</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($demoMode): ?>
                        <div class="alert alert--info">
                            <?= icon('info', 18) ?>
                            <span><strong>Demo mode is on.</strong> No real money is charged; you can simulate a successful payment.</span>
                        </div>
                    <?php endif; ?>

                    <form id="payment-form" class="form" novalidate
                          data-csrf="<?= e($csrf) ?>"
                          data-endpoint="<?= e(url('api/create_order.php')) ?>"
                          data-demo-endpoint="<?= e(url('demo_pay.php')) ?>"
                          data-verify-endpoint="<?= e(url('api/verify_payment.php')) ?>"
                          data-success-url="<?= e(url('success.php')) ?>"
                          data-key="<?= e($settings['razorpay_key_id']) ?>"
                          data-amount-paise="<?= (int) round((float) $selected['price'] * 100) ?>"
                          data-currency="<?= e($currency) ?>"
                          data-site-name="<?= e($settings['site_name']) ?>"
                          data-amount-label="<?= e($priceLabel) ?>"
                          data-product-name="<?= e($selected['name']) ?>"
                          data-demo="<?= $demoMode ? '1' : '0' ?>">
                        <input type="hidden" name="item_type" id="item-type" value="<?= e($selected['type']) ?>">
                        <input type="hidden" name="item_id" id="item-id" value="<?= (int) $selected['id'] ?>">

                        <div class="field">
                            <label for="name">Full name</label>
                            <div class="field__control">
                                <?= icon('user', 18) ?>
                                <input id="name" name="name" type="text" autocomplete="name" placeholder="Your full name" required maxlength="120">
                            </div>
                        </div>
                        <div class="field">
                            <label for="email">Email address</label>
                            <div class="field__control">
                                <?= icon('mail', 18) ?>
                                <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="190">
                            </div>
                        </div>
                        <div class="field">
                            <label for="mobile">Mobile number</label>
                            <div class="field__control">
                                <?= icon('phone', 18) ?>
                                <input id="mobile" name="mobile" type="tel" autocomplete="tel" inputmode="tel" placeholder="98765 43210" required maxlength="20">
                            </div>
                        </div>

                        <div id="form-error" class="alert alert--danger" hidden></div>

                        <button type="submit" class="btn btn--primary btn--block btn--lg" id="pay-button"
                                <?= (!$razorpayReady && !$demoMode) ? 'disabled' : '' ?>>
                            <?= icon('lock', 18) ?>
                            <span class="btn__label">Pay <?= e($priceLabel) ?> &amp; unlock</span>
                        </button>

                        <p class="form__foot">
                            <?= icon('shield', 15) ?>
                            One-time payment. Your details are only used for the receipt and to restore access.
                        </p>
                    </form>
                </div>

                <div class="trust">
                    <span><?= icon('shield', 16) ?> 256-bit SSL</span>
                    <span><?= icon('zap', 16) ?> Instant delivery</span>
                    <span><?= icon('clock', 16) ?> <?= $accessDays > 0 ? $accessDays . '-day access' : 'Lifetime access' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($videos !== []): ?>
<section class="section" id="library">
    <div class="wrap section__inner">
        <header class="section__head">
            <span class="eyebrow">The library</span>
            <h2>Pick a video, or take them all</h2>
            <p>Every video is sold on its own. Choose one, or grab a bundle and pay once for the set.</p>
        </header>

        <div class="catalog">
            <?php foreach ($videos as $video): ?>
                <?php $owned = in_array((int) $video['id'], $ownedIds, true); ?>
                <article class="vcard <?= $owned ? 'is-owned' : '' ?>"
                         data-product-card="video:<?= (int) $video['id'] ?>">
                    <div class="vcard__thumb">
                        <?php if ($video['thumbnail'] !== ''): ?>
                            <img src="<?= e($video['thumbnail']) ?>" alt="" loading="lazy" width="320" height="180">
                        <?php else: ?>
                            <span class="vcard__placeholder"><?= icon('youtube', 30) ?></span>
                        <?php endif; ?>
                        <span class="vcard__price"><?= e(money((float) $video['price'], $currency)) ?></span>
                        <?php if ($owned): ?>
                            <span class="vcard__owned"><?= icon('check', 13) ?> Owned</span>
                        <?php endif; ?>
                    </div>
                    <div class="vcard__body">
                        <h3><?= e($video['title']) ?></h3>
                        <?php if (trim((string) $video['description']) !== ''): ?>
                            <p class="muted small"><?= e(text_limit((string) $video['description'], 130)) ?></p>
                        <?php endif; ?>
                        <div class="vcard__foot">
                            <span class="chip"><?= icon('play', 13) ?> Single video</span>
                            <?php if ($owned): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('success.php?v=' . (int) $video['id'])) ?>">Watch</a>
                            <?php else: ?>
                                <a class="btn btn--primary btn--sm" href="<?= e(url('?buy=video:' . (int) $video['id'] . '#checkout')) ?>"
                                   data-buy
                                   data-type="video"
                                   data-id="<?= (int) $video['id'] ?>"
                                   data-name="<?= e($video['title']) ?>"
                                   data-price="<?= (int) round((float) $video['price'] * 100) ?>"
                                   data-label="<?= e(money((float) $video['price'], $currency)) ?>"
                                   data-period="1 video">Buy video</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($bundles !== []): ?>
<section class="section section--muted" id="bundles">
    <div class="wrap section__inner">
        <header class="section__head">
            <span class="eyebrow">Bundles</span>
            <h2>Better value, one payment</h2>
            <p>A bundle is a named set of videos. Pay once and every video in it stays in your library.</p>
        </header>

        <div class="bundles">
            <?php foreach ($bundles as $bundle): ?>
                <article class="bundle-card" data-product-card="bundle:<?= (int) $bundle['id'] ?>">
                    <div class="bundle-card__head">
                        <div>
                            <span class="chip chip--secure"><?= icon('tag', 13) ?> Bundle · <?= (int) $bundle['video_count'] ?> videos</span>
                            <h3><?= e($bundle['name']) ?></h3>
                            <?php if (trim((string) $bundle['description']) !== ''): ?>
                                <p class="muted small"><?= e($bundle['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="bundle-card__price">
                            <span class="strong"><?= e(money((float) $bundle['price'], $currency)) ?></span>
                            <?php if ($bundle['savings'] > 0): ?>
                                <span class="muted small"><s><?= e(money((float) $bundle['single_total'], $currency)) ?></s></span>
                                <span class="save-badge">Save <?= e(money((float) $bundle['savings'], $currency)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <ul class="bundle-card__list">
                        <?php foreach ($bundle['videos'] as $video): ?>
                            <li><?= icon('play', 14) ?> <span><?= e($video['title']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="bundle-card__foot">
                        <span class="muted small">One payment · <?= $accessDays > 0 ? $accessDays . ' days' : 'lifetime' ?> access</span>
                        <a class="btn btn--primary" href="<?= e(url('?buy=bundle:' . (int) $bundle['id'] . '#checkout')) ?>"
                           data-buy
                           data-type="bundle"
                           data-id="<?= (int) $bundle['id'] ?>"
                           data-name="<?= e($bundle['name']) ?>"
                           data-price="<?= (int) round((float) $bundle['price'] * 100) ?>"
                           data-label="<?= e(money((float) $bundle['price'], $currency)) ?>"
                           data-period="<?= (int) $bundle['video_count'] ?> videos">
                            Get the bundle
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!$hasProducts): ?>
<section class="section" id="library">
    <div class="wrap section__inner section__inner--narrow">
        <div class="card card--pad state">
            <?= icon('youtube', 30) ?>
            <h2>The library is empty</h2>
            <p>
                <?php if ($access !== null): ?>
                    You are signed in as a customer — your own videos appear under “My library”.
                <?php elseif (app()->auth->user()): ?>
                    Add your first video in the admin panel and it will appear here instantly.
                <?php else: ?>
                    Videos will appear here as soon as the owner publishes them.
                <?php endif; ?>
            </p>
            <?php if (app()->auth->user()): ?>
                <a class="btn btn--primary" href="<?= e(url('admin/videos.php')) ?>"><?= icon('youtube', 17) ?> Add a video</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hasProducts || $bundles !== []): ?>
<section class="section section--muted" id="how">
    <div class="wrap section__inner">
        <header class="section__head">
            <span class="eyebrow">How it works</span>
            <h2>Three steps to watch</h2>
        </header>
        <ol class="steps">
            <li class="step">
                <span class="step__num">1</span>
                <h3>Choose a video or bundle</h3>
                <p>Each video has its own price. Bundles group several videos for less than buying them separately.</p>
            </li>
            <li class="step">
                <span class="step__num">2</span>
                <h3>Pay securely</h3>
                <p>Complete the payment in the Razorpay window — UPI, cards, netbanking or wallets.</p>
            </li>
            <li class="step">
                <span class="step__num">3</span>
                <h3>Watch any time</h3>
                <p>Your videos are added to your private library. Come back on any device with your e-mail and mobile number.</p>
            </li>
        </ol>
    </div>
</section>
<?php endif; ?>

<section class="section" id="faq">
    <div class="wrap section__inner section__inner--narrow">
        <header class="section__head">
            <span class="eyebrow">FAQ</span>
            <h2>Questions, answered</h2>
        </header>

        <details class="faq" open>
            <summary>Can I buy just one video?</summary>
            <p>Yes. Every video is listed with its own price — pay for that one and only that one is unlocked.</p>
        </details>
        <details class="faq">
            <summary>What is a bundle?</summary>
            <p>A named set of videos sold together for one price. Everything inside is unlocked at once, and the bundle stays in your library.</p>
        </details>
        <details class="faq">
            <summary>How long can I watch?</summary>
            <p><?= $accessDays > 0
                ? 'Each purchase gives you ' . $accessDays . ' day' . ($accessDays === 1 ? '' : 's') . ' of access, starting the moment the payment is confirmed.'
                : 'Your purchases never expire — come back and watch whenever you like.' ?></p>
        </details>
        <details class="faq">
            <summary>I cleared my browser or changed device. Can I get access again?</summary>
            <p>Yes. Open <a href="<?= e(url('restore.php')) ?>">Restore access</a> and enter the same e-mail and mobile number you paid with. We re-issue your access instantly.</p>
        </details>
        <details class="faq">
            <summary>Which payment methods are supported?</summary>
            <p>UPI, credit/debit cards, netbanking and popular wallets — everything Razorpay supports in India.</p>
        </details>
        <details class="faq">
            <summary>Is my payment information safe?</summary>
            <p>Absolutely. The checkout is hosted by Razorpay (PCI-DSS compliant). We only receive a payment reference, never your card details.</p>
        </details>
        <?php if (!empty($settings['support_email']) || !empty($settings['support_phone'])): ?>
            <details class="faq">
                <summary>I still need help</summary>
                <p>
                    Write to
                    <?php if (!empty($settings['support_email'])): ?>
                        <a href="mailto:<?= e($settings['support_email']) ?>"><?= e($settings['support_email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['support_email']) && !empty($settings['support_phone'])): ?>, or call <?php endif; ?>
                    <?php if (!empty($settings['support_phone'])): ?>
                        <a href="tel:<?= e(normalize_mobile($settings['support_phone'])) ?>"><?= e(display_mobile($settings['support_phone'])) ?></a>
                    <?php endif; ?>
                    . Include your payment id if you have one.
                </p>
            </details>
        <?php endif; ?>
    </div>
</section>

<?php if ($channel !== ''): ?>
<section class="section">
    <div class="wrap">
        <div class="cta">
            <div>
                <h2>Enjoyed it? Join the channel.</h2>
                <p>New videos drop every week. Subscribe so you never miss the next one.</p>
            </div>
            <a class="btn btn--youtube" href="<?= e($channel) ?>" target="_blank" rel="noopener">
                <?= icon('youtube', 18) ?> Subscribe on YouTube
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
