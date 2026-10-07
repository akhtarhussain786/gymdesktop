<?php
/**
 * Member Receipt Endpoint
 * Returns printable and structured receipt details for a specific invoice.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

$invoiceId = (int)($_GET['id'] ?? 0);
$invoice = null;

// Always scope to the authenticated member: an id belonging to another member (even in the same gym) is never served.
if ($invoiceId > 0) {
    $invoice = DB::fetchOne(
        "SELECT * FROM invoices WHERE id = ? AND tenant_id = ? AND member_id = ?",
        [$invoiceId, $tenantId, $memberId]
    );
} else {
    $invoice = DB::fetchOne(
        "SELECT * FROM invoices WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 1",
        [$tenantId, $memberId]
    );
}

// No auto-generated fallback: fabricating a 'Paid' invoice from member data polluted the gym's ledger.
if (!$invoice) {
    ApiResponse::notFound('Receipt not found.');
}

$branch = DB::fetchOne("SELECT branch_name, address, phone FROM branches WHERE id = ? AND tenant_id = ?", [$invoice['branch_id'] ?? 1, $tenantId]);

// Format Logo URL
$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . $tenant['logo'])) {
        $logoUrl = base_url('/uploads/logos/' . $tenant['logo']);
    } else {
        $logoUrl = base_url('/img/' . $tenant['logo']);
    }
}

$receipt = [
    'gym' => [
        'name' => $tenant['gym_name'],
        'header' => $tenant['invoice_header'] ?: $tenant['gym_name'],
        'address' => $branch['address'] ?? $tenant['address'],
        'phone' => $branch['phone'] ?? $tenant['phone'],
        'email' => $tenant['email'],
        'logo' => $logoUrl,
        'footer' => $tenant['invoice_footer'] ?: 'Thank you for your membership!'
    ],
    'invoice' => [
        'id' => (int)$invoice['id'],
        'number' => $invoice['invoice_number'],
        'date' => $invoice['payment_date'],
        'service' => $invoice['service_name'],
        'duration_months' => (int)$invoice['plan_months'],
        'amount' => (float)($invoice['amount'] ?? $invoice['paid_amount'] ?? 0),
        'discount' => (float)($invoice['discount'] ?? 0),
        'paid_amount' => (float)($invoice['paid_amount'] ?? $invoice['amount'] ?? 0),
        'currency' => $tenant['currency'] ?: '₹',
        'payment_method' => $invoice['payment_method'],
        'transaction_ref' => $invoice['transaction_ref'] ?: 'N/A',
        'status' => ucfirst(strtolower($invoice['status'])),
        'notes' => $invoice['notes'] ?? ''
    ],
    'member' => [
        'name' => $member['fullname'],
        'member_id' => $member['user_id'] ?? $member['id'] ?? $memberId,
        'phone' => $member['contact'],
        'email' => $member['email']
    ]
];

ApiResponse::success($receipt, 'Receipt retrieved.');
