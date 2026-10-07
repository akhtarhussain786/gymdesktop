<?php
/**
 * Core Advance Membership Renewal & Subscription Queue Engine
 * Handles queued plans, Cashfree payment orders, idempotency, auto-activations & notifications.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/cashfree.php';

class SubscriptionEngine {

    /** Allowed renewal durations (months) and their discount ladder — single source of truth. */
    const DURATION_DISCOUNTS = [1 => 0, 3 => 5, 6 => 10, 12 => 20];

    /**
     * Add calendar months to a date, clamping to the last day of the target month
     * (avoids PHP's "+1 month" overflow: Jan 31 + 1 month => Feb 28/29, not Mar 3).
     */
    public static function addMonths($date, $months) {
        $months = (int)$months;
        $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
        if ($ts === false || $ts === null) {
            $ts = time();
        }
        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        $d = (int)date('j', $ts);

        $total = ($y * 12 + ($m - 1)) + $months;
        $newY = intdiv($total, 12);
        $newM = ($total % 12) + 1;
        $lastDay = (int)date('t', mktime(0, 0, 0, $newM, 1, $newY));
        return sprintf('%04d-%02d-%02d', $newY, $newM, min($d, $lastDay));
    }

    /**
     * Discount percent for a renewal duration. Returns null for unsupported durations.
     */
    public static function durationDiscountPercent($months) {
        $months = (int)$months;
        return array_key_exists($months, self::DURATION_DISCOUNTS) ? self::DURATION_DISCOUNTS[$months] : null;
    }

    /**
     * Server-side quote for a member renewal from the gym's own rate card.
     * Never trusts client-supplied amount / plan name.
     */
    public static function quoteMemberRenewal($tenantId, $rateId, $months) {
        $tenantId = (int)$tenantId;
        $rateId = (int)$rateId;
        $months = (int)$months;

        $discountPercent = self::durationDiscountPercent($months);
        if ($discountPercent === null) {
            return ['success' => false, 'error' => 'Unsupported renewal duration. Choose 1, 3, 6 or 12 months.'];
        }

        Tenant::ensureDefaultRates($tenantId);

        $rate = $rateId > 0 ? DB::fetchOne("SELECT * FROM rates WHERE id = ? AND tenant_id = ?", [$rateId, $tenantId]) : null;
        if (!$rate) {
            // Fallback to the first available rate for this gym
            $rate = DB::fetchOne("SELECT * FROM rates WHERE tenant_id = ? AND charge > 0 ORDER BY charge ASC, id ASC LIMIT 1", [$tenantId]);
        }
        if (!$rate) {
            return ['success' => false, 'error' => 'Selected membership plan was not found for this gym.'];
        }

        $baseCharge = (float)$rate['charge'];
        if ($baseCharge <= 0) {
            return ['success' => false, 'error' => 'Selected membership plan has no price configured.'];
        }

        $rawTotal = $baseCharge * $months;
        $payableAmount = round($rawTotal * (1 - ($discountPercent / 100)), 2);

        return [
            'success' => true,
            'rate' => $rate,
            'plan_id' => (int)$rate['id'],
            'plan_name' => $rate['name'],
            'months' => $months,
            'monthly_rate' => $baseCharge,
            'discount_percent' => $discountPercent,
            'amount' => $payableAmount
        ];
    }

    /**
     * Whether a gym (tenant) may currently collect member payments.
     * Blocks suspended/cancelled gyms and gyms whose SaaS subscription is past expiry + grace period.
     */
    public static function tenantCanCollectPayments($tenant) {
        if (!$tenant) {
            return ['allowed' => false, 'reason' => 'Gym account not found.'];
        }
        $status = strtolower((string)($tenant['status'] ?? 'active'));
        if (in_array($status, ['suspended', 'cancelled', 'inactive'], true)) {
            return ['allowed' => false, 'reason' => 'This gym account is not active. Online payments are disabled.'];
        }
        if (!empty($tenant['subscription_expiry']) && strpos((string)$tenant['subscription_expiry'], '0000-00-00') !== 0) {
            $graceDays = 7;
            if (!empty($tenant['subscription_plan_id'])) {
                $g = DB::fetchValue("SELECT grace_period_days FROM subscription_plans WHERE id = ?", [(int)$tenant['subscription_plan_id']]);
                if ($g !== null && $g !== '') {
                    $graceDays = max(0, (int)$g);
                }
            }
            $expiryDate = date('Y-m-d', strtotime($tenant['subscription_expiry']));
            $graceEnd = date('Y-m-d', strtotime("+{$graceDays} days", strtotime($expiryDate)));
            if (date('Y-m-d') > $graceEnd) {
                return ['allowed' => false, 'reason' => 'This gym\'s platform subscription has expired. Please contact the gym.'];
            }
        }
        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Whether the gym prices in Indian Rupees. Cashfree orders and UPI QR collection here are INR-only.
     */
    public static function tenantUsesInr($tenant) {
        $cur = trim((string)($tenant['currency'] ?? '₹'));
        if ($cur === '' || $cur === '₹' || strtoupper($cur) === 'INR' || $cur === 'Rs' || $cur === 'Rs.') {
            return true;
        }
        if (function_exists('get_supported_currencies')) {
            foreach (get_supported_currencies() as $c) {
                if ($c['symbol'] === $cur || strtoupper($c['code']) === strtoupper($cur)) {
                    return strtoupper($c['code']) === 'INR';
                }
            }
        }
        return false;
    }

    /**
     * Public application base URL for gateway return URLs.
     * Prefers APP_URL from env; falls back to the request host only when APP_URL is missing.
     */
    public static function appBaseUrl() {
        $appUrl = trim((string)env('APP_URL', ''));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/');
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        return ($isHttps ? 'https' : 'http') . '://' . $host;
    }

    /**
     * Give an invoice a collision-free number derived from its auto-increment id.
     */
    public static function assignInvoiceNumber($invoiceId, $tenant, $date = null) {
        $invoiceId = (int)$invoiceId;
        if ($invoiceId <= 0) return null;
        $slugSrc = $tenant['slug'] ?? ($tenant['gym_name'] ?? 'GYM');
        $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string)$slugSrc), 0, 4)) ?: 'GYM';
        $ym = date('Ym', $date ? strtotime($date) : time());
        $number = 'INV-' . $tenantSlug . '-' . $ym . '-' . str_pad((string)$invoiceId, 5, '0', STR_PAD_LEFT);
        DB::update('invoices', ['invoice_number' => $number], 'id = ?', [$invoiceId]);
        return $number;
    }

    /**
     * Temporary unique placeholder used until assignInvoiceNumber() runs.
     */
    public static function tempInvoiceNumber() {
        return 'TMP-' . strtoupper(bin2hex(random_bytes(6)));
    }

    /**
     * Calculate seamless next start date for a member's renewal.
     * Prevents date overlaps or access gaps.
     * NOTE: members.paid_date is the START of the current period (expiry = paid_date + plan months).
     */
    public static function calculateNextStartDate($tenantId, $memberId) {
        $tenantId = (int)$tenantId;
        $memberId = (int)$memberId;
        $today = date('Y-m-d');

        // 1. Check max expiry_date among active or upcoming subscriptions
        $lastExpiry = DB::fetchValue(
            "SELECT MAX(expiry_date) 
             FROM member_subscriptions 
             WHERE tenant_id = ? AND member_id = ? AND status IN ('active', 'upcoming')",
            [$tenantId, $memberId]
        );

        if (!empty($lastExpiry) && $lastExpiry >= $today) {
            return date('Y-m-d', strtotime('+1 day', strtotime($lastExpiry)));
        }

        // 2. Fallback check on members table paid_date & plan
        $member = DB::fetchOne("SELECT paid_date, plan FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        if ($member && !empty($member['paid_date']) && strpos((string)$member['paid_date'], '0000-00-00') !== 0) {
            $paidDate = $member['paid_date'];
            $planMonths = max(1, (int)$member['plan']);
            $currentExpiry = self::addMonths($paidDate, $planMonths);
            if ($currentExpiry >= $today) {
                return date('Y-m-d', strtotime('+1 day', strtotime($currentExpiry)));
            }
        }

        return $today;
    }

    /**
     * Calculate expiry date from start date and duration in months (month-end safe).
     */
    public static function calculateExpiryDate($startDate, $months) {
        $months = max(1, (int)$months);
        return self::addMonths($startDate, $months);
    }


    /**
     * Create a Cashfree Payment Order securely from backend (never trusting frontend price).
     */
    public static function createPendingPaymentOrder($tenantId, $memberId, $planId, $months = 1) {
        $tenantId = (int)$tenantId;
        $memberId = (int)$memberId;
        $planId = (int)$planId;
        $months = (int)$months;

        $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        if (!$tenant || !$member) {
            return ['success' => false, 'error' => 'Tenant or member not found.'];
        }

        // Expired / suspended gyms must not collect member payments
        $gate = self::tenantCanCollectPayments($tenant);
        if (!$gate['allowed']) {
            return ['success' => false, 'error' => $gate['reason']];
        }

        // Cashfree / UPI collection is INR-only; never charge a non-INR price as rupees
        if (!self::tenantUsesInr($tenant)) {
            return ['success' => false, 'error' => 'Online payments are only available for gyms billing in INR.'];
        }

        // Fetch plan rate securely from DB (no fallback to arbitrary prices)
        $quote = self::quoteMemberRenewal($tenantId, $planId, $months);
        if (!$quote['success']) {
            return ['success' => false, 'error' => $quote['error']];
        }
        $planName = $quote['plan_name'];
        $months = $quote['months'];
        $discountPercent = $quote['discount_percent'];
        $payableAmount = $quote['amount'];

        // Unique order ID
        $orderId = CashfreeGateway::generateOrderId("CF_REN_{$tenantId}_{$memberId}");

        $customerName = $member['fullname'] ?: 'Member';
        $customerPhone = $member['contact'] ?: $tenant['phone'] ?: '9876543210';
        $customerEmail = $member['email'] ?: $tenant['email'] ?: 'member@fitisify.com';
        $returnUrl = self::appBaseUrl() . "/api/member/check_payment_status.php";

        // Insert pending payment record BEFORE creating the gateway order so a webhook can never
        // arrive for an order we have no record of. Months are persisted on the order itself.
        $paymentRecordId = DB::insert('membership_payments', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'plan_id' => $planId,
            'plan_months' => $months,
            'cashfree_order_id' => $orderId,
            'amount' => $payableAmount,
            'currency' => 'INR',
            'payment_method' => 'Cashfree',
            'payment_status' => 'PENDING',
            'gateway_response' => json_encode(['months' => $months, 'discount_percent' => $discountPercent]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        if (!$paymentRecordId) {
            return ['success' => false, 'error' => 'Could not create payment record.'];
        }

        // Call Cashfree API to create order
        $cfResponse = CashfreeGateway::createOrder(
            $orderId,
            $payableAmount,
            'INR',
            $customerName,
            $customerPhone,
            $customerEmail,
            $returnUrl
        );

        if (!$cfResponse['success']) {
            DB::update('membership_payments', [
                'payment_status' => 'FAILED',
                'gateway_response' => json_encode(['months' => $months, 'error' => $cfResponse['error'] ?? null])
            ], 'id = ?', [$paymentRecordId]);
            return ['success' => false, 'error' => $cfResponse['error'] ?? 'Failed to initialize Cashfree order.'];
        }

        DB::update('membership_payments', [
            'gateway_response' => json_encode(['months' => $months, 'discount_percent' => $discountPercent, 'cashfree' => $cfResponse])
        ], 'id = ?', [$paymentRecordId]);

        // Generate dynamic UPI QR URI for scanning
        $cleanUpiId = trim($tenant['upi_id'] ?? '');
        $cleanGymName = urlencode(substr($tenant['gym_name'] ?: 'Gym Owner', 0, 50));
        $cleanNote = urlencode("Renewal-{$planName}-{$months}mo-{$orderId}");
        $formattedAmountStr = number_format($payableAmount, 2, '.', '');
        
        $upiUri = !empty($cleanUpiId) ? "upi://pay?pa={$cleanUpiId}&pn={$cleanGymName}&am={$formattedAmountStr}&cu=INR&tn={$cleanNote}" : '';
        $qrCodeUrl = !empty($upiUri) ? "https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=" . urlencode($upiUri) . "&margin=10" : '';

        return [
            'success' => true,
            'order_id' => $orderId,
            'payment_session_id' => $cfResponse['payment_session_id'],
            'payment_record_id' => $paymentRecordId,
            'payable_amount' => $payableAmount,
            // Cashfree orders are always created in INR
            'currency' => '₹',
            'currency_code' => 'INR',
            'plan_name' => $planName,
            'months' => $months,
            'discount_percent' => $discountPercent,
            'upi_id' => $cleanUpiId,
            'gym_name' => $tenant['gym_name'] ?: 'Gym Owner',
            'upi_url' => $upiUri,
            'qr_code_url' => !empty($tenant['upi_qr']) ? base_url("/uploads/qr/" . $tenant['upi_qr']) : $qrCodeUrl,
            'cashfree_checkout_url' => "https://payments.cashfree.com/order/#" . $cfResponse['payment_session_id'],
            'expires_in_seconds' => 600
        ];
    }

    /**
     * Extract the gateway-confirmed amount from either a verifyOrder() result or a raw webhook payload.
     */
    public static function extractGatewayAmount($gatewayResponse) {
        if (!is_array($gatewayResponse)) return null;
        if (isset($gatewayResponse['order_amount']) && isset($gatewayResponse['status'])) {
            return (float)$gatewayResponse['order_amount'];
        }
        $data = $gatewayResponse['data'] ?? $gatewayResponse;
        if (isset($data['order']['order_amount'])) {
            return (float)$data['order']['order_amount'];
        }
        return null;
    }

    /**
     * Resolve plan duration (months) for a payment from the order record itself.
     * Order: plan_months column -> months stored in gateway_response -> legacy inference.
     */
    private static function resolvePaymentMonths($payment, $rate) {
        if (!empty($payment['plan_months']) && (int)$payment['plan_months'] > 0) {
            return (int)$payment['plan_months'];
        }
        $meta = json_decode((string)($payment['gateway_response'] ?? ''), true);
        if (is_array($meta) && !empty($meta['months']) && (int)$meta['months'] > 0) {
            return (int)$meta['months'];
        }
        // Legacy orders: match the paid amount against the discounted package price
        $amount = (float)$payment['amount'];
        if ($rate && (float)$rate['charge'] > 0) {
            foreach (self::DURATION_DISCOUNTS as $m => $disc) {
                $expected = round((float)$rate['charge'] * $m * (1 - ($disc / 100)), 2);
                if (abs($expected - $amount) <= 0.01) {
                    return $m;
                }
            }
            return max(1, (int)round($amount / (float)$rate['charge']));
        }
        return 1;
    }

    /**
     * Process successful payment in an idempotent, transaction-safe manner.
     * Enqueues plan as active or upcoming.
     *
     * $options:
     *   'verified_amount' => float  gateway-confirmed amount (checked against stored order amount)
     *   'manual'          => bool   staff-approved offline payment (no gateway amount check)
     *   'tenant_id'       => int    enforce that the order belongs to this tenant
     */
    public static function processSuccessfulPayment($cashfreeOrderId, $transactionRef = null, $gatewayResponse = [], $options = []) {
        $isManual = !empty($options['manual']);
        $verifiedAmount = $options['verified_amount'] ?? null;
        if ($verifiedAmount === null && !$isManual) {
            $verifiedAmount = self::extractGatewayAmount($gatewayResponse);
        }

        DB::beginTransaction();
        try {
            // Row lock serialises concurrent webhook / redirect / polling deliveries for the same order
            $payment = DB::fetchOne("SELECT * FROM membership_payments WHERE cashfree_order_id = ? FOR UPDATE", [$cashfreeOrderId]);
            if (!$payment) {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment record not found for order ' . $cashfreeOrderId];
            }

            if (isset($options['tenant_id']) && (int)$options['tenant_id'] !== (int)$payment['tenant_id']) {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment record does not belong to this gym.'];
            }

            // IDEMPOTENCY CHECK: If already processed, return existing subscription
            if ($payment['payment_status'] === 'PAID' && !empty($payment['subscription_id'])) {
                $sub = DB::fetchOne("SELECT * FROM member_subscriptions WHERE id = ?", [$payment['subscription_id']]);
                $existingInvoiceId = DB::fetchValue("SELECT id FROM invoices WHERE tenant_id = ? AND payment_id = ? ORDER BY id DESC LIMIT 1", [$payment['tenant_id'], $payment['id']]);
                DB::commit();
                return [
                    'success' => true,
                    'already_processed' => true,
                    'subscription' => $sub,
                    'subscription_id' => (int)$payment['subscription_id'],
                    'invoice_id' => $existingInvoiceId ? (int)$existingInvoiceId : null,
                    'status' => $sub['status'] ?? null,
                    'start_date' => $sub['start_date'] ?? null,
                    'expiry_date' => $sub['expiry_date'] ?? null,
                    'payment' => $payment
                ];
            }

            // Refunded / cancelled (rejected) orders can never be activated
            if (in_array($payment['payment_status'], ['REFUNDED', 'CANCELLED'], true)) {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment is ' . strtolower($payment['payment_status']) . ' and cannot be activated.'];
            }

            $tenantId = (int)$payment['tenant_id'];
            $memberId = (int)$payment['member_id'];
            $planId = (int)$payment['plan_id'];
            $amount = (float)$payment['amount'];

            if (!$isManual) {
                if ($verifiedAmount === null || abs((float)$verifiedAmount - $amount) > 0.01) {
                    DB::rollback();
                    error_log("SubscriptionEngine: amount mismatch for order {$cashfreeOrderId}: expected {$amount}, gateway " . var_export($verifiedAmount, true));
                    return ['success' => false, 'error' => 'Payment amount could not be verified against the order.'];
                }
            }

            $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
            $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
            if (!$member || !$tenant) {
                DB::rollback();
                return ['success' => false, 'error' => 'Member or tenant missing.'];
            }

            $rate = DB::fetchOne("SELECT * FROM rates WHERE id = ? AND tenant_id = ?", [$planId, $tenantId]);
            $planName = $rate ? $rate['name'] : ($member['services'] ?: 'General Fitness');
            // Months come from the order record, not from amount / rate (which breaks with discounts)
            $months = self::resolvePaymentMonths($payment, $rate);

            // Lock this member's subscription rows so concurrent renewals compute the queue serially
            DB::fetchAll("SELECT id FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? FOR UPDATE", [$tenantId, $memberId]);

            // Calculate seamless dates
            $startDate = self::calculateNextStartDate($tenantId, $memberId);
            $expiryDate = self::calculateExpiryDate($startDate, $months);
            $today = date('Y-m-d');

            // Check if member has an currently active subscription
            $activeSub = DB::fetchOne(
                "SELECT id FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'active' AND expiry_date >= ?",
                [$tenantId, $memberId, $today]
            );

            $status = ($activeSub || strtotime($startDate) > strtotime($today)) ? 'upcoming' : 'active';

            // Count existing upcoming plans to set queue_position
            $upcomingCount = (int)DB::fetchValue(
                "SELECT COUNT(*) FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'upcoming'",
                [$tenantId, $memberId]
            );
            $queuePosition = ($status === 'upcoming') ? ($upcomingCount + 1) : 1;

            // Create subscription snapshot record
            $subscriptionId = DB::insert('member_subscriptions', [
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'plan_id' => $planId,
                'plan_name_snapshot' => $planName,
                'plan_price_snapshot' => $amount,
                'plan_duration_snapshot' => $months,
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'status' => $status,
                'payment_id' => $payment['id'],
                'queue_position' => $queuePosition,
                'activated_at' => ($status === 'active') ? date('Y-m-d H:i:s') : null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            if (!$subscriptionId) {
                throw new RuntimeException('Failed to create member subscription record.');
            }

            // Update payment record (keep stored months; keep gateway payload for audit)
            $storedResponse = $gatewayResponse;
            if (is_array($storedResponse)) {
                $storedResponse['months'] = $months;
            }
            DB::update('membership_payments', [
                'subscription_id' => $subscriptionId,
                'cashfree_payment_id' => $transactionRef ?: ($payment['cashfree_payment_id'] ?: $cashfreeOrderId),
                'payment_status' => 'PAID',
                'gateway_response' => json_encode($storedResponse),
                'paid_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$payment['id']]);

            $paymentMethodLabel = $isManual ? ($payment['payment_method'] ?: 'Manual') : 'Cashfree Online';
            $serviceLabel = $planName . ($status === 'upcoming' ? ' (Upcoming)' : '');
            $txnRefFinal = $transactionRef ?: ($payment['cashfree_payment_id'] ?: $cashfreeOrderId);

            // Reuse a pending-verification invoice for this payment if one exists, else create one
            $invoiceId = (int)DB::fetchValue("SELECT id FROM invoices WHERE tenant_id = ? AND payment_id = ? ORDER BY id DESC LIMIT 1", [$tenantId, $payment['id']]);
            if ($invoiceId > 0) {
                DB::update('invoices', [
                    'service_name' => $serviceLabel,
                    'plan_months' => $months,
                    'paid_amount' => $amount,
                    'payment_date' => $today,
                    'status' => 'paid',
                    'transaction_ref' => $txnRefFinal
                ], 'id = ?', [$invoiceId]);
                $invoiceNumber = DB::fetchValue("SELECT invoice_number FROM invoices WHERE id = ?", [$invoiceId]);
            } else {
                $invoiceId = DB::insert('invoices', [
                    'tenant_id' => $tenantId,
                    'branch_id' => (int)($member['branch_id'] ?? 1),
                    'member_id' => $memberId,
                    'payment_id' => $payment['id'],
                    'invoice_number' => self::tempInvoiceNumber(),
                    'service_name' => $serviceLabel,
                    'plan_months' => $months,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'discount' => 0.00,
                    'payment_method' => $paymentMethodLabel,
                    'payment_date' => $today,
                    'status' => 'Paid',
                    'transaction_ref' => $txnRefFinal,
                    'notes' => $isManual ? 'Member renewal verified by gym staff' : 'Online renewal via Cashfree payment gateway',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                if (!$invoiceId) {
                    throw new RuntimeException('Failed to create invoice record.');
                }
                // Collision-free invoice number derived from the invoice id
                $invoiceNumber = self::assignInvoiceNumber($invoiceId, $tenant, $today);
            }

            // If status == 'active', update main members table for backward compatibility.
            // paid_date = START of the period (expiry is derived as paid_date + plan months).
            if ($status === 'active') {
                DB::update('members', [
                    'services' => $planName,
                    'amount' => $amount,
                    'plan' => $months,
                    'paid_date' => $startDate,
                    'status' => 'Active',
                    'reminder' => 0
                ], 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            error_log('SubscriptionEngine::processSuccessfulPayment error for ' . $cashfreeOrderId . ': ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not activate payment. Please retry or contact support.'];
        }

        return [
            'success' => true,
            'already_processed' => false,
            'subscription_id' => $subscriptionId,
            'invoice_id' => (int)$invoiceId,
            'invoice_number' => $invoiceNumber ?? null,
            'status' => $status,
            'plan_name' => $planName,
            'months' => $months,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'queue_position' => $queuePosition,
            'message' => ($status === 'upcoming')
                ? "Your new plan has been successfully scheduled and will activate automatically on {$startDate} after your current plan expires."
                : "Your membership plan has been activated successfully until {$expiryDate}."
        ];
    }

    /**
     * Mark a not-yet-paid member payment as failed / cancelled (never touches PAID rows).
     */
    public static function markPaymentNotPaid($cashfreeOrderId, $newStatus = 'FAILED', $gatewayResponse = null) {
        $newStatus = in_array($newStatus, ['FAILED', 'CANCELLED'], true) ? $newStatus : 'FAILED';
        $res = DB::query(
            "UPDATE membership_payments SET payment_status = ?" . ($gatewayResponse !== null ? ", gateway_response = ?" : "") . " WHERE cashfree_order_id = ? AND payment_status = 'PENDING'",
            $gatewayResponse !== null ? [$newStatus, json_encode($gatewayResponse), $cashfreeOrderId] : [$newStatus, $cashfreeOrderId]
        );
        return is_array($res) ? $res['affected'] : 0;
    }

    /**
     * Auto-activation Engine & Request-Time Fallback Logic.
     * Auto-expires past memberships and promotes queued 'upcoming' plans to 'active'.
     */
    public static function activateUpcomingSubscriptions($tenantId = null) {
        $today = date('Y-m-d');
        $params = [$today];
        $tenantFilter = "";
        if ($tenantId !== null && $tenantId > 0) {
            $tenantFilter = " AND tenant_id = ? ";
            $params[] = (int)$tenantId;
        }

        // 1. Mark active subscriptions whose expiry_date < today as 'expired'
        $expiredSubs = DB::fetchAll(
            "SELECT * FROM member_subscriptions 
             WHERE status = 'active' AND expiry_date < ? {$tenantFilter}",
            $params
        );

        foreach ($expiredSubs as $sub) {
            DB::update('member_subscriptions', [
                'status' => 'expired',
                'expired_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$sub['id']]);

            // Update main members status
            DB::update('members', ['status' => 'Expired'], 'user_id = ? AND tenant_id = ?', [$sub['member_id'], $sub['tenant_id']]);
        }

        // 2. Find members who need activation of their upcoming plan
        $upcomingSql = "SELECT * FROM member_subscriptions 
                        WHERE status = 'upcoming' AND start_date <= ? {$tenantFilter}
                        ORDER BY start_date ASC, queue_position ASC";
        $upcomingSubs = DB::fetchAll($upcomingSql, $params);

        $activatedCount = 0;
        foreach ($upcomingSubs as $upSub) {
            $mId = $upSub['member_id'];
            $tId = $upSub['tenant_id'];

            // A queued plan whose whole window already passed (engine not run for a while) is expired, not active
            if ($upSub['expiry_date'] < $today) {
                DB::update('member_subscriptions', [
                    'status' => 'expired',
                    'activated_at' => date('Y-m-d H:i:s'),
                    'expired_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND status = ?', [$upSub['id'], 'upcoming']);
                continue;
            }

            // Check if member ALREADY has an active plan right now
            $hasActive = DB::fetchValue(
                "SELECT COUNT(*) FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'active'",
                [$tId, $mId]
            );

            if ($hasActive == 0) {
                // Promote to active! (guarded on status so concurrent requests don't double-activate)
                $promoted = DB::update('member_subscriptions', [
                    'status' => 'active',
                    'activated_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND status = ?', [$upSub['id'], 'upcoming']);
                if (!$promoted) {
                    continue;
                }

                // Update main members table (paid_date = period start)
                DB::update('members', [
                    'services' => $upSub['plan_name_snapshot'],
                    'amount' => $upSub['plan_price_snapshot'],
                    'plan' => $upSub['plan_duration_snapshot'],
                    'paid_date' => $upSub['start_date'],
                    'status' => 'Active',
                    'reminder' => 0
                ], 'user_id = ? AND tenant_id = ?', [$mId, $tId]);

                $activatedCount++;
            }
        }

        return [
            'expired_count' => count($expiredSubs),
            'activated_count' => $activatedCount
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SaaS (gym → platform) subscription billing
    // ─────────────────────────────────────────────────────────────────────

    /** Number of months a SaaS billing cycle covers. */
    public static function saasCycleMonths($cycle) {
        return ($cycle === 'yearly') ? 12 : (($cycle === 'quarterly') ? 3 : 1);
    }

    /**
     * Price of a SaaS plan for a billing cycle. Returns null when the plan has no price for that cycle
     * (never silently charge ₹1 / the monthly price for a yearly term).
     */
    public static function saasCyclePrice($plan, $cycle) {
        $monthly = (float)($plan['price_monthly'] ?? 0);
        $yearly = (float)($plan['price_yearly'] ?? 0);
        switch ($cycle) {
            case 'yearly':
                return $yearly > 0 ? round($yearly, 2) : null;
            case 'quarterly':
                return $monthly > 0 ? round($monthly * 3 * 0.90, 2) : null; // 10% quarterly discount
            case 'monthly':
                return $monthly > 0 ? round($monthly, 2) : null;
            default:
                return null;
        }
    }

    /**
     * Platform-level SaaS tax percent (GST). Configured by the platform via env SAAS_TAX_PERCENT,
     * never by the tenant being billed.
     */
    public static function saasTaxPercent() {
        $pct = (float)env('SAAS_TAX_PERCENT', 0);
        return ($pct > 0 && $pct < 100) ? $pct : 0.0;
    }

    /**
     * Validate a SaaS coupon (active, not expired, usage limit not reached) and compute the discount.
     */
    public static function validateSaasCoupon($code, $baseAmount) {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return ['valid' => true, 'discount' => 0.0, 'coupon' => null, 'code' => null];
        }
        $coupon = DB::fetchOne("SELECT * FROM saas_coupons WHERE code = ?", [$code]);
        if (!$coupon || (int)$coupon['is_active'] !== 1) {
            return ['valid' => false, 'error' => 'Coupon code is invalid or inactive.'];
        }
        if (!empty($coupon['expiry_date']) && strpos((string)$coupon['expiry_date'], '0000-00-00') !== 0 && $coupon['expiry_date'] < date('Y-m-d')) {
            return ['valid' => false, 'error' => 'Coupon code has expired.'];
        }
        if ((int)$coupon['max_uses'] > 0 && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
            return ['valid' => false, 'error' => 'Coupon usage limit has been reached.'];
        }
        $discount = 0.0;
        if ((float)$coupon['discount_percent'] > 0) {
            $discount = ($baseAmount * min(100, (float)$coupon['discount_percent'])) / 100;
        } elseif ((float)$coupon['discount_fixed'] > 0) {
            $discount = (float)$coupon['discount_fixed'];
        }
        $discount = round(min($discount, $baseAmount), 2);
        return ['valid' => true, 'discount' => $discount, 'coupon' => $coupon, 'code' => $code];
    }

    /**
     * Server-side quote for a SaaS renewal / upgrade.
     */
    public static function quoteSaasRenewal($planId, $cycle, $couponCode = '') {
        if (!in_array($cycle, ['monthly', 'quarterly', 'yearly'], true)) {
            return ['success' => false, 'error' => 'Invalid billing cycle.'];
        }
        $plan = DB::fetchOne("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1", [(int)$planId]);
        if (!$plan) {
            return ['success' => false, 'error' => 'Invalid plan selected.'];
        }
        $basePrice = self::saasCyclePrice($plan, $cycle);
        if ($basePrice === null) {
            return ['success' => false, 'error' => 'The selected plan is not available for ' . $cycle . ' billing.'];
        }
        $couponRes = self::validateSaasCoupon($couponCode, $basePrice);
        if (!$couponRes['valid']) {
            return ['success' => false, 'error' => $couponRes['error']];
        }
        $discount = $couponRes['discount'];
        $taxable = max(0, $basePrice - $discount);
        $tax = round($taxable * (self::saasTaxPercent() / 100), 2);
        $total = round($taxable + $tax, 2);

        return [
            'success' => true,
            'plan' => $plan,
            'cycle' => $cycle,
            'months' => self::saasCycleMonths($cycle),
            'base_price' => $basePrice,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'coupon_code' => $couponRes['code']
        ];
    }

    /**
     * Apply an (approved / gateway-verified) SaaS payment: extend tenant expiry from the later of
     * today or current expiry, switch plan, mark payment approved, count coupon usage.
     * Idempotent and row-locked.
     *
     * $options: 'approved_by' => int, 'expected_tenant_id' => int, 'transaction_id' => string
     */
    public static function applySaasPayment($paymentId, $options = []) {
        DB::beginTransaction();
        try {
            $p = DB::fetchOne("SELECT * FROM saas_payments WHERE id = ? FOR UPDATE", [(int)$paymentId]);
            if (!$p) {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment record not found.'];
            }
            if (isset($options['expected_tenant_id']) && (int)$options['expected_tenant_id'] !== (int)$p['tenant_id']) {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment does not belong to this gym.'];
            }
            if ($p['status'] === 'approved') {
                DB::commit();
                return ['success' => true, 'already_processed' => true, 'end_date' => $p['end_date'], 'payment' => $p];
            }
            if ($p['status'] === 'rejected') {
                DB::rollback();
                return ['success' => false, 'error' => 'Payment was rejected and cannot be applied.'];
            }
            if ((int)$p['tenant_id'] <= 0) {
                DB::rollback();
                return ['success' => false, 'error' => 'Onboarding payments are activated by the provisioning flow.'];
            }

            $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ? FOR UPDATE", [(int)$p['tenant_id']]);
            if (!$tenant) {
                DB::rollback();
                return ['success' => false, 'error' => 'Gym not found.'];
            }

            $months = self::saasCycleMonths($p['billing_cycle']);
            $today = date('Y-m-d');
            $currentExpiry = (!empty($tenant['subscription_expiry']) && strpos((string)$tenant['subscription_expiry'], '0000-00-00') !== 0)
                ? date('Y-m-d', strtotime($tenant['subscription_expiry'])) : null;

            // Extend from current expiry while still valid; otherwise start fresh today
            $startDate = ($currentExpiry && $currentExpiry >= $today) ? $currentExpiry : $today;
            $newExpiry = self::addMonths($startDate, $months);

            $update = [
                'status' => 'approved',
                'start_date' => $startDate,
                'end_date' => $newExpiry
            ];
            if (!empty($options['approved_by'])) {
                $update['approved_by'] = (int)$options['approved_by'];
            }
            if (!empty($options['transaction_id'])) {
                $update['cf_payment_id'] = $options['transaction_id'];
            }
            DB::update('saas_payments', $update, 'id = ?', [$p['id']]);

            // Suspensions / cancellations are administrative decisions and are not lifted by a payment
            $tenantStatus = in_array($tenant['status'], ['suspended', 'cancelled'], true) ? $tenant['status'] : 'active';
            DB::update('tenants', [
                'subscription_plan_id' => (int)$p['plan_id'],
                'subscription_expiry' => $newExpiry,
                'status' => $tenantStatus
            ], 'id = ?', [$tenant['id']]);

            // Count coupon usage only for successful payments
            if (!empty($p['coupon_code'])) {
                DB::query("UPDATE saas_coupons SET used_count = used_count + 1 WHERE code = ?", [$p['coupon_code']]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            error_log('SubscriptionEngine::applySaasPayment error for #' . $paymentId . ': ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not apply subscription payment.'];
        }

        return ['success' => true, 'already_processed' => false, 'start_date' => $startDate, 'end_date' => $newExpiry, 'tenant_id' => (int)$p['tenant_id']];
    }

    /**
     * Verify a SaaS renewal Cashfree order with the gateway and apply it if paid in full.
     * Used by the admin callback and the webhooks.
     */
    public static function finalizeSaasGatewayOrder($orderId, $expectedTenantId = null) {
        $p = DB::fetchOne("SELECT * FROM saas_payments WHERE transaction_ref = ? AND tenant_id > 0", [$orderId]);
        if (!$p) {
            return ['success' => false, 'not_found' => true, 'error' => 'Subscription order not found.'];
        }
        if ($expectedTenantId !== null && (int)$p['tenant_id'] !== (int)$expectedTenantId) {
            return ['success' => false, 'error' => 'Subscription order does not belong to this gym.'];
        }
        if ($p['status'] === 'approved') {
            return ['success' => true, 'already_processed' => true, 'end_date' => $p['end_date']];
        }

        $verify = CashfreeGateway::verifyOrder($orderId);
        if (!$verify['success'] || $verify['status'] !== 'PAID') {
            $orderStatus = strtoupper($verify['order_status'] ?? '');
            if (in_array($orderStatus, ['EXPIRED', 'TERMINATED', 'CANCELLED'], true)) {
                DB::query("UPDATE saas_payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'", [$orderStatus, $p['id']]);
            }
            return ['success' => false, 'status' => $verify['status'] ?? 'UNKNOWN', 'error' => $verify['error'] ?? 'Payment not completed.'];
        }
        if (strtoupper($verify['currency'] ?? 'INR') !== 'INR' || abs((float)$verify['order_amount'] - (float)$p['total_payable']) > 0.01) {
            error_log("SaaS order {$orderId} amount/currency mismatch: expected {$p['total_payable']} INR, got {$verify['order_amount']} {$verify['currency']}");
            return ['success' => false, 'status' => 'MISMATCH', 'error' => 'Paid amount does not match the subscription order. Contact support.'];
        }

        $res = self::applySaasPayment($p['id'], ['transaction_id' => $verify['transaction_id'] ?? null]);
        $res['transaction_id'] = $verify['transaction_id'] ?? null;
        $res['payment_method'] = $verify['payment_method'] ?? null;
        return $res;
    }
}
