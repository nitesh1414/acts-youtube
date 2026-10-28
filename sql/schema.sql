-- =============================================================================
-- PayAccess — MySQL schema (MySQL 5.7+ / MariaDB 10.3+)
-- Generated from lib/schema.php by tools/build-schema.php — do not edit by hand.
--
-- Fresh install: `install.php` does all of this for you.
-- Manual install: import this file into an empty utf8mb4 database.
-- Existing (v1) database: open `update.php` instead — it adds what is missing
-- without touching your data.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `site_name` VARCHAR(120) NOT NULL DEFAULT 'PayAccess',
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `logo_text` VARCHAR(120) NOT NULL DEFAULT 'PayAccess',
    `tagline` VARCHAR(190) NOT NULL DEFAULT 'Get instant access to our exclusive video',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 199.00,
    `currency` CHAR(3) NOT NULL DEFAULT 'INR',
    `razorpay_key_id` VARCHAR(120) DEFAULT NULL,
    `razorpay_key_secret` VARCHAR(190) DEFAULT NULL,
    `razorpay_webhook_secret` VARCHAR(190) DEFAULT NULL,
    `youtube_video_url` VARCHAR(255) DEFAULT NULL,
    `youtube_channel_url` VARCHAR(255) DEFAULT NULL,
    `support_email` VARCHAR(190) DEFAULT NULL,
    `support_phone` VARCHAR(30) DEFAULT NULL,
    `access_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `demo_mode` TINYINT(1) NOT NULL DEFAULT 0,
    `theme_color` CHAR(7) NOT NULL DEFAULT '#4f46e5',
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(32) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_name` VARCHAR(120) NOT NULL,
    `user_email` VARCHAR(190) NOT NULL,
    `user_mobile` VARCHAR(20) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'INR',
    `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    `order_id` VARCHAR(64) DEFAULT NULL,
    `payment_id` VARCHAR(64) DEFAULT NULL,
    `signature` VARCHAR(191) DEFAULT NULL,
    `method` VARCHAR(32) DEFAULT NULL,
    `access_token` CHAR(64) DEFAULT NULL,
    `access_expires_at` DATETIME DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_payments_order` (`order_id`),
    UNIQUE KEY `uniq_payments_payment` (`payment_id`),
    UNIQUE KEY `uniq_payments_access` (`access_token`),
    KEY `idx_payments_status_date` (`status`, `created_at`),
    KEY `idx_payments_email` (`user_email`),
    KEY `idx_payments_mobile` (`user_mobile`),
    KEY `idx_payments_access_end` (`access_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` INT UNSIGNED DEFAULT NULL,
    `event` VARCHAR(50) NOT NULL,
    `message` VARCHAR(255) DEFAULT NULL,
    `payload` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_payment` (`payment_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bucket` VARCHAR(40) NOT NULL,
    `identifier` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rate_lookup` (`bucket`, `identifier`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings row (ignored when it already exists).
INSERT IGNORE INTO `settings`
    (`id`, `site_name`, `logo_text`, `tagline`, `amount`, `currency`, `access_days`, `theme_color`, `updated_at`)
VALUES (1, 'PayAccess', 'PayAccess', 'Get instant access to our exclusive video', 199.00, 'INR', 30, '#4f46e5', NOW());
