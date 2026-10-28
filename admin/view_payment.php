<?php require_once '../config.php';
requireAdminLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details #<?= $payment['id'] ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .sidebar { background: #1a1a2e; min-height: 100vh; color: white; padding-top: 20px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin: 4px 12px; border-radius: 8px; transition: all 0.2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #16213e; color: white; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .main-content { padding: 30px; }
        .page-header { background: white; border-radius: 12px; padding: 20px 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .detail-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .detail-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px 30px; }
        .detail-row { display: flex; justify-content: space-between; padding: 18px 30px; border-bottom: 1px solid #f3f4f6; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #6b7280; }
        .detail-value { font-weight: 500; color: #111827; text-align: right; word-break: break-all; max-width: 60%; }
        .logout-btn { position: absolute; top: 20px; right: 30px; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .status-success { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
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
                    <h2 class="mb-0 fw-bold text-gray-800">Payment Details #<?= $payment['id'] ?></h2>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Back to All Payments
                    </a>
                </div>

                <div class="detail-card">
                    <div class="detail-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 fw-bold"><?= htmlspecialchars($payment['user_name']) ?></h3>
                            <p class="mb-0 opacity-75"><?= htmlspecialchars($payment['user_email']) ?></p>
                        </div>
                        <span class="status-badge status-<?= $payment['status'] ?>" style="font-size: 1rem; padding: 8px 20px;">
                            <?= ucfirst(htmlspecialchars($payment['status'])) ?>
                        </span>
                    </div>
                    <div>
                        <div class="detail-row">
                            <span class="detail-label">Full Name</span>
                            <span class="detail-value"><?= htmlspecialchars($payment['user_name']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email Address</span>
                            <span class="detail-value"><?= htmlspecialchars($payment['user_email']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Mobile Number</span>
                            <span class="detail-value"><?= htmlspecialchars($payment['user_mobile']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Amount Paid</span>
                            <span class="detail-value fw-bold fs-5">₹<?= htmlspecialchars($payment['amount']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Razorpay Order ID</span>
                            <span class="detail-value font-monospace small"><?= htmlspecialchars($payment['order_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Razorpay Payment ID</span>
                            <span class="detail-value font-monospace small"><?= htmlspecialchars($payment['payment_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Transaction Date & Time</span>
                            <span class="detail-value"><?= date('F j, Y, g:i a', strtotime($payment['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
