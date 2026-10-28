<?php require_once '../config.php';
requireAdminLogin();

// Handle sorting
$sortColumn = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';
$allowedColumns = ['user_name', 'user_email', 'amount', 'status', 'created_at'];

if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'created_at';
}
$sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

// Fetch all payments sorted
$stmt = $pdo->query("SELECT * FROM payments ORDER BY $sortColumn $sortOrder");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Next sort order toggle for headers
function nextOrder($currentCol, $currentOrder, $thisCol) {
    if ($currentCol === $thisCol) {
        return $currentOrder === 'ASC' ? 'desc' : 'asc';
    }
    return 'asc';
}
function sortIcon($currentCol, $currentOrder, $thisCol) {
    if ($currentCol !== $thisCol) return '↕';
    return $currentOrder === 'ASC' ? '↑' : '↓';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .sidebar { background: #1a1a2e; min-height: 100vh; color: white; padding-top: 20px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin: 4px 12px; border-radius: 8px; transition: all 0.2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #16213e; color: white; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .main-content { padding: 30px; }
        .page-header { background: white; border-radius: 12px; padding: 20px 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        /* Zebra striping (Option A clean pattern) */
        .table-zebra tbody tr:nth-child(odd) { background-color: #ffffff; }
        .table-zebra tbody tr:nth-child(even) { background-color: #f8fafc; }
        .table-zebra tbody tr:hover { background-color: #eef2ff; }
        .table th { background: #1a1a2e; color: white; font-weight: 600; border: none; cursor: pointer; user-select: none; white-space: nowrap; }
        .table th a { color: white; text-decoration: none; display: block; }
        .table th a:hover { color: #c7d2fe; }
        .table td, .table th { padding: 14px 18px; vertical-align: middle; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .btn-view { background: #6366f1; color: white; border-radius: 8px; padding: 6px 14px; font-size: 0.9rem; text-decoration: none; display: inline-block; }
        .btn-view:hover { background: #4f46e5; color: white; }
        .logout-btn { position: absolute; top: 20px; right: 30px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="sidebar-header">
                    <h3 class="mb-0 fw-bold">Admin Panel</h3>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="bi bi-credit-card me-2"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <i class="bi bi-gear me-2"></i> Site Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" target="_blank" class="nav-link">
                            <i class="bi bi-house me-2"></i> View Site
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <a href="logout.php" class="btn btn-outline-danger logout-btn btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>

                <div class="page-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0 fw-bold text-gray-800">Payment Records</h2>
                    <span class="badge bg-primary fs-6"><?= count($payments) ?> Total Payments</span>
                </div>

                <div class="table-container">
                    <table class="table table-zebra mb-0">
                        <thead>
                            <tr>
                                <th><a href="?sort=user_name&order=<?= nextOrder($sortColumn, $sortOrder, 'user_name') ?>">Name <?= sortIcon($sortColumn, $sortOrder, 'user_name') ?></a></th>
                                <th><a href="?sort=user_email&order=<?= nextOrder($sortColumn, $sortOrder, 'user_email') ?>">Email <?= sortIcon($sortColumn, $sortOrder, 'user_email') ?></a></th>
                                <th><a href="?sort=user_mobile&order=asc">Mobile ↕</a></th>
                                <th><a href="?sort=amount&order=<?= nextOrder($sortColumn, $sortOrder, 'amount') ?>">Amount <?= sortIcon($sortColumn, $sortOrder, 'amount') ?></a></th>
                                <th><a href="?sort=status&order=<?= nextOrder($sortColumn, $sortOrder, 'status') ?>">Status <?= sortIcon($sortColumn, $sortOrder, 'status') ?></a></th>
                                <th><a href="?sort=created_at&order=<?= nextOrder($sortColumn, $sortOrder, 'created_at') ?>">Date <?= sortIcon($sortColumn, $sortOrder, 'created_at') ?></a></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No payments recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($payment['user_name']) ?></td>
                                        <td><?= htmlspecialchars($payment['user_email']) ?></td>
                                        <td><?= htmlspecialchars($payment['user_mobile']) ?></td>
                                        <td class="fw-bold">₹<?= htmlspecialchars($payment['amount']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $payment['status'] ?>">
                                                <?= ucfirst(htmlspecialchars($payment['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></td>
                                        <td>
                                            <a href="view_payment.php?id=<?= $payment['id'] ?>" class="btn-view">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
