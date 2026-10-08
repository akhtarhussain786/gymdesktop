<?php
/**
 * Gym Admin Single Member Detail API
 * Returns full profile, financial invoices history, dues breakdown, and attendance logs
 */

require_once __DIR__ . '/middleware.php';

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

// Plan End & Expiry Calculation
$planEndSql = "DATE_ADD(m.paid_date, INTERVAL GREATEST(1, CAST(m.plan AS UNSIGNED)) MONTH)";
$effStatus = (empty($member['paid_date']) || strtotime($member['paid_date'] . ' +' . max(1, (int)$member['plan']) . ' months') < time()) && $member['status'] === 'Active'
    ? 'Expired' 
    : $member['status'];

$computedExpiry = date('Y-m-d', strtotime(($member['paid_date'] ?: $member['dor'] ?: date('Y-m-d')) . ' +' . max(1, (int)$member['plan']) . ' months'));
$daysRemaining = (int)ceil((strtotime($computedExpiry) - strtotime(date('Y-m-d'))) / 86400);

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

    $totalInvoiced += $invAmount;
    $totalPaid += $invPaid;
    $totalDue += $invDue;

    if ($invDue > 0 && !empty($inv['due_date'])) {
        if ($latestDueDate === null || $inv['due_date'] > $latestDueDate) {
            $latestDueDate = $inv['due_date'];
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
        'receipt_url' => base_url('/api/member/receipt_html.php?id=' . $inv['id'])
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
        'whatsapp_reminder' => $waText
    ],
    'invoices' => $formattedInvoices,
    'attendance' => $attendance,
    'gym' => [
        'currency' => $currency,
        'upi_id' => $tenant['upi_id'] ?? '',
        'name' => $gymName
    ]
], 'Member details retrieved successfully');
