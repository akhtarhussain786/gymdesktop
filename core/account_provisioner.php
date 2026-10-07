<?php
/**
 * Atomic & Idempotent SaaS Gym Account Provisioner
 * Handles automatic tenant creation, secure credentials generation, and welcome emailing.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';

class AccountProvisioner {

    /**
     * Generate a unique, readable User ID/username
     */
    public static function generateUniqueUsername($ownerName, $gymName) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ownerName));
        if (empty($base)) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $gymName));
        }
        if (empty($base)) {
            $base = 'gymadmin';
        }

        $base = substr($base, 0, 10);
        $candidate = $base . '_' . rand(100, 999);

        // Ensure uniqueness across entire users table
        while (DB::fetchOne("SELECT id FROM users WHERE username = ?", [$candidate])) {
            $candidate = $base . '_' . rand(1000, 9999);
        }

        return $candidate;
    }

    /**
     * Generate a cryptographically strong temporary password
     * Contains uppercase, lowercase, digits, and special characters (12 chars)
     */
    public static function generateStrongTemporaryPassword($length = 12) {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers   = '23456789';
        $special   = '!@#$%^&*';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Provision a paid or trial gym account idempotently
     * 
     * @param array $onboardingData Pending onboarding record or submission data
     * @param array $paymentDetails Payment verification info (transaction ID, payment method, amount, etc.)
     * @return array ['success' => bool, 'tenant_id' => int, 'user_id' => int, 'username' => string, 'already_provisioned' => bool, 'error' => string|null]
     */
    public static function provisionAccount($onboardingData, $paymentDetails = []) {
        $regRef = $onboardingData['reg_ref'] ?? '';
        $orderId = $paymentDetails['order_id'] ?? ($onboardingData['order_id'] ?? '');

        DB::beginTransaction();
        try {
            // Lock the pending onboarding row so concurrent webhook / redirect / polling calls serialise here
            if (!empty($regRef)) {
                DB::fetchOne("SELECT id FROM pending_onboardings WHERE reg_ref = ? FOR UPDATE", [$regRef]);
            }

            // 1. Idempotency Check: check if already provisioned
            if (!empty($regRef)) {
                $existingPayment = DB::fetchOne("SELECT * FROM saas_payments WHERE reg_ref = ? AND status = 'approved' AND tenant_id > 0", [$regRef]);
                if ($existingPayment) {
                    DB::commit();
                    return [
                        'success' => true,
                        'tenant_id' => (int)$existingPayment['tenant_id'],
                        'already_provisioned' => true,
                        'order_id' => $existingPayment['transaction_ref']
                    ];
                }
            }

            if (!empty($orderId)) {
                $existingPayment = DB::fetchOne("SELECT * FROM saas_payments WHERE transaction_ref = ? AND status = 'approved' AND tenant_id > 0 FOR UPDATE", [$orderId]);
                if ($existingPayment) {
                    DB::commit();
                    return [
                        'success' => true,
                        'tenant_id' => (int)$existingPayment['tenant_id'],
                        'already_provisioned' => true,
                        'order_id' => $orderId
                    ];
                }
            }

            // 2. Fetch subscription plan
            $planId = (int)($onboardingData['plan_id'] ?? 1);
            $plan = DB::fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$planId]);
            if (!$plan) {
                DB::rollback();
                return ['success' => false, 'error' => 'Subscription plan does not exist.'];
            }

            $gymName   = trim($onboardingData['gym_name']);
            $ownerName = trim($onboardingData['owner_name']);
            $email     = trim($onboardingData['email']);
            $phone     = trim($onboardingData['phone']);
            $address   = trim($onboardingData['address'] ?? '');
            $country   = trim($onboardingData['country'] ?? 'India');
            $state     = trim($onboardingData['state'] ?? '');
            $city      = trim($onboardingData['city'] ?? '');
            $currency  = trim($onboardingData['currency'] ?? '₹');
            $timezone  = trim($onboardingData['timezone'] ?? 'Asia/Kolkata');
            $cycle     = trim($onboardingData['billing_cycle'] ?? 'monthly');

            $amountPaid = (float)($paymentDetails['amount'] ?? ($onboardingData['amount'] ?? 0));
            // A trial is a trial plan / explicit trial cycle with nothing paid — never a paid order
            $isTrial = ($amountPaid <= 0) && ($cycle === 'trial' || $plan['billing_cycle'] === 'trial' || (float)$plan['price_monthly'] === 0.00);

            // Calculate subscription expiry (calendar months, month-end safe)
            $startDate = date('Y-m-d');
            if ($isTrial) {
                $trialDays = (int)$plan['trial_days'] ?: 14;
                $expiryDate = date('Y-m-d', strtotime("+$trialDays days"));
            } else {
                $months = ($cycle === 'yearly') ? 12 : (($cycle === 'quarterly') ? 3 : 1);
                $expiryDate = self::addMonthsClamped($startDate, $months);
            }

            // Generate clean unique slug
            $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $gymName), '-'));
            $slug = $baseSlug ?: 'gym';
            while (DB::fetchOne("SELECT id FROM tenants WHERE slug = ?", [$slug])) {
                $slug = ($baseSlug ?: 'gym') . '-' . rand(100, 9999);
            }

            $gymCode = 'GYM' . strtoupper(substr(md5($email . microtime(true) . random_int(0, PHP_INT_MAX)), 0, 6));

            // 3. Create Tenant in DB
            $tenantId = DB::insert('tenants', [
                'gym_code' => $gymCode,
                'gym_name' => $gymName,
                'slug' => $slug,
                'owner_name' => $ownerName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'country' => $country,
                'state' => $state,
                'city' => $city,
                'currency' => $currency,
                'timezone' => $timezone,
                'subscription_plan_id' => $planId,
                'subscription_start' => $startDate,
                'subscription_expiry' => $expiryDate,
                'status' => $isTrial ? 'trial' : 'active',
                'primary_color' => '#6366f1',
                'secondary_color' => '#10b981'
            ]);

            if (!$tenantId) {
                throw new RuntimeException('Failed to create gym tenant record.');
            }

            // 4. Create Main Branch
            $branchId = DB::insert('branches', [
                'tenant_id' => $tenantId,
                'branch_name' => $gymName . ' - Main Center',
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'is_main' => 1,
                'status' => 'active'
            ]);

            // 5. Generate unique User ID / Username and temporary password
            $generatedUsername = self::generateUniqueUsername($ownerName, $gymName);
            $tempPassword = self::generateStrongTemporaryPassword(12);
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

            // 6. Create Gym Admin User with must_change_password = 1
            $userId = DB::insert('users', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'role' => 'gym_admin',
                'username' => $generatedUsername,
                'password' => $passwordHash,
                'email' => $email,
                'fullname' => $ownerName,
                'phone' => $phone,
                'status' => 'active',
                'must_change_password' => 1
            ]);
            if (!$userId) {
                throw new RuntimeException('Failed to create gym admin user.');
            }

            // 6b. Seed initial default gym membership packages/rates
            Tenant::ensureDefaultRates($tenantId);

            // 7. Record / Update SaaS Payment Record
            $cfPaymentId = $paymentDetails['transaction_id'] ?? null;
            $cfOrderId = $paymentDetails['cf_order_id'] ?? null;
            $paymentMethod = $paymentDetails['payment_method'] ?? ($isTrial ? 'free_trial' : 'cashfree');
            $txnRef = $orderId ?: ('TRIAL_' . ($regRef ?: bin2hex(random_bytes(6))));

            $paymentRow = [
                'tenant_id' => $tenantId,
                'reg_ref' => $regRef,
                'plan_id' => $planId,
                'billing_cycle' => $cycle,
                'amount' => $amountPaid,
                'tax_amount' => 0.00,
                'discount_amount' => 0.00,
                'total_payable' => $amountPaid,
                'payment_method' => $paymentMethod,
                'transaction_ref' => $txnRef,
                'cf_order_id' => $cfOrderId,
                'cf_payment_id' => $cfPaymentId,
                'status' => 'approved',
                'email_status' => 'pending',
                'start_date' => $startDate,
                'end_date' => $expiryDate,
                'notes' => $isTrial ? 'Free Trial Activation' : 'Cashfree Paid Activation'
            ];

            // Promote the pending checkout row created at registration instead of inserting a duplicate
            $pendingRow = null;
            if (!empty($orderId)) {
                $pendingRow = DB::fetchOne("SELECT id FROM saas_payments WHERE transaction_ref = ? FOR UPDATE", [$orderId]);
            }
            if (!$pendingRow && !empty($regRef)) {
                $pendingRow = DB::fetchOne("SELECT id FROM saas_payments WHERE reg_ref = ? AND status IN ('pending','failed') ORDER BY id DESC LIMIT 1 FOR UPDATE", [$regRef]);
            }
            if ($pendingRow) {
                $paymentId = (int)$pendingRow['id'];
                $updateRow = $paymentRow;
                if ($cfOrderId === null) unset($updateRow['cf_order_id']);
                DB::update('saas_payments', $updateRow, 'id = ?', [$paymentId]);
            } else {
                $paymentId = DB::insert('saas_payments', $paymentRow);
            }
            if (!$paymentId) {
                throw new RuntimeException('Failed to record SaaS payment.');
            }

            // 8. Update pending onboarding status to completed
            if (!empty($regRef)) {
                DB::update('pending_onboardings', [
                    'status' => 'completed',
                    'order_id' => $orderId
                ], 'reg_ref = ?', [$regRef]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            error_log('AccountProvisioner::provisionAccount error (' . $regRef . '/' . $orderId . '): ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to provision gym account. Please contact support with reference ' . ($orderId ?: $regRef) . '.'];
        }

        // 9. Send Credentials Email (memory-only temp password)
        $mailResult = Mailer::sendCredentialsEmail([
            'to_email' => $email,
            'owner_name' => $ownerName,
            'gym_name' => $gymName,
            'plan_name' => $plan['name'],
            'amount_paid' => $amountPaid,
            'currency' => $currency,
            'activation_date' => $startDate,
            'expiry_date' => $expiryDate,
            'username' => $generatedUsername,
            'temp_password' => $tempPassword,
            'order_ref' => $orderId ?: $gymCode,
            'tenant_id' => $tenantId
        ]);

        // Update email delivery status
        if ($paymentId) {
            DB::update('saas_payments', [
                'email_status' => $mailResult['success'] ? 'sent' : 'failed',
                'email_sent_at' => date('Y-m-d H:i:s'),
                'failure_reason' => $mailResult['error'] ?? null
            ], 'id = ?', [$paymentId]);
        }

        Auth::auditLog('GYM_ACCOUNT_PROVISIONED', "Provisioned gym '$gymName' (#$tenantId) with admin '$generatedUsername'");

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'username' => $generatedUsername,
            'temp_password' => $tempPassword,
            'masked_email' => self::maskEmail($email),
            'order_id' => $orderId,
            'expiry_date' => $expiryDate,
            'already_provisioned' => false
        ];
    }

    /**
     * Calendar-month addition clamped to month end (Jan 31 + 1 month => Feb 28/29).
     */
    public static function addMonthsClamped($date, $months) {
        if (class_exists('SubscriptionEngine')) {
            return SubscriptionEngine::addMonths($date, $months);
        }
        $ts = strtotime((string)$date);
        $total = ((int)date('Y', $ts) * 12 + ((int)date('n', $ts) - 1)) + (int)$months;
        $y = intdiv($total, 12);
        $m = ($total % 12) + 1;
        $last = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
        return sprintf('%04d-%02d-%02d', $y, $m, min((int)date('j', $ts), $last));
    }

    /**
     * Re-verify an onboarding order directly with Cashfree (never trust webhook / redirect payloads)
     * and provision only when the order is PAID in full, in INR, for the amount we quoted.
     */
    public static function provisionVerifiedOrder($pending, $orderId) {
        if (!$pending || empty($orderId)) {
            return ['success' => false, 'error' => 'Onboarding record not found.'];
        }
        if (!class_exists('CashfreeGateway')) {
            require_once __DIR__ . '/cashfree.php';
        }
        // The order must be the one currently attached to this registration
        if (!empty($pending['order_id']) && $pending['order_id'] !== $orderId && ($pending['reg_ref'] ?? '') !== $orderId) {
            return ['success' => false, 'error' => 'Order does not match this registration.'];
        }

        $verification = CashfreeGateway::verifyOrder($orderId);
        if (!$verification['success'] || $verification['status'] !== 'PAID') {
            return ['success' => false, 'status' => $verification['status'] ?? 'UNKNOWN', 'order_status' => $verification['order_status'] ?? null, 'error' => $verification['error'] ?: 'Payment not completed.'];
        }
        $expected = (float)$pending['amount'];
        if (strtoupper($verification['currency'] ?? 'INR') !== 'INR' || abs((float)$verification['order_amount'] - $expected) > 0.01) {
            error_log("Onboarding order {$orderId} amount/currency mismatch: expected {$expected} INR, got {$verification['order_amount']} {$verification['currency']}");
            return ['success' => false, 'status' => 'MISMATCH', 'error' => 'Paid amount does not match the registration order. Please contact support.'];
        }

        return self::provisionAccount($pending, [
            'order_id' => $orderId,
            'amount' => (float)$verification['order_amount'],
            'transaction_id' => $verification['transaction_id'],
            'payment_method' => $verification['payment_method'] ?: 'cashfree',
            'cf_order_id' => $verification['raw_order']['cf_order_id'] ?? null
        ]);
    }

    /**
     * Mask email for display on public confirmation screens (e.g. j***n@example.com)
     */
    public static function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
