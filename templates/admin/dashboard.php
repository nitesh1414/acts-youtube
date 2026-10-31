<?php
/** @var array<string, mixed>               $settings */
/** @var array<string, mixed>               $stats */
/** @var array<int, array<string, mixed>>   $chart */
/** @var array<int, array<string, mixed>>   $recent */
/** @var array{videos:int, bundles:int}      $library */
$currency = (string) $settings['currency'];
$maxDay = 0.0;
foreach ($chart as $day) {
    $maxDay = max($maxDay, (float) $day['total']);
}
$razorpayReady = app()->settings->isRazorpayConfigured();
$logo = app()->settings->logoUrl();
$chartTotal = array_sum(array_column($chart, 'total'));
$lifetimeDefault = (int) $settings['access_days'] === 0;
?>
<?php if (!$razorpayReady && !(int) $settings['demo_mode']): ?>
    <div class="alert alert--warning alert--lg">
        <?= icon('alert', 20) ?>
        <div>
            <strong>Payments are not live yet.</strong>
            <p>Add your Razorpay Key ID and Key Secret so visitors can pay. Until then the pay button stays disabled.</p>
        </div>
        <a class="btn btn--primary btn--sm" href="<?= e(url('admin/settings.php#payments')) ?>"><?= icon('key', 16) ?> Add keys</a>
    </div>
<?php endif; ?>

<?php if ((int) $settings['demo_mode']): ?>
    <div class="alert alert--info">
        <?= icon('info', 18) ?>
        <span><strong>Demo mode is enabled.</strong> Visitors can simulate payments without being charged. Turn it off in <a href="<?= e(url('admin/settings.php#payments')) ?>">settings</a> before sharing your page.</span>
    </div>
<?php endif; ?>

<section class="stat-grid">
    <article class="stat">
        <div class="stat__top">
            <span class="stat__label">Total revenue</span>
            <span class="stat__icon stat__icon--indigo"><?= icon('wallet', 18) ?></span>
        </div>
        <p class="stat__value"><?= e(money($stats['revenue'], $currency)) ?></p>
        <p class="stat__hint">All successful payments</p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__label">Today</span>
            <span class="stat__icon stat__icon--emerald"><?= icon('trending-up', 18) ?></span>
        </div>
        <p class="stat__value"><?= e(money($stats['revenue_today'], $currency)) ?></p>
        <p class="stat__hint"><?= e(money($stats['revenue_month'], $currency)) ?> in the last 30 days</p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__label">Successful payments</span>
            <span class="stat__icon stat__icon--sky"><?= icon('check-circle', 18) ?></span>
        </div>
        <p class="stat__value"><?= (int) $stats['success_count'] ?></p>
        <p class="stat__hint">
            <?= (int) $stats['pending_count'] ?> pending · <?= (int) $stats['failed_count'] ?> failed
            <?php if ($stats['total'] > 0): ?>· <?= e($stats['conversion']) ?>% conversion<?php endif; ?>
        </p>
    </article>

    <article class="stat">
        <div class="stat__top">
            <span class="stat__label">Active access passes</span>
            <span class="stat__icon stat__icon--amber"><?= icon('lock', 18) ?></span>
        </div>
        <p class="stat__value"><?= (int) $stats['active_access'] ?></p>
        <p class="stat__hint"><?= (int) $stats['customers'] ?> unique paying customers</p>
    </article>
</section>

