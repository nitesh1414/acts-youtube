<?php
/** @var array<string, mixed>             $settings */
/** @var array<string, mixed>             $payment */
/** @var array<int, array<string, mixed>> $events */
/** @var string                           $accessLink */
$currency = isset($payment['currency']) && $payment['currency'] !== '' ? (string) $payment['currency'] : 'INR';
$token = isset($payment['access_token']) ? (string) $payment['access_token'] : '';
$accessValid = Payments::accessIsValid($payment);
$daysLeft = access_days_left($payment);
?>
<a class="link small back-link" href="<?= e(url('admin/payments.php')) ?>"><?= icon('arrow-left', 15) ?> All payments</a>

<section class="detail-hero card">
    <div class="detail-hero__main">
        <span class="avatar avatar--lg"><?= e(initials($payment['user_name'])) ?></span>
        <div>
            <h2><?= e($payment['user_name']) ?></h2>
            <p class="muted"><?= e($payment['user_email']) ?> · <?= e(display_mobile($payment['user_mobile'])) ?></p>
        </div>
    </div>
    <div class="detail-hero__side">
        <span class="badge badge--<?= e($payment['status']) ?>"><?= e(ucfirst($payment['status'])) ?></span>
        <p class="detail-hero__amount"><?= e(money_exact($payment['amount'], $currency)) ?></p>
        <p class="muted small">Payment #<?= (int) $payment['id'] ?></p>
    </div>
</section>

<div class="detail-grid">
    <article class="card card--pad">
        <header class="card__head"><h2>Transaction details</h2></header>
        <dl class="detail-list">
            <div><dt>Payment ID</dt>
                <dd class="mono">
                    <?= e(!empty($payment['payment_id']) ? $payment['payment_id'] : '—') ?>
                    <?php if ($payment['payment_id']): ?>
                        <button type="button" class="icon-btn icon-btn--xs" data-copy="<?= e($payment['payment_id']) ?>" title="Copy"><?= icon('copy', 14) ?></button>
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Order ID</dt>
                <dd class="mono">
                    <?= e(!empty($payment['order_id']) ? $payment['order_id'] : '—') ?>
                    <?php if ($payment['order_id']): ?>
                        <button type="button" class="icon-btn icon-btn--xs" data-copy="<?= e($payment['order_id']) ?>" title="Copy"><?= icon('copy', 14) ?></button>
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Method</dt><dd><?= e(!empty($payment['method']) ? $payment['method'] : '—') ?></dd></div>
            <div><dt>Created</dt><dd><?= e(date_display($payment['created_at'])) ?></dd></div>
            <div><dt>Paid at</dt><dd><?= e(date_display($payment['paid_at'])) ?></dd></div>
            <div><dt>IP address</dt><dd class="mono small"><?= e(!empty($payment['ip_address']) ? $payment['ip_address'] : '—') ?></dd></div>
            <div><dt>Device</dt><dd class="tiny muted"><?= e(text_limit(!empty($payment['user_agent']) ? $payment['user_agent'] : '—', 90)) ?></dd></div>
            <?php if (!empty($payment['notes'])): ?>
                <div><dt>Notes</dt><dd class="small"><?= e($payment['notes']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </article>

    <aside class="card card--pad">
        <header class="card__head">
            <h2>Video access</h2>
            <span class="badge <?= $accessValid ? 'badge--success' : 'badge--muted' ?>">
                <?= $accessValid ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : 'No active access' ?>
            </span>
        </header>

        <?php if ($payment['status'] === 'success'): ?>
            <p class="muted small">
                Share this private link with the customer (or use it yourself to test). It works until
                <strong><?= e(date_display($payment['access_expires_at'])) ?></strong>.
            </p>
            <div class="copy-row">
                <input class="input mono small" type="text" readonly value="<?= e($accessLink) ?>" data-select-on-focus aria-label="Private access link">
                <button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($accessLink) ?>"><?= icon('copy', 15) ?> Copy</button>
            </div>

            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regenerate">
                <div class="field">
                    <label for="days">Extend access by</label>
                    <select class="input" id="days" name="days">
                        <?php foreach ([7, 30, 90, 365] as $days): ?>
                            <option value="<?= $days ?>" <?= (int) $settings['access_days'] === $days ? 'selected' : '' ?>><?= $days ?> days</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--primary btn--block btn--sm">
                    <?= icon('refresh', 15) ?> Regenerate access link
                </button>
                <p class="tiny muted">Generating a new link immediately invalidates the previous one.</p>
            </form>
        <?php else: ?>
            <p class="muted small">Access is only granted after a successful payment. This record is <strong><?= e($payment['status']) ?></strong>.</p>
        <?php endif; ?>
    </aside>
</div>

<section class="card card--pad">
    <header class="card__head">
        <h2>Payment timeline</h2>
        <span class="muted small"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></span>
    </header>

    <?php if (empty($events)): ?>
        <p class="muted small">No events recorded for this payment.</p>
    <?php else: ?>
        <ol class="timeline">
            <?php foreach ($events as $event): ?>
                <li class="timeline__item">
                    <span class="timeline__dot"></span>
                    <div>
                        <p class="timeline__title"><?= e($event['event']) ?></p>
                        <?php if (!empty($event['message'])): ?>
                            <p class="muted small"><?= e($event['message']) ?></p>
                        <?php endif; ?>
                        <time class="tiny muted"><?= e(date_display($event['created_at'])) ?></time>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
