<?php
session_start();

// Database Configuration - Update these with your hosting details
define('DB_HOST', 'localhost');
define('DB_NAME', 'pay_access_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Admin Credentials (Default)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Site URL (update with your domain, e.g., https://yourdomain.com/)
define('SITE_URL', 'http://localhost/');

// Connect to MySQL Database using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . ". Please run install.php first if you haven't already.");
}

// Function to fetch site settings
function getSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        return [
            'logo' => '',
            'logo_text' => 'PayAccess',
            'razorpay_key_id' => '',
            'razorpay_key_secret' => '',
            'youtube_link' => '',
            'amount' => '99.00'
        ];
    }
    return $settings;
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Redirect if not logged in
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}
?>
