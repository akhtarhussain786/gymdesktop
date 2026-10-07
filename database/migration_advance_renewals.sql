-- =======================================================
-- Advance Membership Renewal & Upcoming Subscription Queue
-- =======================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Member Subscriptions Queue Table
CREATE TABLE IF NOT EXISTS `member_subscriptions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `member_id` int(11) unsigned NOT NULL,
  `plan_id` int(11) unsigned DEFAULT NULL,
  `plan_name_snapshot` varchar(191) NOT NULL,
  `plan_price_snapshot` decimal(10,2) NOT NULL DEFAULT 0.00,
  `plan_duration_snapshot` int(11) NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('pending_payment','active','upcoming','expired','cancelled','refunded','failed') NOT NULL DEFAULT 'upcoming',
  `payment_id` int(11) unsigned DEFAULT NULL,
  `queue_position` int(11) NOT NULL DEFAULT 1,
  `activated_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_member_status` (`tenant_id`, `member_id`, `status`),
  KEY `tenant_dates` (`tenant_id`, `start_date`, `expiry_date`),
  KEY `status_expiry` (`status`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Membership Payments Table
CREATE TABLE IF NOT EXISTS `membership_payments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `member_id` int(11) unsigned NOT NULL,
  `subscription_id` int(11) unsigned DEFAULT NULL,
  `plan_id` int(11) unsigned DEFAULT NULL,
  `cashfree_order_id` varchar(100) NOT NULL,
  `cashfree_payment_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `payment_method` varchar(64) DEFAULT 'Cashfree',
  `payment_status` enum('PENDING','PAID','FAILED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `gateway_response` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cashfree_order` (`cashfree_order_id`),
  KEY `tenant_member_pay` (`tenant_id`, `member_id`),
  KEY `pay_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
