<?php
/**
 * Cashfree Webhook Handler (Member Renewals & SaaS Gym Onboarding)
 * Verifies HMAC-SHA256 signature and processes payment idempotently.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/cashfree.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

if (file_exists(__DIR__ . '/../../core/account_provisioner.php')) {
    require_once __DIR__ . '/../../core/account_provisioner.php';
}

header('Content-Type: application/json; charset=utf-8');

$rawPayload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_CASHFREE_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? $_SERVER['HTTP_X_CASHFREE_TIMESTAMP'] ?? $_SERVER['HTTP_X_TIMESTAMP'] ?? '';

$payload = json_decode($rawPayload, true) ?? [];
$data = $payload['data'] ?? $payload;
$orderObj = $data['order'] ?? $payload['order'] ?? [];
$paymentObj = $data['payment'] ?? $payload['payment'] ?? [];

$orderId = $orderObj['order_id'] ?? ($payload['orderId'] ?? '');
$paymentStatus = strtoupper($paymentObj['payment_status'] ?? ($orderObj['order_status'] ?? ($payload['type'] ?? '')));
$txRef = $paymentObj['cf_payment_id'] ?? ($paymentObj['referenceId'] ?? $orderId);
$orderAmount = (float)($orderObj['order_amount'] ?? ($payload['orderAmount'] ?? 0));

// Find tenant ID for audit log if possible
$tenantId = 1;
if (!empty($orderId)) {
    $mp = DB::fetchOne("SELECT tenant_id FROM membership_payments WHERE cashfree_order_id = ?", [$orderId]);
    if ($mp && !empty($mp['tenant_id'])) {
        $tenantId = (int)$mp['tenant_id'];
    }
}

// Audit log raw webhook request
DB::insert('audit_logs', [
    'tenant_id' => $tenantId,
    'user_id' => 0,
    'action' => 'WEBHOOK_RECEIVED',
    'description' => 'Cashfree webhook: ' . substr($rawPayload, 0, 1000),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'created_at' => date('Y-m-d H:i:s')
]);

if (empty($orderId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Order ID missing from webhook payload']);
    exit();
}

// Always verify the webhook signature. A bypass is only possible with an explicit opt-in
// (CASHFREE_WEBHOOK_SKIP_VERIFY=1) and is never honoured in production mode.
$skipVerify = ((string)env('CASHFREE_WEBHOOK_SKIP_VERIFY', '') === '1') && CashfreeGateway::getMode() !== 'production';
if (!$skipVerify) {
    if (!CashfreeGateway::verifyWebhookSignature($rawPayload, $signature, $timestamp)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired webhook signature']);
        exit();
    }
} else {
    error_log('Cashfree member webhook: signature verification SKIPPED (CASHFREE_WEBHOOK_SKIP_VERIFY=1)');
}

// Process SUCCESS or PAID transactions
if (in_array($paymentStatus, ['SUCCESS', 'PAID']) || strpos($paymentStatus, 'SUCCESS') !== false) {
    
    // 1. Check if order belongs to Member Subscription Renewal
    $payment = DB::fetchOne("SELECT * FROM membership_payments WHERE cashfree_order_id = ?", [$orderId]);
    if ($payment) {
        // Never credit on the webhook body alone: re-confirm with Cashfree and match the stored amount
        $verify = CashfreeGateway::verifyOrder($orderId);
        if (!$verify['success'] || $verify['status'] !== 'PAID') {
            http_response_code(202);
            echo json_encode(['status' => 'pending', 'type' => 'member_renewal', 'message' => 'Gateway has not confirmed payment yet']);
            exit();
        }
        if (strtoupper($verify['currency'] ?? 'INR') !== 'INR' || abs((float)$verify['order_amount'] - (float)$payment['amount']) > 0.01) {
            error_log("Member webhook amount mismatch for order $orderId: expected {$payment['amount']}, got {$verify['order_amount']} {$verify['currency']}");
            echo json_encode(['status' => 'rejected', 'type' => 'member_renewal', 'message' => 'Amount mismatch — not credited']);
            exit();
        }

        $res = SubscriptionEngine::processSuccessfulPayment(
            $orderId,
            $verify['transaction_id'] ?: $txRef,
            $verify,
            ['verified_amount' => (float)$verify['order_amount']]
        );
        if (!$res['success']) {
            http_response_code(500);
        }
        echo json_encode([
            'status' => $res['success'] ? 'success' : 'error',
            'type' => 'member_renewal',
            'message' => $res['success'] ? 'Member renewal subscription processed idempotently' : ($res['error'] ?? 'Processing failed'),
            'already_processed' => $res['already_processed'] ?? false
        ]);
        exit();
    }

    // 2. Check if order belongs to SaaS Gym Onboarding
    $pending = DB::fetchOne("SELECT * FROM pending_onboardings WHERE order_id = ? OR reg_ref = ?", [$orderId, $orderId]);
    if ($pending && class_exists('AccountProvisioner')) {
        // Re-verified with Cashfree (status, amount, currency) inside provisionVerifiedOrder
        $provisionResult = AccountProvisioner::provisionVerifiedOrder($pending, $orderId);
        if (!$provisionResult['success']) {
            http_response_code(($provisionResult['status'] ?? '') === 'MISMATCH' ? 200 : 500);
        }
        echo json_encode([
            'status' => $provisionResult['success'] ? 'success' : 'error',
            'type' => 'saas_onboarding',
            'message' => $provisionResult['success'] ? 'Gym account provisioned successfully' : ($provisionResult['error'] ?? 'Provisioning failed'),
            'already_provisioned' => $provisionResult['already_provisioned'] ?? false
        ]);
        exit();
    }

    // 3. SaaS subscription renewal by an existing gym
    $saasRenewal = DB::fetchOne("SELECT id FROM saas_payments WHERE transaction_ref = ? AND tenant_id > 0", [$orderId]);
    if ($saasRenewal) {
        $res = SubscriptionEngine::finalizeSaasGatewayOrder($orderId);
        echo json_encode(['status' => $res['success'] ? 'success' : 'error', 'type' => 'saas_renewal', 'message' => $res['error'] ?? 'Subscription renewed']);
        exit();
    }

    // If order was not found in either table but status is PAID
    echo json_encode([
        'status' => 'acknowledged',
        'order_id' => $orderId,
        'message' => 'Payment received but order record not found in database queue'
    ]);
    exit();
}

// Fallback for non-activation statuses (FAILED, CANCELLED, USER_DROPPED).
// Only PENDING rows are touched — a PAID order is never downgraded by a late/duplicate failure event.
if (in_array($paymentStatus, ['FAILED', 'USER_DROPPED', 'CANCELLED', 'EXPIRED'])) {
    SubscriptionEngine::markPaymentNotPaid($orderId, 'FAILED', $payload);
    DB::query("UPDATE pending_onboardings SET status = 'failed' WHERE order_id = ? AND status = 'pending'", [$orderId]);
}

echo json_encode(['status' => 'acknowledged', 'order_id' => $orderId, 'payment_status' => $paymentStatus]);
