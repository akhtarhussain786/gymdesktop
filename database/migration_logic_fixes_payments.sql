-- ============================================================================
-- Payment / subscription logic fixes (idempotent; MariaDB 10.3+ syntax, like
-- the other migrations in this folder). Safe to run more than once.
--
-- BEFORE RUNNING: the UNIQUE keys below fail if duplicates already exist.
-- Check first:
--   SELECT transaction_ref, COUNT(*) FROM saas_payments
--     WHERE transaction_ref IS NOT NULL GROUP BY transaction_ref HAVING COUNT(*) > 1;
--   SELECT cashfree_order_id, COUNT(*) FROM membership_payments
--     GROUP BY cashfree_order_id HAVING COUNT(*) > 1;
--   SELECT tenant_id, invoice_number, COUNT(*) FROM invoices
--     GROUP BY tenant_id, invoice_number HAVING COUNT(*) > 1;
-- ============================================================================

-- 1. Store the purchased duration on the order itself (no more round(amount/rate))
ALTER TABLE `membership_payments`
  ADD COLUMN IF NOT EXISTS `plan_months` int(11) NOT NULL DEFAULT 0 AFTER `plan_id`;

-- 2. Idempotency: one row per gateway order / transaction reference
-- 2a. Old onboarding flow left a stale 'pending' row (tenant_id 0) next to the approved row for the
--     same order; remove only those stale duplicates so the unique key below can be created.
DELETE p FROM `saas_payments` p
  JOIN `saas_payments` a ON a.transaction_ref = p.transaction_ref AND a.id <> p.id AND a.status = 'approved'
 WHERE p.status = 'pending' AND (p.tenant_id = 0 OR p.tenant_id IS NULL);
CREATE UNIQUE INDEX IF NOT EXISTS `unique_cashfree_order` ON `membership_payments` (`cashfree_order_id`);
CREATE UNIQUE INDEX IF NOT EXISTS `uniq_saas_transaction_ref` ON `saas_payments` (`transaction_ref`);
CREATE INDEX IF NOT EXISTS `idx_saas_reg_ref` ON `saas_payments` (`reg_ref`);
CREATE INDEX IF NOT EXISTS `idx_mp_payment_ref` ON `membership_payments` (`tenant_id`, `cashfree_payment_id`);

-- 3. Invoice numbers are now derived from the invoice id; enforce per-tenant uniqueness
CREATE UNIQUE INDEX IF NOT EXISTS `uniq_invoice_tenant_number` ON `invoices` (`tenant_id`, `invoice_number`);
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `payment_id` int(11) DEFAULT NULL AFTER `member_id`;
CREATE INDEX IF NOT EXISTS `idx_invoice_payment` ON `invoices` (`tenant_id`, `payment_id`);

-- 4. Member-submitted UPI renewals are stored as 'pending' until staff verify them;
--    rejected ones become 'cancelled'. Older installs (migration_saas.sql) only had
--    ('Paid','Partial','Unpaid'). Enum matching is case-insensitive, existing values map over.
ALTER TABLE `invoices`
  MODIFY `status` enum('paid','pending','partial','unpaid','cancelled') NOT NULL DEFAULT 'paid';

-- 5. One-off data repair: SubscriptionEngine used to write members.paid_date = EXPIRY
--    for online renewals; the convention is paid_date = START of the current period.
UPDATE `members` m
  JOIN `member_subscriptions` s
    ON s.member_id = m.user_id AND s.tenant_id = m.tenant_id AND s.status = 'active'
   SET m.paid_date = s.start_date
 WHERE m.paid_date = s.expiry_date
   AND s.start_date <> s.expiry_date;

-- 6. Backfill plan_months for already-created orders from their subscription snapshot
UPDATE `membership_payments` mp
  JOIN `member_subscriptions` s ON s.id = mp.subscription_id
   SET mp.plan_months = s.plan_duration_snapshot
 WHERE mp.plan_months = 0;
