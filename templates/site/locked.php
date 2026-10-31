<?php
/** @var array<string, mixed> $settings */
$amount = (float) $settings['amount'];
?>
<section class="section">
    <div class="wrap section__inner section__inner--narrow">
        <div class="card card--pad state state--locked">
            <span class="state__icon"><?= icon('lock', 32) ?></span>
            <h1>Your library is locked</h1>
            <p>
                This page shows the videos you bought, so it needs a verified payment — from this device or the private link we sent you.
                If you already paid, restore your access with the e-mail and mobile number used at checkout.
            </p>
            <div class="state__actions">
                <a class="btn btn--primary" href="<?= e(url('restore.php')) ?>">
                    <?= icon('refresh', 18) ?> Restore my access
                </a>
                <a class="btn btn--ghost" href="<?= e(url('')) ?>">
                    <?= icon('arrow-left', 18) ?> Buy access · <?= e(money($amount, $settings['currency'])) ?>
                </a>
            </div>
            <?php if (!empty($settings['support_email'])): ?>
                <p class="muted small">
                    Still stuck? <a href="mailto:<?= e($settings['support_email']) ?>"><?= e($settings['support_email']) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
