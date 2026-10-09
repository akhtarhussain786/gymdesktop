<?php
/**
 * Gym Admin Member Registration Onboarding Data API
 * Returns complete verified data for generating official Member Registration PDFs & Onboarding Dockets.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

$memberId = (int)($_GET['member_id'] ?? $_GET['id'] ?? 0);
if ($memberId <= 0) {
    ApiResponse::error('Member ID is required', 400);
}

// 1. Fetch Member
$member = DB::fetchOne(
    "SELECT m.*, u.email as user_email, u.status as account_status 
     FROM members m 
     LEFT JOIN users u ON (m.user_id = u.id AND u.tenant_id = m.tenant_id)
     WHERE m.user_id = ? AND m.tenant_id = ?",
    [$memberId, $tenantId]
);

if (!$member) {
    ApiResponse::notFound('Member not found in this gym.');
}

// 2. Fetch Latest Registration / Joining Invoice
$invoice = DB::fetchOne(
    "SELECT * FROM invoices 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY id ASC LIMIT 1",
    [$tenantId, $memberId]
);

// Fallback to latest invoice if no initial invoice
if (!$invoice) {
    $invoice = DB::fetchOne(
        "SELECT * FROM invoices 
         WHERE tenant_id = ? AND member_id = ? 
         ORDER BY id DESC LIMIT 1",
        [$tenantId, $memberId]
    );
}

// 3. Compute Dates
$dor = $member['dor'] ?: date('Y-m-d');
$planMonths = max(1, (int)($member['plan'] ?? $invoice['plan_months'] ?? 1));
$computedExpiry = date('Y-m-d', strtotime(($member['paid_date'] ?: $dor) . " +$planMonths months"));

// Gym Logo
$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . basename($tenant['logo']))) {
        $logoUrl = base_url('/uploads/logos/' . basename($tenant['logo']));
    }
}

// Member Avatar
$avatarUrl = api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null);

$totalFee = (float)($invoice['amount'] ?? $member['amount'] ?? 0.0);
$paidFee = (float)($invoice['paid_amount'] ?? ($totalFee - (float)($member['due_amount'] ?? 0.0)));
$discount = (float)($invoice['discount'] ?? 0.0);
$dueBalance = max(0.0, (float)($member['due_amount'] ?? ($totalFee - $paidFee - $discount)));

$currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';

$invNumber = $invoice['invoice_number'] ?? ('REG-' . strtoupper(substr($tenant['gym_name'] ?? 'GYM', 0, 3)) . '-' . str_pad($memberId, 4, '0', STR_PAD_LEFT));
$paymentMethod = $invoice['payment_method'] ?? 'Cash';
$paymentStatus = ($dueBalance <= 0) ? 'Paid' : ($paidFee > 0 ? 'Partial' : 'Unpaid');
$paymentRef = $invoice['transaction_ref'] ?? ('REF-' . strtoupper(substr(md5($memberId . $dor), 0, 8)));

$data = [
    'gym' => [
        'id' => $tenantId,
        'name' => $tenant['gym_name'] ?? 'FITISIFY FITNESS CLUB',
        'phone' => $tenant['phone'] ?? '',
        'email' => $tenant['email'] ?? '',
        'address' => $tenant['address'] ?? 'Gym Headquarters',
        'logo_url' => $logoUrl,
        'currency' => $currency,
        'terms' => $tenant['terms_conditions'] ?? '1. Membership fee is non-refundable.\n2. Members must follow gym decorum and re-rack all weights.\n3. Gym management is not liable for personal lost belongings.\n4. Members must present their digital QR pass at turnstile entry.'
    ],
    'member' => [
        'member_id' => $memberId,
        'formatted_member_id' => 'MEM-' . str_pad($memberId, 4, '0', STR_PAD_LEFT),
        'fullname' => $member['fullname'] ?? '',
        'phone' => $member['contact'] ?? '',
        'email' => $member['user_email'] ?? ($member['email'] ?? ''),
        'gender' => $member['gender'] ?? 'Not Specified',
        'dob' => $member['dob'] ?? null,
        'address' => $member['address'] ?? 'On File',
        'avatar_url' => $avatarUrl,
        'emergency_contact' => $member['emergency_contact'] ?? '',
        'blood_group' => $member['blood_group'] ?? '',
        'joining_date' => $dor
    ],
    'membership' => [
        'plan_name' => $member['services'] ?? 'General Fitness & Gym',
        'plan_months' => $planMonths,
        'duration_text' => $planMonths . ' ' . ($planMonths > 1 ? 'Months' : 'Month'),
        'start_date' => $dor,
        'expiry_date' => $computedExpiry,
        'status' => ucfirst(strtolower($member['status'] ?? 'Active')),
        'trainer_name' => $member['trainer_name'] ?? 'General Floor Trainer'
    ],
    'payment' => [
        'invoice_id' => (int)($invoice['id'] ?? 0),
        'invoice_number' => $invNumber,
        'total_amount' => $totalFee,
        'discount' => $discount,
        'final_payable' => max(0.0, $totalFee - $discount),
        'paid_amount' => $paidFee,
        'due_amount' => $dueBalance,
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'payment_date' => $invoice['payment_date'] ?? $dor,
        'transaction_ref' => $paymentRef
    ],
    'generated_at' => date('d M Y, h:i A')
];

ApiResponse::success($data);
