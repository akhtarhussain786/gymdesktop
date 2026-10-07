<?php
/**
 * Member Check Payment Status Endpoint
 * Verifies payment status with Cashfree API and processes payment idempotently.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = trim($jsonInput['order_id'] ?? $_GET['order_id'] ?? $_POST['order_id'] ?? '');

if (empty($orderId)) {
    ApiResponse::error('Order ID is required.', 422);
}

// 1. Check local payment record
$payment = DB::fetchOne("SELECT * FROM membership_payments WHERE cashfree_order_id = ? AND tenant_id = ? AND member_id = ?", [$orderId, $tenantId, $memberId]);
if (!$payment) {
    ApiResponse::error('Payment record not found.', 404);
}

// 2. Call Cashfree Gateway API to verify actual order status
$verifyRes = CashfreeGateway::verifyOrder($orderId);

if (!$verifyRes['success']) {
    // Gateway unreachable: report our stored state instead of guessing
    ApiResponse::success([
        'payment_status' => $payment['payment_status'],
        'order_id' => $orderId,
        'message' => 'Could not reach the payment gateway right now. Please check again shortly.'
    ], 'Payment status fetched.');
}

$status = $verifyRes['status'] ?? 'PENDING';
$orderStatus = strtoupper($verifyRes['order_status'] ?? '');
$transactionId = $verifyRes['transaction_id'] ?? $orderId;

if ($status === 'PAID') {
    // Process payment idempotently (amount is re-checked against the stored order)
    $processRes = SubscriptionEngine::processSuccessfulPayment($orderId, $transactionId, $verifyRes, [
        'verified_amount' => (float)($verifyRes['order_amount'] ?? 0),
        'tenant_id' => $tenantId
    ]);
    if (!$processRes['success']) {
        ApiResponse::error($processRes['error'] ?? 'Payment received but could not be activated. Please contact the gym.', 409);
    }
    if ($processRes['success']) {
        ApiResponse::success([
            'payment_status' => 'PAID',
            'order_id' => $orderId,
            'subscription_status' => $processRes['status'] ?? 'upcoming',
            'scheduled_start_date' => $processRes['start_date'] ?? date('Y-m-d'),
            'scheduled_expiry_date' => $processRes['expiry_date'] ?? date('Y-m-d'),
            'message' => $processRes['message'] ?? 'Payment verified successfully.'
        ], 'Payment verified successfully!');
    }
}

// A failed attempt does not close a Cashfree order — while the order is ACTIVE it is still pending.
if ($orderStatus === 'ACTIVE') {
    $status = 'PENDING';
} elseif (in_array($orderStatus, ['EXPIRED', 'TERMINATED', 'CANCELLED'], true)) {
    SubscriptionEngine::markPaymentNotPaid($orderId, 'FAILED');
    $status = 'FAILED';
}

ApiResponse::success([
    'payment_status' => $status,
    'order_id' => $orderId,
    'message' => ($status === 'PENDING') ? 'Payment is still pending. Please complete transaction in your UPI App.' : 'Payment status: ' . $status
], 'Payment status fetched.');
