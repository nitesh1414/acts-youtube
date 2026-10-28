<?php
// Installation Script - Run this once, then delete it!
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS pay_access_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green;'>✓ Database 'pay_access_db' created successfully (or already exists).</p>";

    // Select the database
    $pdo->exec("USE pay_access_db");

    // Create Settings Table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logo VARCHAR(500),
        logo_text VARCHAR(255) DEFAULT 'PayAccess',
        razorpay_key_id VARCHAR(255),
        razorpay_key_secret VARCHAR(255),
        youtube_link VARCHAR(500),
        amount DECIMAL(10,2) DEFAULT 99.00
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ Settings table created.</p>";

    // Create Payments Table
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(255) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        user_mobile VARCHAR(20) NOT NULL,
        order_id VARCHAR(255),
        payment_id VARCHAR(255),
        amount DECIMAL(10,2),
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ Payments table created.</p>";

    // Insert default settings if not exists
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("INSERT INTO settings (logo_text, amount) VALUES ('PayAccess', 99.00)");
        echo "<p style='color:green;'>✓ Default settings inserted.</p>";
    }

    echo "<hr>";
    echo "<h3 style='color:green; font-family:sans-serif;'>Installation Complete!</h3>";
    echo "<p style='font-family:sans-serif;'>You can now:</p>";
    echo "<ol style='font-family:sans-serif;'>";
    echo "<li>Go to the <a href='index.php'>Main Site</a></li>";
    echo "<li>Go to <a href='admin/index.php'>Admin Panel</a> (Default credentials: admin / admin123)</li>";
    echo "<li><strong>Important:</strong> Delete this <code>install.php</code> file for security reasons.</li>";
    echo "</ol>";

} catch (PDOException $e) {
    die("<p style='color:red;'>Installation Error: " . $e->getMessage() . "</p>");
}
?>
