-- ========================================================================
-- Migration: Push & In-App Notification System (SuperAdmin -> Gym Owners -> Members)
-- ========================================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `sender_user_id` int(11) DEFAULT NULL,
  `sender_role` varchar(50) NOT NULL DEFAULT 'gym_admin',
  `target_type` varchar(50) NOT NULL DEFAULT 'all_members',
  `recipient_user_id` int(11) DEFAULT NULL,
  `recipient_member_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'announcement',
  `data_payload` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_recipient` (`tenant_id`, `recipient_member_id`),
  KEY `idx_user_recipient` (`recipient_user_id`),
  KEY `idx_target_type` (`target_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure device_tokens table has all required columns and indexes
CREATE TABLE IF NOT EXISTS `device_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
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
