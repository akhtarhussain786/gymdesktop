<?php
/**
 * Member Submit Renewal Payment Endpoint
 * Records member's QR payment reference (UTR) as a PENDING renewal for gym staff verification.
 * Nothing is extended here: the gym approves it from Admin → Payments (pending UPI verifications),
 * which runs SubscriptionEngine::processSuccessfulPayment().
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];
$member = $auth['member'];
$memberId = (int)$auth['member_id'];

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$planId = (int)($jsonInput['plan_id'] ?? $_POST['plan_id'] ?? 0);
$planNameInput = trim($jsonInput['plan_name'] ?? $_POST['plan_name'] ?? '');
$months = (int)($jsonInput['months'] ?? $_POST['months'] ?? 1);
$transactionRef = strtoupper(trim($jsonInput['transaction_ref'] ?? $_POST['transaction_ref'] ?? ''));

if (empty($transactionRef)) {
    ApiResponse::error('Transaction Reference / UTR Number is required for verification.', 422);
}
if (!preg_match('/^[A-Z0-9\-]{6,40}$/', $transactionRef)) {
    ApiResponse::error('Please enter a valid UPI Transaction / UTR reference number.', 422);
}

// Expired / suspended gyms must not collect member payments
$gate = SubscriptionEngine::tenantCanCollectPayments($tenant);
if (!$gate['allowed']) {
    ApiResponse::error($gate['reason'], 403);
}

// UPI collection is INR-only
if (!SubscriptionEngine::tenantUsesInr($tenant)) {
    ApiResponse::error('UPI payments are only available for gyms billing in INR.', 422);
}

// Legacy clients send only plan_name: resolve it against this gym's own rate card
if ($planId <= 0 && $planNameInput !== '') {
    $planId = (int)DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND name = ? LIMIT 1", [$tenantId, $planNameInput]);
}
if ($planId <= 0 && !empty($member['services'])) {
    $planId = (int)DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND name = ? LIMIT 1", [$tenantId, $member['services']]);
}

// Price is computed server-side from the gym's rates (client amount is ignored)
$quote = SubscriptionEngine::quoteMemberRenewal($tenantId, $planId, $months);
if (!$quote['success']) {
    ApiResponse::error($quote['error'], 422);
}
$planName = $quote['plan_name'];
$months = $quote['months'];
$amount = $quote['amount'];

// The same UTR cannot be submitted twice for this gym
$dupe = DB::fetchValue(
    "SELECT id FROM membership_payments WHERE tenant_id = ? AND cashfree_payment_id = ? AND payment_status IN ('PENDING','PAID')",
    [$tenantId, $transactionRef]
);
if ($dupe) {
    ApiResponse::error('This transaction reference has already been submitted.', 409);
}

$today = date('Y-m-d');
$orderRef = CashfreeGateway::generateOrderId("UPI_{$tenantId}_{$memberId}");
$notes = "Direct UPI QR Renewal (awaiting gym verification): {$planName} ({$months} Months). UTR: {$transactionRef}";

DB::beginTransaction();
try {
    // Pending payment record — months & server-computed amount stored on the order itself
    $paymentId = DB::insert('membership_payments', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'plan_id' => $quote['plan_id'],
        'plan_months' => $months,
        'cashfree_order_id' => $orderRef,
        'cashfree_payment_id' => $transactionRef,
        'amount' => $amount,
        'currency' => 'INR',
        'payment_method' => 'Direct UPI QR',
        'payment_status' => 'PENDING',
        'gateway_response' => json_encode(['months' => $months, 'discount_percent' => $quote['discount_percent'], 'utr' => $transactionRef, 'source' => 'member_app']),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    if (!$paymentId) {
        throw new RuntimeException('Could not record renewal payment.');
    }

    // Invoice shown to the member as "Pending" until the gym verifies the UTR
    $invoiceId = DB::insert('invoices', [
        'tenant_id' => $tenantId,
        'branch_id' => (int)($member['branch_id'] ?? 1),
        'member_id' => $memberId,
        'payment_id' => $paymentId,
        'invoice_number' => SubscriptionEngine::tempInvoiceNumber(),
        'service_name' => $planName,
        'amount' => $amount,
        'paid_amount' => 0.00,
        'discount' => 0.00,
        'plan_months' => $months,
        'payment_method' => 'Direct UPI QR',
        'payment_date' => $today,
        'status' => 'pending',
        'transaction_ref' => $transactionRef,
        'notes' => $notes,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    if (!$invoiceId) {
        throw new RuntimeException('Could not record renewal invoice.');
    }
    $invoiceNumber = SubscriptionEngine::assignInvoiceNumber($invoiceId, $tenant, $today);

    DB::commit();
} catch (Throwable $e) {
    DB::rollback();
    error_log('submit_renewal_payment error: ' . $e->getMessage());
    ApiResponse::error('Could not submit your renewal payment. Please try again.', 500);
}

ApiResponse::success([
    'invoice_id' => $invoiceId,
    'invoice_number' => $invoiceNumber,
    'plan_name' => $planName,
    'months' => $months,
    'amount_paid' => $amount,
    'amount_payable' => $amount,
    'transaction_ref' => $transactionRef,
    'status' => 'Pending',
    'verification_status' => 'pending_verification',
    'new_expiry_date' => null,
    'receipt_url' => base_url("/api/member/download_receipt_pdf.php?id=" . $invoiceId)
], 'Renewal payment submitted! Your membership will be extended once the gym verifies your payment.');
