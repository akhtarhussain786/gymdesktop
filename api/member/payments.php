<?php
/**
 * Member Payments & Invoices Endpoint
 * Computes official dues from server records and returns invoice history.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

$invoices = DB::fetchAll(
    "SELECT id, invoice_number, service_name, total_amount as amount, paid_amount, discount, plan_months, payment_method, payment_date, status, transaction_ref, notes 
     FROM invoices 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY payment_date DESC, id DESC",
    [$tenantId, $memberId]
);

$totalPaid = 0;
$totalDue = 0;

$formattedInvoices = array_map(function($inv) use (&$totalPaid, &$totalDue, $tenant) {
    $amount = (float)$inv['amount'];
    $paid = (float)$inv['paid_amount'];
    $discount = (float)$inv['discount'];
    $pending = max(0, $amount - $paid - $discount);
    $statusNormalized = ucfirst(strtolower($inv['status']));
    
    $statusLower = strtolower($inv['status']);
    // Cancelled/rejected invoices are neither paid nor due; 'pending' = member-submitted UTR awaiting gym verification
    if ($statusLower !== 'cancelled') {
        $totalPaid += $paid;
    }
    if (in_array($statusLower, ['unpaid', 'partial'])) {
        $totalDue += $pending;
    }

    return [
        'id' => (int)$inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'service_name' => $inv['service_name'],
        'plan_months' => (int)$inv['plan_months'],
        'total_amount' => $amount,
        'paid_amount' => $paid,
        'discount' => $discount,
        'pending_amount' => $pending,
        'payment_method' => $inv['payment_method'],
        'payment_date' => $inv['payment_date'],
        'status' => $statusNormalized,
        'awaiting_verification' => ($statusLower === 'pending'),
        'transaction_ref' => $inv['transaction_ref'] ?? '',
        'notes' => $inv['notes'] ?? '',
        'receipt_url' => base_url("/api/member/download_receipt_pdf.php?id=" . $inv['id'])
    ];
}, $invoices);

$response = [
    'summary' => [
        'total_paid' => $totalPaid,
        'total_due' => $totalDue,
        'currency' => $tenant['currency'] ?: '₹',
        'has_pending_dues' => ($totalDue > 0),
        'membership_fee' => (float)$member['amount']
    ],
    'invoices' => $formattedInvoices,
    'payment_instructions' => [
        'mode' => 'Gym Reception / Online',
        'contact' => $tenant['phone'] ?: $tenant['email'],
        'notice' => 'For invoice receipt copies or payment questions, please visit the gym billing desk.'
    ]
];

ApiResponse::success($response, 'Payments and invoice history retrieved.');
