-- ============================================================================
-- Schema alignment (idempotent, MariaDB 10.3+). Safe to run more than once.
-- gymsaas_complete_fresh_install.sql and the migration_*.sql files defined several tables
-- differently; CREATE TABLE IF NOT EXISTS kept the fresh-install shape, so on a fresh install
-- the application queried columns that did not exist (e.g. workout_plans.name, tenants.tax_percent,
-- users.staff_id). This brings any install to the shape the application code uses.
-- Run AFTER all other migration_*.sql files.
-- ============================================================================

ALTER TABLE `device_tokens` ADD COLUMN IF NOT EXISTS `user_id` int(11);
ALTER TABLE `device_tokens` ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') NOT NULL DEFAULT 'active';
ALTER TABLE `member_inquiries` ADD COLUMN IF NOT EXISTS `branch_id` int(11) DEFAULT 1;
ALTER TABLE `member_inquiries` ADD COLUMN IF NOT EXISTS `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `subscription_plans` ADD COLUMN IF NOT EXISTS `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `grace_period_end` date DEFAULT NULL;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `tax_percent` decimal(5,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `gst_number` varchar(50) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `avatar` varchar(255) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `designation` varchar(50) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `permissions` text DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `staff_id` int(11) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` datetime DEFAULT NULL;
ALTER TABLE `workout_plans` ADD COLUMN IF NOT EXISTS `name` varchar(150);
ALTER TABLE `workout_plans` ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL;
ALTER TABLE `workout_plans` ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL;
ALTER TABLE `diet_plans` ADD COLUMN IF NOT EXISTS `name` varchar(150);
ALTER TABLE `diet_plans` ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL;
ALTER TABLE `diet_plans` ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL;

-- Fresh-install plan tables were per-member (member_id, plan_name NOT NULL); the app uses reusable
-- named plans assigned through member_assigned_plans. Keep the old columns but make them optional.
ALTER TABLE `workout_plans` MODIFY COLUMN IF EXISTS `member_id` int(11) unsigned DEFAULT NULL;
ALTER TABLE `workout_plans` MODIFY COLUMN IF EXISTS `plan_name` varchar(191) DEFAULT NULL;
ALTER TABLE `diet_plans` MODIFY COLUMN IF EXISTS `member_id` int(11) unsigned DEFAULT NULL;
ALTER TABLE `diet_plans` MODIFY COLUMN IF EXISTS `plan_name` varchar(191) DEFAULT NULL;
UPDATE `workout_plans` SET `name` = `plan_name` WHERE (`name` IS NULL OR `name` = '') AND `plan_name` IS NOT NULL;
UPDATE `diet_plans` SET `name` = `plan_name` WHERE (`name` IS NULL OR `name` = '') AND `plan_name` IS NOT NULL;
UPDATE `workout_plans` SET `description` = `notes` WHERE `description` IS NULL AND `notes` IS NOT NULL;
UPDATE `diet_plans` SET `description` = `notes` WHERE `description` IS NULL AND `notes` IS NOT NULL;

-- Invoices: application code reads both `amount` and `total_amount`
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
UPDATE `invoices` SET `amount` = `total_amount` WHERE `amount` = 0 AND `total_amount` > 0;
UPDATE `invoices` SET `total_amount` = `amount` WHERE `total_amount` = 0 AND `amount` > 0;

-- Landing-page testimonials (previously only created when a super admin first opened that page,
-- so the public home page logged a DB error on every visit until then)
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `author_name` VARCHAR(150) NOT NULL,
  `designation` VARCHAR(255) NOT NULL,
  `quote` TEXT NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `rating` INT DEFAULT 5,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Member app support tickets / password-reset requests: the app writes status 'open', but the
-- fresh-install enum only allowed 'pending' (strict SQL mode rejected every ticket)
ALTER TABLE `member_inquiries` MODIFY COLUMN `status` enum('open','pending','in_progress','resolved','closed') NOT NULL DEFAULT 'open';
UPDATE `member_inquiries` SET `status` = 'open' WHERE `status` = 'pending';

-- Columns the fresh install declared NOT NULL without a default, although the application
-- (and the migration_*.sql definitions) treat them as optional; inserts failed in strict SQL mode
ALTER TABLE `announcements` MODIFY COLUMN IF EXISTS `title` varchar(191) NOT NULL DEFAULT '';
ALTER TABLE `workout_plans` MODIFY COLUMN IF EXISTS `schedule_json` longtext DEFAULT NULL;
ALTER TABLE `diet_plans` MODIFY COLUMN IF EXISTS `meals_json` longtext DEFAULT NULL;
ALTER TABLE `staffs` MODIFY COLUMN IF EXISTS `email` varchar(100) DEFAULT NULL;
