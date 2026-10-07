-- ========================================================================
-- Flutter Multi-Tenant Member App Migration Script
-- ========================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Ensure gym_code column exists in tenants and is indexed
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `gym_code` varchar(50) DEFAULT NULL AFTER `id`;

-- 2. Populate gym codes for existing tenants if null
UPDATE `tenants` SET `gym_code` = CONCAT('GYM-', UPPER(SUBSTRING(REPLACE(slug, '-', ''), 1, 3)), LPAD(id, 2, '0')) WHERE `gym_code` IS NULL OR `gym_code` = '';
UPDATE `tenants` SET `gym_code` = 'GYM-FIT01' WHERE `id` = 1;
UPDATE `tenants` SET `gym_code` = 'GYM-URB02' WHERE `id` = 2;
UPDATE `tenants` SET `gym_code` = 'GYM-KHA03' WHERE `id` = 3;

-- 3. Member Authentication Tokens Table (for mobile app API session management)
CREATE TABLE IF NOT EXISTS `member_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(128) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `device_name` varchar(150) DEFAULT NULL,
  `platform` varchar(30) DEFAULT 'flutter',
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_member` (`tenant_id`, `member_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Push Notification Device Tokens Table (Strictly isolated by Tenant ID)
CREATE TABLE IF NOT EXISTS `device_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_token` text NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `platform` enum('android','ios','web') NOT NULL DEFAULT 'android',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_device` (`tenant_id`, `device_id`),
  KEY `idx_tenant_member` (`tenant_id`, `member_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Member Support Tickets & Help Inquiries Table
CREATE TABLE IF NOT EXISTS `member_inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT 1,
  `member_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_member` (`tenant_id`, `member_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Demo tenants/members for local testing were moved to seed_demo_tenants_DEV_ONLY.sql (never run on production).
