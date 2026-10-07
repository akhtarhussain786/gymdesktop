<?php
/**
 * Member Generate Renewal Payment QR Code Endpoint
 * Generates dynamic UPI URI and QR Code for the exact selected plan amount directly payable to Gym Owner.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = $auth['tenant_id'];
$member = $auth['member'];

$jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
$planId = (int)($jsonInput['plan_id'] ?? $_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$months = (int)($jsonInput['months'] ?? $_GET['months'] ?? $_POST['months'] ?? 1);
if ($months <= 0) { $months = 1; }

// Expired / suspended gyms must not collect member payments
$gate = SubscriptionEngine::tenantCanCollectPayments($tenant);
if (!$gate['allowed']) {
    ApiResponse::error($gate['reason'], 403);
}

// UPI collection is INR-only
if (!SubscriptionEngine::tenantUsesInr($tenant)) {
    ApiResponse::error('UPI payments are only available for gyms billing in INR.', 422);
}

// Resolve plan from this gym's rate card only (fallback: member's current service by name)
if ($planId <= 0 && !empty($member['services'])) {
    $planId = (int)DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND name = ? LIMIT 1", [$tenantId, $member['services']]);
}
$quote = SubscriptionEngine::quoteMemberRenewal($tenantId, $planId, $months);
if (!$quote['success']) {
    ApiResponse::error($quote['error'], 422);
}

$planName = $quote['plan_name'];
$months = $quote['months'];
$discountPercent = $quote['discount_percent'];
$finalAmount = $quote['amount'];

// Format amount to 2 decimal places
$formattedAmount = number_format($finalAmount, 2, '.', '');

$gymName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Gym Owner';
$upiId = trim($tenant['upi_id'] ?? '');
$memberName = !empty($member['fullname']) ? $member['fullname'] : 'Member';

// Clean values for UPI URI
$cleanPayeeName = urlencode(substr($gymName, 0, 50));
$cleanNote = urlencode("Renewal-{$planName}-{$months}mo-{$memberName}");
$cleanUpiId = $upiId;

// Standard compliant UPI URI string
$upiUri = !empty($cleanUpiId) ? "upi://pay?pa={$cleanUpiId}&pn={$cleanPayeeName}&am={$formattedAmount}&cu=INR&tn={$cleanNote}" : '';

// Generate high resolution QR Code image URL via QRServer
$encodedUpiUri = urlencode($upiUri);
$qrCodeUrl = !empty($upiUri) ? "https://api.qrserver.com/v1/create-qr-code/?size=350x350&data={$encodedUpiUri}&margin=10" : null;

// Check if tenant uploaded a custom static QR image
$customQrUrl = !empty($tenant['upi_qr']) ? base_url("/uploads/qr/" . $tenant['upi_qr']) : null;

$response = [
    'plan_name' => $planName,
    'plan_id' => $quote['plan_id'],
    'months' => $months,
    'discount_percent' => $discountPercent,
    'payable_amount' => $finalAmount,
    'currency' => $tenant['currency'] ?: '₹',
    'payee_info' => [
        'gym_name' => $gymName,
        'upi_id' => $cleanUpiId,
        'owner_name' => $tenant['owner_name'] ?: $gymName
    ],
    'upi_url' => $upiUri,
    'qr_code_url' => $qrCodeUrl,
    'custom_qr_url' => $customQrUrl,
    'instructions' => [
        'Step 1: Open Google Pay, PhonePe, Paytm, BHIM, or any UPI app on your phone.',
        'Step 2: Scan the QR code displayed above or click "Pay via UPI App".',
        'Step 3: Confirm payment of ' . ($tenant['currency'] ?: '₹') . $formattedAmount . ' to ' . $gymName . ' (' . $cleanUpiId . ').',
        'Step 4: After payment, enter your Transaction UTR / Ref Number below to log renewal.'
    ]
];

ApiResponse::success($response, 'Renewal QR payment generated successfully.');
