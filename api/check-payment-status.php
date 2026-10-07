<?php
/**
 * Backend Payment Status Verification API
 * Checks real payment status from Cashfree and triggers idempotent gym activation.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/account_provisioner.php';

$orderId = $_GET['order_id'] ?? '';
$regRef  = $_GET['reg_ref'] ?? '';

if (empty($orderId) && empty($regRef)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Missing order reference.']);
    exit();
}

// 1. Fetch pending onboarding / payment records
$pending = null;
if (!empty($regRef)) {
    $pending = DB::fetchOne("SELECT p.*, sp.name as plan_name FROM pending_onboardings p JOIN subscription_plans sp ON p.plan_id = sp.id WHERE p.reg_ref = ?", [$regRef]);
}
if (!$pending && !empty($orderId)) {
    $pending = DB::fetchOne("SELECT p.*, sp.name as plan_name FROM pending_onboardings p JOIN subscription_plans sp ON p.plan_id = sp.id WHERE p.order_id = ?", [$orderId]);
}

if (!$pending) {
    // Check if already in saas_payments
    $payment = DB::fetchOne("SELECT p.*, t.gym_name, t.email, sp.name as plan_name 
                             FROM saas_payments p 
                             JOIN tenants t ON p.tenant_id = t.id 
                             JOIN subscription_plans sp ON p.plan_id = sp.id 
                             WHERE p.transaction_ref = ? OR p.reg_ref = ?", [$orderId, $regRef]);
    if ($payment && $payment['status'] === 'approved') {
        echo json_encode([
            'status' => 'SUCCESS',
            'order_id' => $payment['transaction_ref'],
            'gym_name' => $payment['gym_name'],
            'plan_name' => $payment['plan_name'],
            'masked_email' => AccountProvisioner::maskEmail($payment['email'])
        ]);
        exit();
    }

    echo json_encode(['status' => 'NOT_FOUND', 'message' => 'Registration order not found.']);
    exit();
}

$effectiveOrderId = $pending['order_id'] ?: $orderId;

// 2. Check if already activated in DB
$existingApproved = DB::fetchOne("SELECT p.*, t.gym_name, t.email FROM saas_payments p JOIN tenants t ON p.tenant_id = t.id WHERE (p.transaction_ref = ? OR p.reg_ref = ?) AND p.status = 'approved'", [$effectiveOrderId, $pending['reg_ref']]);
if ($existingApproved) {
    echo json_encode([
        'status' => 'SUCCESS',
        'order_id' => $effectiveOrderId,
        'gym_name' => $existingApproved['gym_name'],
        'plan_name' => $pending['plan_name'],
        'masked_email' => AccountProvisioner::maskEmail($existingApproved['email'])
    ]);
    exit();
}

// 3. Query Cashfree API directly for definitive status, verify amount/currency, then provision idempotently
$provision = AccountProvisioner::provisionVerifiedOrder($pending, $effectiveOrderId);

if ($provision['success']) {
    echo json_encode([
        'status' => 'SUCCESS',
        'order_id' => $effectiveOrderId,
        'gym_name' => $pending['gym_name'],
        'plan_name' => $pending['plan_name'],
        'masked_email' => AccountProvisioner::maskEmail($pending['email'])
    ]);
    exit();
}

// Paid at gateway but we could not activate (e.g. amount mismatch / DB error) — surface for support
if (($provision['status'] ?? '') === 'MISMATCH' || (!isset($provision['status']) && !empty($provision['error']))) {
    echo json_encode([
        'status' => 'FAILED',
        'reason' => $provision['error'] ?? 'Payment could not be activated. Please contact support.'
    ]);
    exit();
}

// An ACTIVE order can still be paid (a failed attempt does not close it)
if (($provision['order_status'] ?? '') === 'ACTIVE' || ($provision['status'] ?? '') === 'ACTIVE') {
    echo json_encode([
        'status' => 'PENDING',
        'message' => 'Payment awaiting completion at gateway.'
    ]);
    exit();
}

echo json_encode([
    'status' => 'FAILED',
    'reason' => $provision['error'] ?: 'Payment could not be completed.'
]);
