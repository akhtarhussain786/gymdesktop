-- Email Delivery Tracking Database Migration
-- Run against the application database selected by your client (e.g. mysql -u USER -p DB_NAME < this_file.sql)

CREATE TABLE IF NOT EXISTS `email_delivery_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(150) NOT NULL,
  `email_type` varchar(50) NOT NULL,
  `order_ref` varchar(100) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 1,
  `sent_at` datetime DEFAULT NULL,
  `last_attempt_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `failure_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_ref` (`order_ref`),
  KEY `idx_recipient_email` (`recipient_email`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
