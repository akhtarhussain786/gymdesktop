<?php
/**
 * Gym Admin PDF Receipt Download Endpoint
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/pdf_generator.php';
Auth::requireAuth(['gym_admin', 'staff', 'member']);

$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

$invoiceId = (int)($_GET['id'] ?? 0);
$memberId = (int)($_GET['member_id'] ?? 0);

// A member may only ever download their OWN receipts (tenant scoping alone let any member of
// the gym enumerate other members' invoices by id).
$isMember = (($_SESSION['role'] ?? '') === 'member');
if ($isMember) {
    $memberId = (int)$_SESSION['user_id'];
}

$invoice = null;

if ($invoiceId > 0) {
    if ($isMember) {
        $invoice = DB::fetchOne("SELECT * FROM invoices WHERE id = ? AND tenant_id = ? AND member_id = ?", [$invoiceId, $tenantId, $memberId]);
    } else {
        $invoice = DB::fetchOne("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?", [$invoiceId, $tenantId]);
    }
}

if (!$invoice && $memberId > 0) {
    $invoice = DB::fetchOne("SELECT * FROM invoices WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1", [$memberId, $tenantId]);
}

// Downloading a receipt must never create one (that invented 'paid' revenue from members.amount):
// invoices are only written when a payment is actually recorded.
if (!$invoice && $memberId > 0 && !$isMember
    && DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId])) {
    redirect('payment.php', 'info', 'No invoice recorded yet for this member. Record a payment to generate a receipt.');
}

if (!$invoice) {
    redirect($isMember ? base_url('/customer/pages/my-invoices.php') : 'payment.php', 'error', 'Invoice record not found.');
}

$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$invoice['member_id'], $tenantId]);

PDFReceipt::output($invoice, $tenant, $member);
