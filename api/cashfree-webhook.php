<?php
/**
 * Cashfree Webhook Receiver
 * Secure, verified, and idempotent webhook endpoint for Cashfree payment notifications.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/account_provisioner.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/subscription_engine.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';

// 1. Signature Verification
if (!CashfreeGateway::verifyWebhookSignature($rawBody, $signature, $timestamp)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired webhook signature']);
    exit();
}

$payload = json_decode($rawBody, true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit();
}

$eventType = $payload['type'] ?? ($payload['event'] ?? 'UNKNOWN');
$data = $payload['data'] ?? $payload;
$orderObj = $data['order'] ?? $data;
$paymentObj = $data['payment'] ?? [];

$orderId = $orderObj['order_id'] ?? ($payload['orderId'] ?? '');
$orderAmount = (float)($orderObj['order_amount'] ?? ($payload['orderAmount'] ?? 0));
$orderCurrency = $orderObj['order_currency'] ?? 'INR';
$paymentStatus = strtoupper($paymentObj['payment_status'] ?? ($orderObj['order_status'] ?? ''));
$cfPaymentId = $paymentObj['cf_payment_id'] ?? null;
$paymentMethod = $paymentObj['payment_group'] ?? 'cashfree_webhook';

// 2. Audit Log Webhook Event
DB::insert('webhook_logs', [
    'event_type' => $eventType,
    'order_id' => $orderId,
    'signature' => $signature,
    'payload' => $rawBody,
    'status' => 'received'
]);

// 3. Process Successful Payment Event
if (in_array($paymentStatus, ['SUCCESS', 'PAID']) || strpos($eventType, 'SUCCESS') !== false || strpos($eventType, 'PAID') !== false) {
    
    // Find pending onboarding record
    $pending = DB::fetchOne("SELECT * FROM pending_onboardings WHERE order_id = ? OR reg_ref = ?", [$orderId, $orderId]);
    
    if ($pending) {
        // Validate amount match against the stored order — a mismatch must NOT provision
        if (abs((float)$pending['amount'] - $orderAmount) > 0.01 || strtoupper($orderCurrency) !== 'INR') {
            error_log("Webhook amount mismatch for order $orderId: expected {$pending['amount']} INR, got $orderAmount $orderCurrency");
            DB::query("UPDATE webhook_logs SET status = 'amount_mismatch' WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$orderId]);
            http_response_code(200);
            echo json_encode(['status' => 'rejected', 'message' => 'Amount mismatch — not provisioned']);
            exit();
        }

        // Idempotent Account Provisioning — re-confirmed with Cashfree's API before crediting
        $provisionResult = AccountProvisioner::provisionVerifiedOrder($pending, $orderId);

        // Update Webhook Log
        DB::query("UPDATE webhook_logs SET status = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$provisionResult['success'] ? 'processed' : 'failed', $orderId]);

        if (!$provisionResult['success']) {
            // Non-2xx so Cashfree retries delivery later
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $provisionResult['error'] ?? 'Provisioning failed']);
            exit();
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Gym account provisioned successfully',
            'already_provisioned' => $provisionResult['already_provisioned'] ?? false
        ]);
        exit();
    }

    // SaaS subscription renewal paid online by an existing gym
    $saasRenewal = DB::fetchOne("SELECT id FROM saas_payments WHERE transaction_ref = ? AND tenant_id > 0", [$orderId]);
    if ($saasRenewal) {
        $res = SubscriptionEngine::finalizeSaasGatewayOrder($orderId);
        DB::query("UPDATE webhook_logs SET status = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$res['success'] ? 'processed' : 'failed', $orderId]);
        echo json_encode(['status' => $res['success'] ? 'success' : 'error', 'type' => 'saas_renewal', 'message' => $res['error'] ?? 'Subscription renewed']);
        exit();
    }
}

// Fallback for non-activation webhooks (e.g. FAILED, USER_DROPPED)
// A failed attempt does not end a Cashfree order (the customer may retry on the same order),
// so only record the failure on rows that are still pending — never downgrade an approved payment.
if (in_array($paymentStatus, ['FAILED', 'USER_DROPPED', 'CANCELLED'])) {
    DB::query("UPDATE pending_onboardings SET status = 'failed' WHERE order_id = ? AND status = 'pending'", [$orderId]);
    DB::query("UPDATE saas_payments SET status = 'failed', failure_reason = ? WHERE transaction_ref = ? AND status = 'pending'", [$paymentStatus, $orderId]);
}

echo json_encode(['status' => 'received', 'event' => $eventType]);
