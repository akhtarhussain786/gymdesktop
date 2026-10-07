<?php
/**
 * Member PDF Receipt Download Endpoint
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/pdf_generator.php';

// GET document opened in a browser/webview: ?token= is accepted for this endpoint only
$auth = MemberAuthMiddleware::authenticate(null, true);
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

PDFReceipt::output($invoice, $tenant, $member);
