-- Cashfree Onboarding & Automated Activation Database Migration
-- Run against the application database selected by your client (e.g. mysql -u USER -p DB_NAME < this_file.sql)

-- 1. Pending Onboardings Table
CREATE TABLE IF NOT EXISTS `pending_onboardings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reg_ref` varchar(60) NOT NULL,
  `gym_name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT '₹',
  `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
  `plan_id` int(11) NOT NULL,
  `billing_cycle` varchar(20) DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reg_ref` (`reg_ref`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add must_change_password to users
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `must_change_password` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

-- 3. Add country, state, city, gym_code to tenants
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `country` varchar(100) DEFAULT 'India' AFTER `address`;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `state` varchar(100) DEFAULT NULL AFTER `country`;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `city` varchar(100) DEFAULT NULL AFTER `state`;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `gym_code` varchar(50) DEFAULT NULL AFTER `id`;

-- 4. Extend saas_payments for tracking Cashfree & Email delivery
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `reg_ref` varchar(60) DEFAULT NULL AFTER `tenant_id`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `cf_order_id` varchar(100) DEFAULT NULL AFTER `transaction_ref`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `cf_payment_id` varchar(100) DEFAULT NULL AFTER `cf_order_id`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `email_status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending' AFTER `status`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `email_sent_at` datetime DEFAULT NULL AFTER `email_status`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `failure_reason` text DEFAULT NULL AFTER `email_sent_at`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `webhook_payload` longtext DEFAULT NULL AFTER `notes`;
ALTER TABLE `saas_payments` ADD COLUMN IF NOT EXISTS `webhook_status` varchar(50) DEFAULT NULL AFTER `webhook_payload`;

-- 5. Webhook Logs Table for Idempotent Event Auditing
CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) DEFAULT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `status` varchar(50) DEFAULT 'received',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
