<?php
/** @var array<string, mixed> $payment */
/** @var array<string, mixed> $settings */
$currency = (string) $payment['currency'];
?>
<section class="section">
    <div class="wrap section__inner section__inner--narrow">
        <div class="demo-banner">
            <?= icon('info', 18) ?>
            <span><strong>Demo mode</strong> · this checkout is simulated. No money is charged and no Razorpay account is used.</span>
        </div>

        <div class="card card--pad demo-card">
            <header class="demo-card__head">
                <div>
                    <p class="muted small">Paying</p>
                    <h1><?= e(money_exact($payment['amount'], $currency)) ?></h1>
                </div>
                <span class="chip"><?= icon('lock', 14) ?> Demo</span>
            </header>

            <dl class="detail-list">
                <div><dt>Customer</dt><dd><?= e($payment['user_name']) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= e($payment['user_email']) ?></dd></div>
                <div><dt>Mobile</dt><dd><?= e(display_mobile($payment['user_mobile'])) ?></dd></div>
                <div><dt>Order ID</dt><dd class="mono small"><?= e($payment['order_id']) ?></dd></div>
                <div><dt>Status</dt><dd><span class="badge badge--<?= e($payment['status']) ?>"><?= e(ucfirst($payment['status'])) ?></span></dd></div>
            </dl>

            <div class="demo-actions">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order" value="<?= e($payment['order_id']) ?>">
                    <input type="hidden" name="action" value="success">
                    <button type="submit" class="btn btn--primary btn--block"><?= icon('check', 18) ?> Simulate successful payment</button>
                </form>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order" value="<?= e($payment['order_id']) ?>">
                    <input type="hidden" name="action" value="fail">
                    <button type="submit" class="btn btn--ghost btn--block"><?= icon('x', 18) ?> Simulate failed payment</button>
                </form>
            </div>
        </div>
    </div>
</section>
