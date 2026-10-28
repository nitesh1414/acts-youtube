<?php
/** @var array<string, mixed>             $settings */
/** @var array<string, mixed>             $result */
/** @var array<string, string>            $filters */
/** @var string                           $sort */
/** @var string                           $direction */
/** @var array<string, mixed>             $totals */
$currency = (string) $settings['currency'];
$rows = $result['rows'];

$sortLink = function ($column) use ($sort, $direction) {
    $next = ($sort === $column && strtolower($direction) === 'asc') ? 'desc' : 'asc';
    $query = array_merge($_GET, ['sort' => $column, 'order' => $next, 'page' => 1]);
    unset($query['partial']);
    return '?' . http_build_query($query);
};
$sortIcon = function ($column) use ($sort, $direction) {
    if ($sort !== $column) {
        return '<span class="sort-off">↕</span>';
    }
    return strtolower($direction) === 'asc' ? '<span class="sort-on">↑</span>' : '<span class="sort-on">↓</span>';
};
$pageUrl = function ($page) {
    $query = array_merge($_GET, ['page' => $page]);
    unset($query['partial']);
    return '?' . http_build_query($query);
};
$exportUrl = url('admin/export.php') . '?' . http_build_query(array_filter([
    'q'      => $filters['q'],
    'status' => $filters['status'],
    'from'   => $filters['from'],
    'to'     => $filters['to'],
]));
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';
?>
<section class="card">
    <div class="filters">
        <form method="get" class="filters__form" role="search">
            <div class="field field--inline">
                <label class="sr-only" for="q">Search payments</label>
                <div class="field__control">
                    <?= icon('search', 17) ?>
                    <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, email, mobile, order or payment id">
                </div>
            </div>
            <div class="field field--inline">
                <label class="sr-only" for="status">Status</label>
                <select id="status" name="status" class="input">
                    <option value="">All statuses</option>
                    <?php foreach (['success', 'pending', 'failed'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field--inline">
                <label class="sr-only" for="from">From date</label>
                <input id="from" type="date" name="from" class="input" value="<?= e($filters['from']) ?>" title="From date">
            </div>
            <div class="field field--inline">
                <label class="sr-only" for="to">To date</label>
                <input id="to" type="date" name="to" class="input" value="<?= e($filters['to']) ?>" title="To date">
            </div>
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="order" value="<?= e($direction) ?>">
            <button type="submit" class="btn btn--primary btn--sm"><?= icon('filter', 15) ?> Filter</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/payments.php')) ?>">Reset</a>
            <?php endif; ?>
        </form>
        <a class="btn btn--ghost btn--sm" href="<?= e($exportUrl) ?>"><?= icon('download', 15) ?> Export CSV</a>
    </div>

    <div class="table-meta">
        <span>
            <strong><?= (int) $result['total'] ?></strong> record<?= (int) $result['total'] === 1 ? '' : 's' ?>
            <?= $hasFilters ? 'matching your filters' : 'in total' ?>
        </span>
        <span class="muted small">
            Successful: <strong><?= (int) $totals['success_count'] ?></strong> ·
            Revenue: <strong><?= e(money($totals['revenue'], $currency)) ?></strong>
        </span>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">
            <?= icon('search', 34) ?>
            <h3>No payments found</h3>
            <p><?= $hasFilters ? 'Try a different search term or clear the filters.' : 'Payments will appear here as soon as visitors check out.' ?></p>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/payments.php')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" id="payments-table">
                <thead>
                    <tr>
                        <th><a href="<?= e($sortLink('user_name')) ?>">Customer <?= $sortIcon('user_name') ?></a></th>
                        <th><a href="<?= e($sortLink('user_mobile')) ?>">Mobile <?= $sortIcon('user_mobile') ?></a></th>
                        <th><a href="<?= e($sortLink('amount')) ?>">Amount <?= $sortIcon('amount') ?></a></th>
                        <th><a href="<?= e($sortLink('status')) ?>">Status <?= $sortIcon('status') ?></a></th>
                        <th>Razorpay IDs</th>
                        <th><a href="<?= e($sortLink('created_at')) ?>">Date <?= $sortIcon('created_at') ?></a></th>
                        <th class="right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $payment): ?>
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
                            <td class="mono small"><?= e(display_mobile($payment['user_mobile'])) ?></td>
                            <td class="strong"><?= e(money_exact($payment['amount'], $payment['currency'] ?? 'INR')) ?></td>
                            <td><span class="badge badge--<?= e($payment['status']) ?>"><?= e(ucfirst($payment['status'])) ?></span></td>
                            <td class="mono tiny muted">
                                <?= e(!empty($payment['order_id']) ? $payment['order_id'] : '—') ?><br>
                                <?= e(!empty($payment['payment_id']) ? $payment['payment_id'] : '') ?>
                            </td>
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

        <?php if ((int) $result['pages'] > 1): ?>
            <nav class="pagination" aria-label="Payment pages">
                <a class="page-btn <?= (int) $result['page'] <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= (int) $result['page'] <= 1 ? '#' : e($pageUrl((int) $result['page'] - 1)) ?>">
                    <?= icon('arrow-left', 15) ?> Prev
                </a>

                <?php
                $total = (int) $result['pages'];
                $currentPage = (int) $result['page'];
                $start = max(1, $currentPage - 2);
                $end = min($total, $start + 4);
                $start = max(1, $end - 4);
                for ($page = $start; $page <= $end; $page++):
                    ?>
                    <a class="page-btn <?= $page === $currentPage ? 'is-active' : '' ?>" href="<?= e($pageUrl($page)) ?>"><?= $page ?></a>
                <?php endfor; ?>

                <a class="page-btn <?= (int) $result['page'] >= (int) $result['pages'] ? 'is-disabled' : '' ?>"
                   href="<?= (int) $result['page'] >= (int) $result['pages'] ? '#' : e($pageUrl((int) $result['page'] + 1)) ?>">
                    Next <?= icon('arrow-right', 15) ?>
                </a>
                <span class="pagination__meta">Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
