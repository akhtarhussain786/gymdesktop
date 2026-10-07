-- ============================================================================
-- Migration: Admin Mobile App, Customer Photo, and Dues Management
-- ============================================================================

-- 1. Ensure members table supports avatar, due amount, and promise due date
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `avatar` varchar(255) DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `due_date` date DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL;

-- 2. Ensure invoices table supports partial payments, due amounts, and due dates
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `due_date` date DEFAULT NULL;

-- 3. Ensure users table supports avatars for gym staff / admins
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `avatar` varchar(255) DEFAULT NULL;

-- 4. Update existing invoices where paid_amount is 0 but status is 'Paid'
UPDATE `invoices` SET `paid_amount` = `amount` WHERE `status` = 'Paid' AND `paid_amount` = 0.00 AND `amount` > 0.00;
