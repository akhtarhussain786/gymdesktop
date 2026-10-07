<?php
/**
 * Gym Admin UPI QR Code API
 * Returns tenant's UPI ID, merchant name, and instant dynamic QR payload for on-the-spot checkout.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$upiId = trim($tenant['upi_id'] ?? '');
$gymName = trim($tenant['gym_name'] ?? 'Gym Owner');

$amount = (float)($_GET['amount'] ?? 0);
$note = trim($_GET['note'] ?? 'Gym Membership Payment');

// Generate standard UPI Intent URI
$upiPayload = "";
if (!empty($upiId)) {
    $upiPayload = "upi://pay?pa=" . urlencode($upiId) . "&pn=" . urlencode($gymName) . "&cu=INR";
    if ($amount > 0) {
        $upiPayload .= "&am=" . number_format($amount, 2, '.', '');
    }
    if (!empty($note)) {
        $upiPayload .= "&tn=" . urlencode($note);
    }
}

ApiResponse::success([
    'gym_name' => $gymName,
    'upi_id' => $upiId,
    'has_upi' => !empty($upiId),
    'upi_payload' => $upiPayload,
    'currency' => $tenant['currency'] ?: '₹'
], 'Gym UPI details retrieved successfully');