<section class="admin-grid">
    <article class="card card--pad chart-card">
        <header class="card__head">
            <div>
                <h2>Revenue · last 14 days</h2>
                <p class="muted small">Successful payments per day</p>
            </div>
            <span class="chip"><?= e(money($chartTotal, $currency)) ?></span>
        </header>

        <div class="chart" role="img" aria-label="Daily revenue for the last 14 days">
            <?php foreach ($chart as $day): ?>
                <?php $height = $maxDay > 0 ? max(2, round(((float) $day['total'] / $maxDay) * 100)) : 2; ?>
                <div class="chart__col" title="<?= e($day['label']) ?> — <?= e(money($day['total'], $currency)) ?> (<?= (int) $day['count'] ?> payments)">
                    <div class="chart__bar <?= (float) $day['total'] > 0 ? 'is-filled' : '' ?>" style="height: <?= (int) $height ?>%"></div>
                    <span class="chart__label"><?= e(substr($day['label'], 0, 2)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($chartTotal <= 0): ?>
            <p class="muted small center">No successful payments in this period yet.</p>
        <?php endif; ?>
    </article>

    <aside class="card card--pad store-card">
        <header class="card__head">
            <h2>Store status</h2>
            <a class="link small" href="<?= e(url('admin/settings.php')) ?>">Edit</a>
        </header>

        <div class="store-card__logo">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="Current logo">
            <?php else: ?>
                <?= brand_mark(48) ?>
            <?php endif; ?>
            <div>
                <strong><?= e($settings['logo_text']) ?></strong>
                <p class="muted small"><?= $logo !== '' ? 'Custom logo uploaded' : 'Using default brand mark' ?></p>
            </div>
        </div>

        <dl class="mini-list">
            <div><dt>Price</dt><dd class="strong"><?= e(money($settings['amount'], $currency)) ?></dd></div>
            <div><dt>Access window</dt>
                <dd><?= $lifetimeDefault ? 'No expiry' : ((int) $settings['access_days'] . ' days') ?></dd>
            </div>
            <div><dt>Razorpay</dt>
                <dd>
                    <span class="badge <?= $razorpayReady ? 'badge--success' : 'badge--warning' ?>">
                        <?= $razorpayReady ? 'Connected' : 'Not configured' ?>
                    </span>
                </dd>
            </div>
            <div><dt>Videos on sale</dt>
                <dd>
                    <a class="link" href="<?= e(url('admin/videos.php')) ?>"><?= (int) $library['videos'] ?> of
                        <?= count(app()->catalog->videos(false)) ?></a>
                </dd>
            </div>
            <div><dt>Bundles on sale</dt>
                <dd><a class="link" href="<?= e(url('admin/bundles.php')) ?>"><?= (int) $library['bundles'] ?></a></dd>
            </div>
            <div><dt>Demo mode</dt>
                <dd><span class="badge <?= (int) $settings['demo_mode'] ? 'badge--info' : 'badge--muted' ?>"><?= (int) $settings['demo_mode'] ? 'On' : 'Off' ?></span></dd>
            </div>
        </dl>

        <div class="save-bar__actions">
            <a class="btn btn--primary btn--block btn--sm" href="<?= e(url('admin/video.php')) ?>">
                <?= icon('upload', 16) ?> Add a video
            </a>
            <a class="btn btn--ghost btn--block btn--sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener">
                <?= icon('external-link', 16) ?> Open public page
            </a>
        </div>
    </aside>
</section>

<section class="card">
    <header class="card__head card__head--pad">
        <div>
            <h2>Recent payments</h2>
            <p class="muted small">The latest activity on your checkout</p>
        </div>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/payments.php')) ?>">View all <?= icon('arrow-right', 15) ?></a>
    </header>

    <?php if (empty($recent)): ?>
        <div class="empty">
            <?= icon('credit-card', 34) ?>
            <h3>No payments yet</h3>
            <p>Share your page and the first transaction will appear here.</p>
            <a class="btn btn--primary btn--sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener">
                <?= icon('external-link', 16) ?> View public page
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $payment): ?>
                        <tr>
                            <td>
                                <div class="person">
                                    <span class="avatar"><?= e(initials($payment['user_name'])) ?></span>
                                    <div>
                                        <strong><?= e($payment['user_name']) ?></strong>
                                        <span class="muted small"><?= e($payment['user_email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="strong"><?= e(money_exact($payment['amount'], $payment['currency'] ?? 'INR')) ?></td>
                            <td><span class="badge badge--<?= e($payment['status']) ?>"><?= e(ucfirst($payment['status'])) ?></span></td>
                            <td class="muted small"><?= e(date_display($payment['created_at'])) ?></td>
                            <td class="right">
                                <a class="btn btn--ghost btn--xs" href="<?= e(url('admin/payment.php?id=' . (int) $payment['id'])) ?>">
                                    <?= icon('eye', 15) ?> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
