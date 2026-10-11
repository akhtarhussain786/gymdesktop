<?php
/**
 * Gym Admin Single Member Detail API
 * Returns full profile, financial invoices history, dues breakdown, and attendance logs
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$memberId = (int)($_GET['id'] ?? $_GET['member_id'] ?? 0);
if ($memberId <= 0) {
    ApiResponse::error('Member ID is required', 400);
}

// 1. Fetch Member
$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    ApiResponse::notFound('Member not found in this gym.');
}

// Plan End & Expiry Calculation (same rule as members.php: the expiry day itself is still active)
$today = date('Y-m-d');
$planMonths = max(1, (int)$member['plan']);
$hasPaidDate = !empty($member['paid_date']) && strpos((string)$member['paid_date'], '0000-00-00') !== 0;
$planEnd = $hasPaidDate ? SubscriptionEngine::addMonths($member['paid_date'], $planMonths) : null;
$effStatus = ($member['status'] === 'Active' && ($planEnd === null || $planEnd < $today))
    ? 'Expired' 
    : $member['status'];

$hasDor = !empty($member['dor']) && strpos((string)$member['dor'], '0000-00-00') !== 0;
$computedExpiry = SubscriptionEngine::addMonths($hasPaidDate ? $member['paid_date'] : ($hasDor ? $member['dor'] : $today), $planMonths);
$daysRemaining = (int)(new DateTime($today))->diff(new DateTime($computedExpiry))->format('%r%a');

// Avatar
$avatarUrl = api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null);

// 2. Invoices & Payments History
$invoices = DB::fetchAll(
    "SELECT id, invoice_number, service_name, plan_months, amount, paid_amount, 
            (amount - paid_amount) as due_amount, discount, payment_method, 
            payment_date, due_date, status, transaction_ref, notes, created_at
     FROM invoices 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY payment_date DESC, id DESC",
    [$tenantId, $memberId]
);

$totalInvoiced = 0.0;
$totalPaid = 0.0;
$totalDue = 0.0;
$latestDueDate = null;

$formattedInvoices = [];
foreach ($invoices as $inv) {
    $invAmount = (float)$inv['amount'];
    $invPaid = (float)$inv['paid_amount'];
    $invDue = max(0.0, $invAmount - $invPaid);
    $invStatus = strtolower((string)$inv['status']);

    // Pending (awaiting verification) and cancelled invoices are not charges; only partial/unpaid ones are dues.
    // Legacy 'paid' rows that never stored paid_amount count as fully paid (same rule as revenue reports).
    if (!in_array($invStatus, ['pending', 'cancelled'], true)) {
        $totalInvoiced += $invAmount;
        $totalPaid += ($invStatus === 'paid' && $invPaid <= 0) ? $invAmount : $invPaid;
    }
    if (in_array($invStatus, ['partial', 'unpaid'], true)) {
        $totalDue += $invDue;

        if ($invDue > 0 && !empty($inv['due_date'])) {
            if ($latestDueDate === null || $inv['due_date'] > $latestDueDate) {
                $latestDueDate = $inv['due_date'];
            }
        }
    }

    $formattedInvoices[] = [
        'id' => (int)$inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'service_name' => $inv['service_name'] ?: 'Membership',
        'plan_months' => (int)$inv['plan_months'],
        'amount' => $invAmount,
        'paid_amount' => $invPaid,
        'due_amount' => $invDue,
        'payment_method' => $inv['payment_method'] ?: 'Cash',
        'payment_date' => $inv['payment_date'],
        'due_date' => $inv['due_date'],
        'status' => $inv['status'],
        'notes' => $inv['notes'] ?? '',
        // Signed link: the receipt opens in a browser, which cannot send the app's login token
        'receipt_url' => api_admin_receipt_url($tenantId, (int)$inv['id'])
    ];
}

// Fallback to member record if no invoices recorded yet
if (empty($invoices)) {
    $totalInvoiced = (float)($member['amount'] ?? 0.00);
    $totalDue = (float)($member['due_amount'] ?? 0.00);
    $totalPaid = max(0.0, $totalInvoiced - $totalDue);
    $latestDueDate = $member['due_date'] ?? null;
}

// 3. Recent Attendance History (last 15 records)
$attendance = DB::fetchAll(
    "SELECT curr_date, curr_time, present 
     FROM attendance 
     WHERE tenant_id = ? AND user_id = ? 
     ORDER BY curr_date DESC, id DESC 
     LIMIT 15",
    [$tenantId, $memberId]
);

$currency = $tenant['currency'] ?: '₹';
$gymName = $tenant['gym_name'] ?: 'Our Gym';

$waText = "";
if ($totalDue > 0) {
    $waText = "Hello " . $member['fullname'] . ", this is a gentle reminder from *" . $gymName . "*. You have a pending fee of *" . $currency . number_format($totalDue, 2) . "*";
    if ($latestDueDate) {
        $waText .= " due by *" . date('d M Y', strtotime($latestDueDate)) . "*";
    }
    $waText .= ". Please clear your dues at the gym counter or via UPI. Thank you!";
}

ApiResponse::success([
    'member' => [
        'member_id' => (int)$member['user_id'],
        'fullname' => $member['fullname'],
        'username' => $member['username'],
        'phone' => $member['contact'] ?? '',
        'email' => $member['email'] ?? '',
        'gender' => $member['gender'] ?? 'Not Specified',
        'address' => $member['address'] ?? '',
        'avatar' => $avatarUrl,
        'services' => $member['services'] ?? 'General Fitness',
        'plan_months' => (int)($member['plan'] ?? 1),
        'membership_status' => $effStatus,
        'start_date' => $member['dor'] ?? $member['paid_date'] ?? date('Y-m-d'),
        'expiry_date' => $computedExpiry,
        'days_remaining' => $daysRemaining,
        'attendance_count' => (int)($member['attendance_count'] ?? 0),
        'total_fee' => $totalInvoiced,
        'paid_amount' => $totalPaid,
        'due_amount' => $totalDue,
        'due_date' => $latestDueDate,
        'whatsapp_reminder' => $waText,
        // Used by the app's edit screen so an edit keeps the current trainer / weight
        'trainer_id' => !empty($member['trainer_id']) ? (int)$member['trainer_id'] : null,
        'curr_weight' => isset($member['curr_weight']) && $member['curr_weight'] !== '' ? (float)$member['curr_weight'] : (isset($member['current_weight']) && $member['current_weight'] !== '' ? (float)$member['current_weight'] : null),
        'ini_weight' => isset($member['ini_weight']) && $member['ini_weight'] !== '' ? (float)$member['ini_weight'] : null
    ],
    'invoices' => $formattedInvoices,
    'attendance' => $attendance,
    'gym' => [
        'currency' => $currency,
        'upi_id' => $tenant['upi_id'] ?? '',
        'name' => $gymName
    ]
], 'Member details retrieved successfully');
