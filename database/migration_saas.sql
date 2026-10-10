-- =======================================================
-- Fitisify Fitness Gym - Multi-Tenant SaaS & Subscription Migration
-- =======================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Subscription Plans Table (Enhanced for SaaS & Rental Model)
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `billing_cycle` enum('trial','monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `trial_days` int(11) NOT NULL DEFAULT 14,
  `grace_period_days` int(11) NOT NULL DEFAULT 7,
  `max_members` int(11) NOT NULL DEFAULT 100,
  `max_staff` int(11) NOT NULL DEFAULT 10,
  `max_branches` int(11) NOT NULL DEFAULT 1,
  `storage_limit_mb` int(11) NOT NULL DEFAULT 1024,
  `features` text DEFAULT NULL, -- JSON array of enabled features
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default SaaS subscription tiers
INSERT IGNORE INTO `subscription_plans` (`id`, `name`, `slug`, `billing_cycle`, `price_monthly`, `price_yearly`, `trial_days`, `grace_period_days`, `max_members`, `max_staff`, `max_branches`, `storage_limit_mb`, `features`, `is_active`) VALUES
(1, 'Free Trial (4 Days)', 'free-trial-4-days', 'trial', 0.00, 0.00, 4, 2, 50, 3, 1, 500, '["members","attendance","payments","basic_reports"]', 1),
(2, 'Basic Monthly', 'basic-monthly', 'monthly', 29.00, 290.00, 0, 5, 100, 5, 1, 1024, '["members","attendance","payments","basic_reports","expenses"]', 1),
(3, 'Standard Pro', 'standard-pro', 'monthly', 69.00, 690.00, 0, 7, 350, 15, 2, 5120, '["members","attendance","payments","workouts","diet","classes","reports","expenses","staff","branding"]', 1),
(4, 'Premium Quarterly', 'premium-quarterly', 'quarterly', 189.00, 690.00, 0, 10, 800, 30, 4, 10240, '["members","attendance","payments","workouts","diet","classes","reports","expenses","staff","branding","branches","sms_reminders"]', 1),
(5, 'Yearly Powerhouse', 'yearly-powerhouse', 'yearly', 649.00, 649.00, 0, 15, 2000, 80, 10, 25600, '["members","attendance","payments","workouts","diet","classes","reports","expenses","staff","branding","branches","sms_reminders","api_access","custom_domain"]', 1),
(6, 'Custom Enterprise', 'custom-enterprise', 'custom', 999.00, 9990.00, 0, 30, 10000, 500, 50, 102400, '["all_features","dedicated_support","custom_integrations"]', 1);

-- 2. Tenants (Gyms) Table with Lifecycle & Tax Fields
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gym_name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL UNIQUE,
  `owner_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `subscription_plan_id` int(11) NOT NULL DEFAULT 3,
  `subscription_start` date NOT NULL,
  `subscription_expiry` date NOT NULL,
  `grace_period_end` date DEFAULT NULL,
  `status` enum('trial','active','expiring_soon','grace_period','expired','suspended','cancelled') NOT NULL DEFAULT 'active',
  `currency` varchar(10) NOT NULL DEFAULT '$',
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Kathmandu',
  `primary_color` varchar(20) NOT NULL DEFAULT '#3b82f6',
  `secondary_color` varchar(20) NOT NULL DEFAULT '#10b981',
  `tax_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_number` varchar(50) DEFAULT NULL,
  `invoice_header` text DEFAULT NULL,
  `invoice_footer` text DEFAULT 'Thank you for training with us!',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tenants` (`id`, `gym_name`, `slug`, `owner_name`, `email`, `phone`, `address`, `subscription_plan_id`, `subscription_start`, `subscription_expiry`, `status`, `currency`, `timezone`) VALUES
(1, 'Fitisify Fitness Gym - Main', 'fitisify-main', 'Gym Owner', 'admin@fitisify.com', '1234567890', '123 Fitness Boulevard, Downtown', 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', '$', 'Asia/Kathmandu');

-- 3. SaaS Subscription Payments & Invoices (Rental Transactions)
CREATE TABLE IF NOT EXISTS `saas_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `billing_cycle` varchar(50) NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'manual',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','failed') NOT NULL DEFAULT 'approved',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Subscription Coupons & Discounts Table
CREATE TABLE IF NOT EXISTS `saas_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_fixed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_uses` int(11) NOT NULL DEFAULT 100,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `saas_coupons` (`id`, `code`, `discount_percent`, `max_uses`, `is_active`) VALUES
(1, 'WELCOME20', 20.00, 500, 1),
(2, 'FITNESS50', 50.00, 100, 1);

-- 5. Branches Table
CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `branches` (`id`, `tenant_id`, `branch_name`, `address`, `phone`, `email`, `is_main`, `status`) VALUES
(1, 1, 'Main Branch', '123 Fitness Boulevard', '1234567890', 'main@fitisify.com', 1, 'active');

-- 6. Unified Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `role` enum('super_admin','gym_admin','staff','trainer','member') NOT NULL DEFAULT 'member',
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `status` enum('active','inactive','pending','suspended') NOT NULL DEFAULT 'active',
  `member_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users` (`id`, `tenant_id`, `branch_id`, `role`, `username`, `password`, `email`, `fullname`, `phone`, `status`) VALUES
(1, NULL, NULL, 'super_admin', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin@fitisify.com', 'Master Platform Admin', '0000000000', 'active'),
(2, 1, 1, 'gym_admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@fitisify.com', 'Gym Administrator', '8520281200', 'active');

-- 7. Expenses Table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) DEFAULT 1,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `title` varchar(150) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL,
  `vendor` varchar(100) DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT 'Cash',
  `receipt_doc` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `expense_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Workout Plans Table
CREATE TABLE IF NOT EXISTS `workout_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(150) NOT NULL,
  `goal` varchar(100) NOT NULL,
  `level` enum('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
  `description` text DEFAULT NULL,
  `schedule_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Diet Plans Table
CREATE TABLE IF NOT EXISTS `diet_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(150) NOT NULL,
  `target` varchar(100) NOT NULL,
  `calories` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `meals_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Member Assigned Plans Table
CREATE TABLE IF NOT EXISTS `member_assigned_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `member_id` int(11) NOT NULL,
  `workout_plan_id` int(11) DEFAULT NULL,
  `diet_plan_id` int(11) DEFAULT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','completed','archived') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `tenant_member` (`tenant_id`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Classes & Class Bookings Table
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) DEFAULT 1,
  `title` varchar(150) NOT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 20,
  `room` varchar(100) DEFAULT 'Main Studio',
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `class_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `status` enum('booked','attended','cancelled') NOT NULL DEFAULT 'booked',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_class` (`tenant_id`, `class_id`),
  KEY `tenant_member` (`tenant_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Invoices Table
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) DEFAULT 1,
  `invoice_number` varchar(50) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `plan_months` int(11) NOT NULL DEFAULT 1,
  `service_name` varchar(100) DEFAULT 'Gym Membership',
  `payment_method` varchar(50) DEFAULT 'Cash',
  `payment_date` date NOT NULL,
  `status` enum('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Paid',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe column additions to existing tables
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `branch_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `avatar` varchar(255) DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `trainer_id` int(11) DEFAULT NULL;

ALTER TABLE `staffs` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `staffs` ADD COLUMN IF NOT EXISTS `branch_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `staffs` ADD COLUMN IF NOT EXISTS `role` enum('gym_admin','staff','trainer') NOT NULL DEFAULT 'staff';
ALTER TABLE `staffs` ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') NOT NULL DEFAULT 'active';

ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `branch_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `check_out_time` text DEFAULT NULL;

ALTER TABLE `equipment` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `equipment` ADD COLUMN IF NOT EXISTS `branch_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `equipment` ADD COLUMN IF NOT EXISTS `status` varchar(50) NOT NULL DEFAULT 'Operational';

ALTER TABLE `rates` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `reminder` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `todo` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) NOT NULL DEFAULT 1;

COMMIT;
