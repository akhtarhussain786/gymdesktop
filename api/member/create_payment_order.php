<?php
/**
 * Member Create Cashfree Payment Order Endpoint
 * Calculates backend price securely and initializes Cashfree payment session and UPI QR details.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];
$member = $auth['member'];

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$planId = (int)($jsonInput['plan_id'] ?? $_POST['plan_id'] ?? 0);
$months = (int)($jsonInput['months'] ?? $_POST['months'] ?? 1);

// Auto-resolve planId if not explicitly passed
if ($planId <= 0 && !empty($member['services'])) {
    $planId = (int)DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND name = ? LIMIT 1", [$tenantId, $member['services']]);
}
if ($planId <= 0) {
    Tenant::ensureDefaultRates($tenantId);
    $planId = (int)DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND charge > 0 ORDER BY charge ASC, id ASC LIMIT 1", [$tenantId]);
}

if ($planId <= 0) {
    ApiResponse::error('No active membership plan available for renewal.', 422);
}

$res = SubscriptionEngine::createPendingPaymentOrder($tenantId, $memberId, $planId, $months);

if (!$res['success']) {
    ApiResponse::error($res['error'] ?? 'Could not create payment order.', 400);
}

ApiResponse::success($res, 'Payment order initialized successfully.');
