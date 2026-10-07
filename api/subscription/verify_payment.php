<?php
/**
 * API: Verify Payment & Automatically Activate SaaS Subscription
 * Endpoint: POST /api/subscription/verify_payment.php
 * 
 * Rules:
 * - 100% Server-side Gateway Verification (never trusting client callback alone)
 * - Idempotent (prevents double extension on multiple submissions)
 * - Date Stacking (extends from current expiry date if still in future)
 * - Automatic Activation (no Super Admin / Admin manual approval required)
 */

require_once __DIR__ . '/../admin/middleware.php';
require_once __DIR__ . '/../../core/cashfree.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$orderId = trim($input['order_id'] ?? '');

if (empty($orderId)) {
    ApiResponse::error('Order ID is required for verification', 400);
}

// 1. Secure Server-side Verification with Cashfree REST API
$result = SubscriptionEngine::finalizeSaasGatewayOrder($orderId, $tenantId);

if (!$result['success']) {
    ApiResponse::error($result['error'] ?? 'Payment was not completed. Please try again.', 400);
}

// 2. Fetch updated tenant subscription state
$updatedTenant = DB::fetchOne("SELECT t.*, sp.name as plan_name, sp.max_members, sp.max_staff 
                              FROM tenants t 
                              LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id 
                              WHERE t.id = ?", [$tenantId]);

$subStatus = Tenant::getSubscriptionStatus();

ApiResponse::success([
    'status' => 'active',
    'verified' => true,
    'order_id' => $orderId,
    'plan_id' => (int)($updatedTenant['subscription_plan_id'] ?? 0),
    'plan_name' => $updatedTenant['plan_name'] ?? 'Active SaaS Plan',
    'start_date' => $updatedTenant['subscription_start'] ?? date('Y-m-d'),
    'expiry_date' => $updatedTenant['subscription_expiry'] ?? date('Y-m-d'),
    'days_remaining' => (int)($subStatus['days_left'] ?? 30),
    'max_members' => (int)($updatedTenant['max_members'] ?? 100),
    'max_staff' => (int)($updatedTenant['max_staff'] ?? 10),
    'message' => 'Subscription activated successfully'
], 'Subscription activated successfully');
