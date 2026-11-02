<?php
/** @var array<string, mixed> $settings */
$amount = (float) $settings['amount'];
?>
<section class="section">
    <div class="wrap section__inner section__inner--narrow">
        <div class="restore-grid">
            <div class="restore-copy">
                <span class="eyebrow"><?= icon('refresh', 15) ?> Access recovery</span>
                <h1>Restore your access</h1>
                <p>
                    Enter the <strong>mobile number and name</strong> you used at checkout.
                    If we find a successful payment, your private library link is re-issued instantly —
                    no extra charge. No e-mail or password needed.
                </p>
                <ul class="checklist">
                    <li><?= icon('check', 16) ?> Works on any device or browser</li>
                    <li><?= icon('check', 16) ?> <?= (int) $settings['access_days'] > 0
                        ? 'Access stays valid for ' . (int) $settings['access_days'] . ' days from your payment'
                        : 'Your access does not expire' ?></li>
                    <li><?= icon('check', 16) ?> No password to remember</li>
                </ul>
                <p class="muted small">
                    Didn't buy yet?
                    <a href="<?= e(url('')) ?>">Get access for <?= e(money($amount, $settings['currency'])) ?></a>.
                </p>
            </div>

            <div class="card card--pad">
                <?php if (!empty($error)): ?>
                    <div class="alert alert--danger">
                        <?= icon('alert', 18) ?>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="form" novalidate>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="mobile">Mobile number</label>
                        <div class="field__control">
                            <?= icon('phone', 18) ?>
                            <input id="mobile" name="mobile" type="tel" autocomplete="tel" inputmode="tel" required
                                   maxlength="20" value="<?= e(old('mobile')) ?>" placeholder="98765 43210">
                        </div>
                    </div>
                    <div class="field">
                        <label for="name">Full name <span class="field__optional">as you paid</span></label>
                        <div class="field__control">
                            <?= icon('user', 18) ?>
                            <input id="name" name="name" type="text" autocomplete="name" required
                                   maxlength="120" value="<?= e(old('name')) ?>" placeholder="Your full name">
                        </div>
                    </div>
                    <div class="field">
                        <label for="email">Email address <span class="field__optional">optional</span></label>
                        <div class="field__control">
                            <?= icon('mail', 18) ?>
                            <input id="email" name="email" type="email" autocomplete="email"
                                   maxlength="190" value="<?= e(old('email')) ?>" placeholder="you@example.com (not required)">
                        </div>
                        <p class="hint">Only if you entered one at checkout — it just speeds up matching.</p>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        <?= icon('refresh', 18) ?> Restore access
                    </button>
                    <p class="form__foot">
                        <?= icon('shield', 15) ?>
                        For security, repeated attempts are rate limited.
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>
