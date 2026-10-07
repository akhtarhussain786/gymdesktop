-- Auth / password-reset hardening migration (idempotent).
-- core/db.php ensureSchema() also creates these automatically on the next deploy.

-- 1. Password reset tokens + request log (rate limiting)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `identifier_hash` char(64) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_identifier_created` (`identifier_hash`, `created_at`),
  KEY `idx_ip_created` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Forced first-login password change flag (MariaDB 10.0.2+ syntax)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `must_change_password` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

-- 3. Optional housekeeping: purge old reset rows
-- DELETE FROM `password_resets` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY);
