<?php
/**
 * API: Create SaaS Subscription Payment Order
 * Endpoint: POST /api/subscription/create_order.php
 * 
 * Strict Security: Price is determined ONLY from database; Flutter request amount is never accepted.
 */

require_once __DIR__ . '/../admin/middleware.php';
require_once __DIR__ . '/../../core/cashfree.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$planId = (int)($input['plan_id'] ?? 0);
$billingCycle = strtolower(trim($input['billing_cycle'] ?? 'monthly'));
$couponCode = strtoupper(trim($input['coupon_code'] ?? ''));

if ($planId <= 0) {
    ApiResponse::error('Valid plan_id is required', 400);
}

if (!in_array($billingCycle, ['monthly', 'quarterly', 'yearly'], true)) {
    $billingCycle = 'monthly';
}

$tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
if (!$tenant) {
    ApiResponse::error('Tenant account not found', 404);
}

// 1. Validate plan and get price strictly from DATABASE
$quote = SubscriptionEngine::quoteSaasRenewal($planId, $billingCycle, $couponCode);
if (!$quote['success']) {
    ApiResponse::error($quote['error'] ?? 'Could not calculate subscription pricing', 400);
}

$selectedPlan = $quote['plan'];
$basePrice    = (float)$quote['base_price'];
$discount     = (float)$quote['discount'];
$tax          = (float)$quote['tax'];
$totalPayable = (float)$quote['total'];
$appliedCoupon = $quote['coupon_code'];

if ($totalPayable < 1) {
    ApiResponse::error('Payable amount is below minimum online transaction limit', 400);
}

// 2. Generate unique order ID
$orderId = CashfreeGateway::generateOrderId('SAAS_SUB');
$today = date('Y-m-d');

// 3. Save pending payment record in database
$saasPaymentId = DB::insert('saas_payments', [
    'tenant_id'       => $tenantId,
    'plan_id'         => $planId,
    'billing_cycle'   => $billingCycle,
    'amount'          => $basePrice,
    'tax_amount'      => $tax,
    'discount_amount' => $discount,
    'total_payable'   => $totalPayable,
    'coupon_code'     => $appliedCoupon,
    'payment_method'  => 'cashfree',
    'transaction_ref' => $orderId,
    'status'          => 'pending',
    'start_date'      => $today,
    'end_date'        => $today,
    'notes'           => 'Subscription order created via API'
]);

if (!$saasPaymentId) {
    ApiResponse::error('Could not initiate subscription order in database', 500);
}

// 4. Create Cashfree Order
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
    ApiResponse::error('Payment Gateway Error: ' . $errorMsg, 500);
}

$paymentSessionId = $cfResult['payment_session_id'];
$checkoutUrl = base_url('/saas-renew-checkout.php?order_id=' . urlencode($orderId) . '&payment_session_id=' . urlencode($paymentSessionId));

ApiResponse::success([
    'order_id' => $orderId,
    'payment_session_id' => $paymentSessionId,
    'checkout_url' => $checkoutUrl,
    'amount' => $totalPayable,
    'currency' => $tenant['currency'] ?? '₹',
    'plan_id' => $planId,
    'plan_name' => $selectedPlan['name'] ?? 'SaaS Plan',
    'billing_cycle' => $billingCycle,
    'status' => 'pending'
], 'Subscription payment order created successfully');
