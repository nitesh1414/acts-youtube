<?php require_once '../config.php';
requireAdminLogin();

$settings = getSettings($pdo);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo = trim($_POST['logo']);
    $logoText = trim($_POST['logo_text']);
    $razorpayKeyId = trim($_POST['razorpay_key_id']);
    $razorpayKeySecret = trim($_POST['razorpay_key_secret']);
    $youtubeLink = trim($_POST['youtube_link']);
    $amount = trim($_POST['amount']);

    try {
        // Check if settings already exist
        $stmt = $pdo->query("SELECT id FROM settings LIMIT 1");
        if ($stmt->rowCount() > 0) {
            $update = $pdo->prepare("UPDATE settings SET logo=?, logo_text=?, razorpay_key_id=?, razorpay_key_secret=?, youtube_link=?, amount=? WHERE id=1");
            $update->execute([$logo, $logoText, $razorpayKeyId, $razorpayKeySecret, $youtubeLink, $amount]);
        } else {
            $insert = $pdo->prepare("INSERT INTO settings (logo, logo_text, razorpay_key_id, razorpay_key_secret, youtube_link, amount) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$logo, $logoText, $razorpayKeyId, $razorpayKeySecret, $youtubeLink, $amount]);
        }
        $message = "Settings saved successfully!";
        $messageType = 'success';
        $settings = getSettings($pdo); // Refresh settings
    } catch (Exception $e) {
        $message = "Error saving settings: " . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin Panel</title>
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
        .settings-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #374151; }
        .form-control, .form-control:focus { border-radius: 10px; padding: 11px 16px; border: 2px solid #e5e7eb; }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 0.25rem rgba(102,126,234,0.15); }
        .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; padding: 12px 30px; font-weight: 600; color: white; }
        .btn-save:hover { opacity: 0.9; color: white; }
        .logout-btn { position: absolute; top: 20px; right: 30px; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6; }
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
                        <a href="index.php" class="nav-link">
                            <i class="bi bi-credit-card me-2"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link active">
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

                <div class="page-header">
                    <h2 class="mb-0 fw-bold text-gray-800">Site Settings</h2>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mb-4">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="settings-card">
                    <form method="POST">
                        <!-- Branding Section -->
                        <div class="section-title"><i class="bi bi-brush me-2"></i> Branding</div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Logo URL</label>
                                <input type="text" name="logo" class="form-control" value="<?= htmlspecialchars($settings['logo'] ?? '') ?>" placeholder="https://example.com/logo.png">
                                <div class="form-text">Enter URL to your logo image (leave blank to use text).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logo Text / Site Name</label>
                                <input type="text" name="logo_text" class="form-control" value="<?= htmlspecialchars($settings['logo_text'] ?? 'PayAccess') ?>" required>
                            </div>
                        </div>

                        <!-- Payment Section -->
                        <div class="section-title"><i class="bi bi-credit-card-2-front me-2"></i> Razorpay Credentials</div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Razorpay Key ID</label>
                                <input type="text" name="razorpay_key_id" class="form-control" value="<?= htmlspecialchars($settings['razorpay_key_id'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Razorpay Key Secret</label>
                                <input type="password" name="razorpay_key_secret" class="form-control" value="<?= htmlspecialchars($settings['razorpay_key_secret'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label">Payment Amount (INR)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($settings['amount'] ?? '99') ?>" required>
                            </div>
                        </div>

                        <!-- Video Section -->
                        <div class="section-title"><i class="bi bi-youtube me-2"></i> YouTube Settings</div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label">YouTube Video Link (Unlisted or Private)</label>
                                <input type="text" name="youtube_link" class="form-control" value="<?= htmlspecialchars($settings['youtube_link'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=..." required>
                                <div class="form-text">Use an unlisted video link so only paying users can access it via this page.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-save">
                            <i class="bi bi-check-lg me-2"></i> Save All Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
