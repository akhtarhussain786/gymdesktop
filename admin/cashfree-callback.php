<?php
/**
 * Cashfree Callback — Member Payment Verification
 * Cashfree redirects here after payment. Verifies with the gateway and records the payment
 * through SubscriptionEngine (idempotent, queue-aware, amount-checked).
 */
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth(['gym_admin', 'staff']);

$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

$orderId = trim($_GET['order_id'] ?? '');

if (empty($orderId)) {
    redirect('payment.php', 'error', 'Invalid payment callback — no order ID received.');
}

// The order record is the source of truth (session is only a convenience)
$paymentRow = DB::fetchOne("SELECT * FROM membership_payments WHERE cashfree_order_id = ? AND tenant_id = ?", [$orderId, $tenantId]);
if (!$paymentRow) {
    unset($_SESSION['cashfree_pending']);
    redirect('payment.php', 'error', 'Payment order not found for this gym.');
}
$memberId = (int)$paymentRow['member_id'];

// Verify payment with Cashfree API
$result = CashfreeGateway::verifyOrder($orderId);

if ($result['success'] && $result['status'] === 'PAID') {
    $process = SubscriptionEngine::processSuccessfulPayment(
        $orderId,
        $result['transaction_id'] ?? null,
        $result,
        ['verified_amount' => (float)$result['order_amount'], 'tenant_id' => $tenantId]
    );

    unset($_SESSION['cashfree_pending']);

    if (!$process['success']) {
        redirect("user-payment.php?id=$memberId", 'error', 'Payment received at gateway but could not be recorded: ' . ($process['error'] ?? 'Unknown error') . ' (Order ' . $orderId . ')');
    }

    if (empty($process['already_processed'])) {
        $member = DB::fetchOne("SELECT fullname FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        Auth::auditLog('CASHFREE_PAYMENT', "Online payment of {$paymentRow['amount']} received for " . ($member['fullname'] ?? ('#' . $memberId)) . " via Cashfree. Txn: " . ($result['transaction_id'] ?? $orderId));
    }

    $invoiceId = (int)($process['invoice_id'] ?? 0);
    $target = $invoiceId > 0 ? "userpay.php?id=$invoiceId" : "user-payment.php?id=$memberId";
    redirect($target, 'success', 'Payment received successfully via Cashfree! Transaction ID: ' . ($result['transaction_id'] ?? $orderId));
}

// Payment failed, still pending, or gateway unreachable
$status = $result['status'] ?? 'UNKNOWN';
$orderStatus = strtoupper($result['order_status'] ?? '');
$errorMsg = $result['error'] ?? 'Payment was not completed.';

if (in_array($orderStatus, ['EXPIRED', 'TERMINATED', 'CANCELLED'], true)) {
    SubscriptionEngine::markPaymentNotPaid($orderId, 'FAILED');
}

unset($_SESSION['cashfree_pending']);

redirect("user-payment.php?id=$memberId", 'error', "Payment not completed. Status: $status. $errorMsg. Please try again.");
