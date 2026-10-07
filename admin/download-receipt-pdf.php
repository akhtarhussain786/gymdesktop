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

// Back-filling a missing invoice writes a 'paid' financial record, so only gym staff may trigger it
if (!$invoice && $memberId > 0 && !$isMember) {
    // Check if member exists and create missing invoice safely
    $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
    if ($member) {
        $realMemId = (int)$member['user_id'];
        $amount = (float)($member['amount'] ?? 0);
        $planMonths = max(1, (int)($member['plan'] ?? 1));
        $services = !empty($member['services']) ? $member['services'] : 'Fitness Membership';
        $paidDate = !empty($member['paid_date']) ? $member['paid_date'] : date('Y-m-d');

        $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
        $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
        $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym', strtotime($paidDate)) . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

        $newInvId = DB::insert('invoices', [
            'tenant_id' => $tenantId,
            'branch_id' => (int)($member['branch_id'] ?? 1),
            'member_id' => $realMemId,
            'invoice_number' => $invoiceNumber,
            'service_name' => $services,
            'plan_months' => $planMonths,
            'amount' => $amount,
            'paid_amount' => $amount,
            'discount' => 0.00,
            'payment_method' => 'Cash',
            'payment_date' => $paidDate,
            'status' => 'Paid',
            'transaction_ref' => 'AUTO-REC-' . str_pad($realMemId, 4, '0', STR_PAD_LEFT),
            'notes' => 'Official membership invoice',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if ($newInvId) {
            $invoice = DB::fetchOne("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?", [$newInvId, $tenantId]);
        }
    }
}

if (!$invoice) {
    redirect($isMember ? base_url('/customer/pages/my-invoices.php') : 'payment.php', 'error', 'Invoice record not found.');
}

$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$invoice['member_id'], $tenantId]);

PDFReceipt::output($invoice, $tenant, $member);
