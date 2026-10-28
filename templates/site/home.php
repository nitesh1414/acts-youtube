<?php
/** @var array<string, mixed> $settings */
$videoUrl = (string) $settings['youtube_video_url'];
$videoId = youtube_id($videoUrl);
$channel = (string) $settings['youtube_channel_url'];
$amount = (float) $settings['amount'];
$currency = (string) $settings['currency'];
$accessDays = (int) $settings['access_days'];
$demoMode = (int) $settings['demo_mode'] === 1;
$razorpayReady = app()->settings->isRazorpayConfigured();
$thumb = youtube_thumbnail($videoUrl, 'hqdefault');
$hasAccess = current_access_payment() !== null;
$successCount = (int) app()->payments->stats()['success_count'];
?>

<section class="hero">
    <div class="wrap hero__grid">
        <div class="hero__copy">
            <span class="eyebrow"><?= icon('sparkles', 15) ?> Premium video access</span>
            <h1 class="hero__title">
                <?= e($settings['logo_text']) ?>
                <span class="hero__title-accent">unlocked in 30 seconds.</span>
            </h1>
            <p class="hero__lede"><?= e($settings['tagline']) ?></p>

            <ul class="hero__features">
                <li><?= icon('check-circle', 18) ?> Instant access right after payment</li>
                <li><?= icon('shield', 18) ?> Secure checkout — UPI, cards, netbanking &amp; wallets</li>
                <li><?= icon('clock', 18) ?> Watch anytime for <?= $accessDays ?> day<?= $accessDays === 1 ? '' : 's' ?></li>
                <li><?= icon('youtube', 18) ?> One private, ad-light video stream</li>
            </ul>

            <?php if ($thumb !== '' && !$hasAccess): ?>
                <div class="preview">
                    <img src="<?= e($thumb) ?>" alt="Video preview" loading="lazy" class="preview__img">
                    <div class="preview__overlay">
                        <span class="preview__play"><?= icon('play', 22) ?></span>
                        <span class="preview__text">Preview locked · unlock to watch in HD</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hero__card">
            <?php if ($hasAccess): ?>
                <div class="card card--pad">
                    <div class="state state--success">
                        <?= icon('check-circle', 30) ?>
                        <h2>You already have access</h2>
                        <p>Your payment is verified. Jump straight back into the video.</p>
                    </div>
                    <a class="btn btn--primary btn--block" href="<?= e(url('success.php')) ?>">
                        <?= icon('play', 18) ?> Watch the video
                    </a>
                </div>
            <?php else: ?>
                <div class="card card--pad paycard">
                    <div class="paycard__head">
                        <div>
                            <p class="paycard__label">One-time payment</p>
                            <p class="paycard__price" data-amount-label>
                                <?= e(money($amount, $currency)) ?>
                                <span class="paycard__period">/ <?= $accessDays ?>-day pass</span>
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
                          data-amount-paise="<?= (int) round($amount * 100) ?>"
                          data-currency="<?= e($currency) ?>"
                          data-site-name="<?= e($settings['site_name']) ?>"
                          data-amount-label="<?= e(money($amount, $currency)) ?>"
                          data-demo="<?= $demoMode ? '1' : '0' ?>">
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
                            <span class="btn__label">Pay <?= e(money($amount, $currency)) ?> &amp; unlock</span>
                        </button>

                        <p class="form__foot">
                            <?= icon('shield', 15) ?>
                            Your details are only used for the payment receipt. No spam, ever.
                        </p>
                    </form>
                </div>

                <div class="trust">
                    <span><?= icon('shield', 16) ?> 256-bit SSL</span>
                    <span><?= icon('zap', 16) ?> Instant delivery</span>
                    <?php if ($successCount >= 5): ?>
                        <span><?= icon('users', 16) ?> <?= $successCount ?> happy viewer<?= $successCount === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($videoId): ?>
<section class="section" id="inside">
    <div class="wrap section__inner">
        <header class="section__head">
            <span class="eyebrow">What you unlock</span>
            <h2>A private video, made for paying viewers</h2>
            <p>Unlisted on YouTube and embedded here for members only — no ad interruptions, no public comments, just the content.</p>
        </header>
        <div class="grid grid--3">
            <article class="feature">
                <span class="feature__icon"><?= icon('play', 20) ?></span>
                <h3>HD streaming</h3>
                <p>Plays directly in the page on mobile, tablet and desktop — no app to install.</p>
            </article>
            <article class="feature">
                <span class="feature__icon"><?= icon('clock', 20) ?></span>
                <h3><?= $accessDays ?>-day access</h3>
                <p>Revisit the video as often as you like during your access window.</p>
            </article>
            <article class="feature">
                <span class="feature__icon"><?= icon('shield', 20) ?></span>
                <h3>Safe payments</h3>
                <p>Razorpay handles the transaction. We never see your card or UPI details.</p>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section--muted" id="how">
    <div class="wrap section__inner">
        <header class="section__head">
            <span class="eyebrow">How it works</span>
            <h2>Three steps to watch</h2>
        </header>
        <ol class="steps">
            <li class="step">
                <span class="step__num">1</span>
                <h3>Enter your details</h3>
                <p>Name, e-mail and mobile number — used for your receipt and access recovery.</p>
            </li>
            <li class="step">
                <span class="step__num">2</span>
                <h3>Pay securely</h3>
                <p>Complete the <?= e(money($amount, $currency)) ?> payment in the Razorpay window.</p>
            </li>
            <li class="step">
                <span class="step__num">3</span>
                <h3>Watch &amp; subscribe</h3>
                <p>You are redirected to the video instantly. Subscribe to the channel for more.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section" id="faq">
    <div class="wrap section__inner section__inner--narrow">
        <header class="section__head">
            <span class="eyebrow">FAQ</span>
            <h2>Questions, answered</h2>
        </header>

        <details class="faq" open>
            <summary>How do I get the video after paying?</summary>
            <p>You are redirected to a private page the moment Razorpay confirms your payment. Bookmark it — it stays available on this device for <?= $accessDays ?> days.</p>
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
