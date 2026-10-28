<?php require_once 'config.php';
$settings = getSettings($pdo);

// Handle admin login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin/index.php");
        exit;
    } else {
        $loginError = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['logo_text']) ?> - Get Exclusive Access</title>
    
    <!-- Bootstrap 5 (Option A Clean Professional Template) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            backdrop-filter: blur(10px);
        }
        .admin-login-card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-admin {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
            border-radius: 50px;
        }
        .price-tag {
            background: #fff3cd;
            color: #856404;
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.2rem;
            display: inline-block;
        }
        .logo-img {
            max-height: 70px;
            object-fit: contain;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-4">

    <!-- Main User Form Card -->
    <div class="card main-card col-12 col-md-8 col-lg-5">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?= htmlspecialchars($settings['logo']) ?>" alt="Logo" class="logo-img mb-3">
                <?php else: ?>
                    <h1 class="h1 fw-bold text-primary mb-0"><?= htmlspecialchars($settings['logo_text']) ?></h1>
                <?php endif; ?>
                <p class="text-muted mt-3 mb-1">Get instant access to exclusive content</p>
                <div class="price-tag mt-2">Only ₹<?= htmlspecialchars($settings['amount']) ?></div>
            </div>

            <form id="paymentForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" class="form-control" id="name" required placeholder="Enter your full name">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" id="email" required placeholder="you@example.com">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Mobile Number</label>
                    <input type="tel" class="form-control" id="mobile" required placeholder="+91 98765 43210">
                </div>
                <button type="submit" class="btn btn-primary w-100" id="payBtn">
                    Pay ₹<?= htmlspecialchars($settings['amount']) ?> & Unlock
                </button>
            </form>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <button type="button" class="btn btn-dark btn-admin shadow-lg" data-bs-toggle="modal" data-bs-target="#adminModal">
        🔐 Admin Login
    </button>

    <div class="modal fade" id="adminModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content admin-login-card">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Admin Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <?php if ($loginError): ?>
                        <div class="alert alert-danger"><?= $loginError ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="admin_login" value="1">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const settings = <?= json_encode($settings) ?>;
        
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const mobile = document.getElementById('mobile').value;
            const payBtn = document.getElementById('payBtn');
            payBtn.disabled = true;
            payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            try {
                const res = await fetch('api/create_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name, email, mobile})
                });
                const data = await res.json();

                if (data.error) {
                    alert('Error: ' + data.error);
                    payBtn.disabled = false;
                    payBtn.innerHTML = 'Pay ₹' + settings.amount + ' & Unlock';
                    return;
                }

                const options = {
                    key: settings.razorpay_key_id,
                    amount: data.amount,
                    currency: "INR",
                    name: settings.logo_text,
                    description: "Payment for Exclusive Content",
                    order_id: data.order_id,
                    handler: async function (response) {
                        const verifyRes = await fetch('api/verify_payment.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                                payment_id: data.payment_db_id
                            })
                        });
                        if (verifyRes.ok) {
                            window.location.href = 'success.php';
                        } else {
                            const err = await verifyRes.json();
                            alert('Payment verification failed: ' + (err.error || 'Unknown error'));
                        }
                    },
                    prefill: { name, email, contact: mobile },
                    theme: { color: "#667eea" },
                    modal: {
                        ondismiss: function() {
                            payBtn.disabled = false;
                            payBtn.innerHTML = 'Pay ₹' + settings.amount + ' & Unlock';
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.open();
            } catch (err) {
                alert('Something went wrong: ' + err.message);
                payBtn.disabled = false;
                payBtn.innerHTML = 'Pay ₹' + settings.amount + ' & Unlock';
            }
        });

        // Show admin modal if there was a login error
        <?php if ($loginError): ?>
            new bootstrap.Modal(document.getElementById('adminModal')).show();
        <?php endif; ?>

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch(err => console.log('SW registration failed:', err));
            });
        }
    </script>
</body>
</html>
