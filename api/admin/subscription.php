<?php
/**
 * Gym Admin SaaS Subscription & Renewal API
 * Provides current SaaS validity, plan catalog, Cashfree order creation & verification
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/cashfree.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$tenant = DB::fetchOne("SELECT t.*, sp.name as plan_name, sp.max_members, sp.max_staff, sp.max_branches 
                        FROM tenants t 
                        LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id 
                        WHERE t.id = ?", [$tenantId]);

if (!$tenant) {
    ApiResponse::error('Tenant not found', 404);
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle POST: Create order or Verify payment
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = strtolower(trim($input['action'] ?? 'create_order'));

    // Action 1: Create Cashfree SaaS Renewal Order
    if ($action === 'create_order') {
        $planId = (int)($input['plan_id'] ?? 0);
        $cycle = strtolower(trim($input['billing_cycle'] ?? 'monthly'));
        $couponCode = strtoupper(trim($input['coupon_code'] ?? ''));

        if (!in_array($cycle, ['monthly', 'quarterly', 'yearly'], true)) {
            $cycle = 'monthly';
        }

        $quote = SubscriptionEngine::quoteSaasRenewal($planId, $cycle, $couponCode);
        if (!$quote['success']) {
            ApiResponse::error($quote['error'] ?? 'Could not calculate plan pricing', 400);
        }

        $selectedPlan = $quote['plan'];
        $basePrice    = (float)$quote['base_price'];
        $discount     = (float)$quote['discount'];
        $tax          = (float)$quote['tax'];
        $totalPayable = (float)$quote['total'];
        $couponCode   = $quote['coupon_code'];

        if ($totalPayable < 1) {
            ApiResponse::error('Payable amount is below minimum online transaction limit', 400);
        }

        $orderId = CashfreeGateway::generateOrderId('SAAS_MOB');
        $today = date('Y-m-d');

        $saasPaymentId = DB::insert('saas_payments', [
            'tenant_id'       => $tenantId,
            'plan_id'         => $planId,
            'billing_cycle'   => $cycle,
            'amount'          => $basePrice,
            'tax_amount'      => $tax,
            'discount_amount' => $discount,
            'total_payable'   => $totalPayable,
            'coupon_code'     => $couponCode,
            'payment_method'  => 'cashfree',
            'transaction_ref' => $orderId,
            'status'          => 'pending',
            'start_date'      => $today,
            'end_date'        => $today,
            'notes'           => 'Mobile SaaS renewal order via Cashfree'
        ]);

        if (!$saasPaymentId) {
            ApiResponse::error('Could not initiate payment order in database', 500);
        }

        $returnUrl = base_url('/saas-renew-callback.php?order_id=' . urlencode($orderId));
        $customerName = !empty($tenant['owner_name']) ? $tenant['owner_name'] : ($tenant['gym_name'] ?: 'Gym Admin');
        $customerPhone = !empty($tenant['phone']) ? $tenant['phone'] : '9999999999';
        $customerEmail = !empty($tenant['email']) ? $tenant['email'] : 'admin@fitisify.com';

        $cfResult = CashfreeGateway::createOrder(
            $orderId,
            $totalPayable,
            'INR',
            $customerName,
            $customerPhone,
            $customerEmail,
            $returnUrl
        );

        if (!$cfResult['success']) {
            $errorMsg = $cfResult['error'] ?? 'Cashfree gateway initialization failed';
            DB::update('saas_payments', ['status' => 'failed'], 'id = ?', [$saasPaymentId]);
            ApiResponse::error('Cashfree Error: ' . $errorMsg, 500);
        }

        $paymentSessionId = $cfResult['payment_session_id'];
        $checkoutUrl = base_url('/saas-renew-checkout.php?order_id=' . urlencode($orderId) . '&payment_session_id=' . urlencode($paymentSessionId));

        // Generate native UPI Intent payload and app-specific links
        $upiLinks = [];
        $upiIntentUrl = null;
        try {
            $upiRes = CashfreeGateway::createUpiPayment($paymentSessionId, 'link');
            if (!empty($upiRes['data']['payload'])) {
                if (is_array($upiRes['data']['payload'])) {
                    $upiLinks = $upiRes['data']['payload'];
                    $upiIntentUrl = $upiLinks['default'] ?? reset($upiLinks);
                } elseif (is_string($upiRes['data']['payload'])) {
                    $upiIntentUrl = $upiRes['data']['payload'];
                    $upiLinks['default'] = $upiIntentUrl;
                }
            }
            if (empty($upiIntentUrl) && !empty($upiRes['data']['link'])) {
                $upiLinks['cashfree_link'] = $upiRes['data']['link'];
            }
        } catch (Throwable $e) {
            error_log("Cashfree createUpiPayment error: " . $e->getMessage());
        }

        ApiResponse::success([
            'order_id' => $orderId,
            'payment_session_id' => $paymentSessionId,
            'upi_intent_url' => $upiIntentUrl,
            'upi_links' => $upiLinks,
            'checkout_url' => $checkoutUrl,
            'total_payable' => $totalPayable,
            'currency' => $tenant['currency'] ?? '₹',
            'plan_name' => $selectedPlan['name'] ?? 'SaaS Plan',
            'billing_cycle' => $cycle,
            'cashfree_mode' => CashfreeGateway::getMode(),
            'notes' => 'Open direct UPI app or Cashfree checkout.'
        ], 'Cashfree payment order created successfully');
    }

    // Action 2: Verify & Finalize Order
    if ($action === 'verify_order' || $action === 'verify_payment') {
        $orderId = trim($input['order_id'] ?? '');
        if (empty($orderId)) {
            ApiResponse::error('Order ID is required', 400);
        }

        $result = SubscriptionEngine::finalizeSaasGatewayOrder($orderId, $tenantId);
        if (!$result['success']) {
            ApiResponse::error($result['error'] ?? 'Payment has not been completed or verified yet', 400);
        }

        // Return refreshed tenant subscription info
        $updatedTenant = DB::fetchOne("SELECT t.*, sp.name as plan_name, sp.max_members, sp.max_staff 
                                      FROM tenants t 
                                      LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id 
                                      WHERE t.id = ?", [$tenantId]);

        $subStatus = Tenant::getSubscriptionStatus();

        ApiResponse::success([
            'verified' => true,
            'order_id' => $orderId,
            'status' => 'approved',
            'new_expiry_date' => $updatedTenant['subscription_expiry'],
            'days_remaining' => $subStatus['days_left'] ?? 30,
            'plan_name' => $updatedTenant['plan_name'] ?? 'Active Plan',
            'message' => 'SaaS subscription successfully renewed and extended until ' . date('d M Y', strtotime($updatedTenant['subscription_expiry']))
        ], 'SaaS Subscription renewed successfully');
    }

    ApiResponse::error('Unsupported action', 400);
}

// GET: Return Full Subscription Overview, Plans Catalog & Payment History
$subStatus = Tenant::getSubscriptionStatus();

$currentMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status <> 'Trash'", [$tenantId]);
$currentStaff = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ?", [$tenantId]);

// Plan Catalog
$plansRaw = DB::fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
$plansList = [];
foreach ($plansRaw as $p) {
    $features = [];
    if (!empty($p['features'])) {
        $decoded = json_decode($p['features'], true);
        if (is_array($decoded)) {
            $features = $decoded;
        }
    }

    $monthly = (float)$p['price_monthly'];
    $yearly = (float)$p['price_yearly'];
    $quarterly = round($monthly * 3 * 0.90, 2); // 10% discount default on 3 months

    $plansList[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'price_monthly' => $monthly,
        'price_quarterly' => $quarterly,
        'price_yearly' => $yearly,
        'trial_days' => (int)$p['trial_days'],
        'grace_period_days' => (int)$p['grace_period_days'],
        'max_members' => (int)$p['max_members'],
        'max_staff' => (int)$p['max_staff'],
        'max_branches' => (int)($p['max_branches'] ?? 1),
        'features' => $features,
        'is_current' => ((int)$p['id'] === (int)($tenant['subscription_plan_id'] ?? 0))
    ];
}

// Payment History
$historyRaw = DB::fetchAll("SELECT p.*, sp.name as plan_name 
                            FROM saas_payments p 
                            LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                            WHERE p.tenant_id = ? 
                            ORDER BY p.id DESC LIMIT 15", [$tenantId]);
$historyList = [];
foreach ($historyRaw as $h) {
    $historyList[] = [
        'id' => (int)$h['id'],
        'plan_name' => $h['plan_name'] ?? 'SaaS Subscription',
        'billing_cycle' => $h['billing_cycle'] ?? 'monthly',
        'amount' => (float)$h['amount'],
        'tax_amount' => (float)$h['tax_amount'],
        'discount_amount' => (float)$h['discount_amount'],
        'total_payable' => (float)$h['total_payable'],
        'payment_method' => $h['payment_method'] ?? 'cashfree',
        'transaction_ref' => $h['transaction_ref'] ?? '',
        'status' => $h['status'] ?? 'pending',
        'created_at' => $h['created_at'],
        'start_date' => $h['start_date'],
        'end_date' => $h['end_date']
    ];
}

$appId = CashfreeGateway::getAppId();
$secretKey = CashfreeGateway::getSecretKey();
$cashfreeAvailable = !empty($appId) && !empty($secretKey);

// Auto-check and send email reminder if subscription is expiring in <= 5 days
if (isset($subStatus['days_left']) && (int)$subStatus['days_left'] <= 5 && (int)$subStatus['days_left'] >= 0) {
    try {
        SubscriptionEngine::checkAndSendSaasExpiryReminders($tenantId);
    } catch (Throwable $e) {
        error_log('Error checking SaaS expiry reminders: ' . $e->getMessage());
    }
}

ApiResponse::success([
    'current_subscription' => [
        'plan_id' => (int)($tenant['subscription_plan_id'] ?? 0),
        'plan_name' => $tenant['plan_name'] ?? 'Standard SaaS Plan',
        'subscription_start' => $tenant['subscription_start'] ?? date('Y-m-d'),
        'subscription_expiry' => $tenant['subscription_expiry'] ?? date('Y-m-d'),
        'days_remaining' => (int)($subStatus['days_left'] ?? 0),
        'state' => $subStatus['state'] ?? 'active',
        'is_active' => (bool)($subStatus['is_active'] ?? true),
        'can_write' => (bool)($subStatus['can_write'] ?? true),
        'message' => $subStatus['message'] ?? null,
        'max_members' => (int)($tenant['max_members'] ?? 100),
        'max_staff' => (int)($tenant['max_staff'] ?? 10),
        'current_members' => $currentMembers,
        'current_staff' => $currentStaff
    ],
    'plans' => $plansList,
    'payment_history' => $historyList,
    'cashfree' => [
        'available' => $cashfreeAvailable,
        'mode' => CashfreeGateway::getMode(),
    ],
    'currency' => $tenant['currency'] ?? '₹'
], 'Subscription details retrieved successfully');
