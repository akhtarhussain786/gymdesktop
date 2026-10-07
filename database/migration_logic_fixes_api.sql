-- ========================================================================
-- Member API logic/security fixes - schema support (MariaDB, idempotent)
-- Safe to run multiple times. Code in api/member/ degrades gracefully if not applied,
-- but login throttling, token metadata and legacy dashboard fallbacks need these objects.
-- ========================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- 1. Brute-force / abuse throttling store (login, forgot_password, gym_lookup, list_gyms, change_password)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(32) NOT NULL,
  `identifier` char(64) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scope_ident_time` (`scope`, `identifier`, `attempted_at`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Member API session tokens (missing in production per api/member/error_log)
CREATE TABLE IF NOT EXISTS `member_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `token_hash` varchar(64) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `device_name` varchar(150) DEFAULT NULL,
  `platform` varchar(30) DEFAULT 'android',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL COMMENT 'UTC',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_tenant_member` (`tenant_id`, `member_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older migration_flutter_member_app.sql variant lacks these columns (login INSERT failed silently)
ALTER TABLE `member_tokens` ADD COLUMN IF NOT EXISTS `ip_address` varchar(45) DEFAULT NULL AFTER `platform`;
ALTER TABLE `member_tokens` ADD COLUMN IF NOT EXISTS `user_agent` text DEFAULT NULL AFTER `ip_address`;
ALTER TABLE `member_tokens` MODIFY `user_id` int(11) NOT NULL DEFAULT 0;
-- Old variant auto-bumped last_used_at; harmless, but make it nullable/plain
ALTER TABLE `member_tokens` MODIFY `last_used_at` datetime DEFAULT NULL;
-- Purge already-expired sessions before adding the unique index
DELETE FROM `member_tokens` WHERE `expires_at` < UTC_TIMESTAMP();
ALTER TABLE `member_tokens` ADD UNIQUE INDEX IF NOT EXISTS `uniq_token_hash` (`token_hash`);

-- 3. Legacy per-member trainer table referenced by dashboard.php fallback
CREATE TABLE IF NOT EXISTS `trainers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `member_id` int(11) unsigned NOT NULL,
  `fullname` varchar(191) NOT NULL,
  `designation` varchar(191) DEFAULT 'Personal Trainer',
  `specializations` varchar(255) DEFAULT NULL,
  `available_timings` varchar(191) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_tenant_member` (`tenant_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Member workout checklist table referenced by dashboard.php
CREATE TABLE IF NOT EXISTS `member_workout_todos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `member_id` int(11) unsigned NOT NULL,
  `task_desc` varchar(255) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_todos_tenant_member` (`tenant_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Optional direct member binding for workout/diet plans (dashboard.php fallback when no member_assigned_plans row)
ALTER TABLE `workout_plans` ADD COLUMN IF NOT EXISTS `member_id` int(11) DEFAULT NULL AFTER `tenant_id`;
ALTER TABLE `workout_plans` ADD INDEX IF NOT EXISTS `idx_wp_tenant_member` (`tenant_id`, `member_id`);
ALTER TABLE `diet_plans` ADD COLUMN IF NOT EXISTS `member_id` int(11) DEFAULT NULL AFTER `tenant_id`;
ALTER TABLE `diet_plans` ADD INDEX IF NOT EXISTS `idx_dp_tenant_member` (`tenant_id`, `member_id`);

-- 6. Attendance lookups are now always (tenant_id, user_id, curr_date)
ALTER TABLE `attendance` ADD INDEX IF NOT EXISTS `idx_att_tenant_user_date` (`tenant_id`, `user_id`, `curr_date`);

-- 7. Push token lookups by member
ALTER TABLE `device_tokens` ADD INDEX IF NOT EXISTS `idx_tenant_member` (`tenant_id`, `member_id`);
