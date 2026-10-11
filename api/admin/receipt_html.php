<?php
/**
 * Gym Admin Printable Receipt HTML Endpoint
 * Opened from the admin app in a browser, which cannot send the app's login token: access is granted by a
 * signed, time-limited link (api_admin_receipt_url) bound to one gym and one invoice. No admin login here.
 */

require_once __DIR__ . '/../member/common.php';

// common.php defaults every response to JSON; this endpoint renders an HTML page
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$sigTenantId = (int)($_GET['t'] ?? 0);
$invoiceId = (int)($_GET['id'] ?? 0);
$exp = (int)($_GET['exp'] ?? 0);
$sig = isset($_GET['sig']) && is_string($_GET['sig']) ? $_GET['sig'] : '';

if (!api_verify_admin_receipt_signature($sigTenantId, $invoiceId, $exp, $sig)) {
    admin_receipt_error_page(403, 'Link expired or invalid', 'This receipt link has expired or is not valid. Please open the receipt again from the app.');
}

$tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$sigTenantId]);
$invoice = $tenant ? DB::fetchOne("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?", [$invoiceId, $sigTenantId]) : null;
if (!$tenant || !$invoice) {
    admin_receipt_error_page(404, 'Receipt not found', 'This receipt could not be found.');
}

// The member may have been deleted since (invoices are kept for history)
$member = DB::fetchOne("SELECT user_id, fullname, contact FROM members WHERE user_id = ? AND tenant_id = ?", [(int)$invoice['member_id'], $sigTenantId]) ?: [];

api_apply_tenant_timezone($tenant);

$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . basename($tenant['logo']))) {
        $logoUrl = base_url('/uploads/logos/' . basename($tenant['logo']));
    } else {
        $logoUrl = base_url('/img/' . basename($tenant['logo']));
    }
}

$gymName = (string)($tenant['gym_name'] ?? $tenant['name'] ?? '');
$currencySymbol = !empty($tenant['currency']) ? (string)$tenant['currency'] : '₹';
$money = function ($amount) use ($currencySymbol) {
    return e($currencySymbol) . number_format((float)$amount, 2);
};

$totalAmt = (float)($invoice['amount'] ?? $invoice['paid_amount'] ?? 0);
$discount = (float)($invoice['discount'] ?? 0);
$paidAmt = (float)($invoice['paid_amount'] ?? $invoice['amount'] ?? $totalAmt);
$statusRaw = strtolower((string)($invoice['status'] ?? ''));
// Legacy 'paid' rows that never stored paid_amount were fully paid
if ($statusRaw === 'paid' && $paidAmt <= 0) {
    $paidAmt = $totalAmt;
}
$statusLabel = ucfirst($statusRaw !== '' ? $statusRaw : 'paid');
$memberUserId = (int)($member['user_id'] ?? $invoice['member_id'] ?? 0);

function admin_receipt_error_page($code, $title, $message) {
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . e($title) . '</title>'
        . '<style>body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;background:#0f172a;color:#f8fafc;padding:20px;display:flex;justify-content:center;}'
        . '.card{width:100%;max-width:480px;background:#1e293b;border-radius:16px;border:1px solid #334155;padding:24px;text-align:center;}'
        . 'h1{font-size:1.2rem;color:#f87171;margin:0 0 10px;}p{color:#94a3b8;font-size:0.9rem;margin:0;}</style></head>'
        . '<body><div class="card"><h1>' . e($title) . '</h1><p>' . e($message) . '</p></div></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt #<?php echo e($invoice['invoice_number']); ?> - <?php echo e($gymName); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; display: flex; justify-content: center; }
        .receipt-card { width: 100%; max-width: 480px; background: #1e293b; border-radius: 16px; border: 1px solid #334155; padding: 24px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); }
        .header { display: flex; align-items: center; gap: 14px; border-bottom: 1px solid #334155; padding-bottom: 18px; margin-bottom: 18px; }
        .logo { width: 52px; height: 52px; border-radius: 12px; object-fit: contain; background: #0f172a; border: 1px solid #334155; }
        .gym-info h1 { font-size: 1.25rem; font-weight: 700; color: #38bdf8; }
        .gym-info p { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-paid { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e; }
        .badge-due { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.5px; }
        .row { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px; }
        .row .label { color: #94a3b8; }
        .row .val { font-weight: 600; color: #f1f5f9; }
        .item-box { background: #0f172a; border-radius: 10px; padding: 12px; margin-bottom: 16px; border: 1px solid #334155; }
        .divider { height: 1px; background: #334155; margin: 16px 0; }
        .total-row { display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: 800; color: #38bdf8; }
        .footer { text-align: center; font-size: 0.75rem; color: #64748b; margin-top: 20px; border-top: 1px dashed #334155; padding-top: 14px; }
        .btn-print { width: 100%; margin-top: 16px; padding: 12px; background: #0284c7; color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; }
        @media print {
            body { background: white; color: black; padding: 0; }
            .receipt-card { background: white; border: none; box-shadow: none; color: black; max-width: 100%; }
            .gym-info h1, .total-row { color: black; }
            .btn-print { display: none; }
            .item-box { background: #f8fafc; border-color: #cbd5e1; }
            .row .val { color: black; }
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="header">
            <?php if ($logoUrl): ?>
                <img src="<?php echo e($logoUrl); ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="gym-info">
                <h1><?php echo e($gymName); ?></h1>
                <p><?php echo e($tenant['address'] ?? ''); ?></p>
                <p>Phone: <?php echo e($tenant['phone'] ?? ''); ?></p>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <span class="badge <?php echo $statusRaw === 'paid' ? 'badge-paid' : 'badge-due'; ?>"><?php echo e($statusLabel); ?></span>
                <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">#<?php echo e($invoice['invoice_number']); ?></div>
            </div>
            <div style="text-align: right; font-size: 0.85rem; color: #94a3b8;">
                <div>Payment Date: <strong><?php echo e(format_date($invoice['payment_date'] ?? '')); ?></strong></div>
                <div>Method: <strong><?php echo e($invoice['payment_method'] ?? ''); ?></strong></div>
            </div>
        </div>

        <div class="item-box">
            <div class="row">
                <strong><?php echo e($invoice['service_name'] ?? 'Membership'); ?> (<?php echo (int)($invoice['plan_months'] ?? 1); ?> Mo)</strong>
                <span style="font-weight: 700;"><?php echo $money($totalAmt); ?></span>
            </div>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">Ref: <?php echo e(($invoice['transaction_ref'] ?? '') ?: 'Direct Payment'); ?></div>
        </div>

        <div class="section">
            <div class="section-title">Member Details</div>
            <div class="row"><span class="label">Name:</span> <span class="val"><?php echo e($member['fullname'] ?? 'Former member'); ?></span></div>
            <div class="row"><span class="label">Contact:</span> <span class="val"><?php echo e($member['contact'] ?? ''); ?></span></div>
            <div class="row"><span class="label">Member ID:</span> <span class="val">#MEM-<?php echo e(str_pad((string)$memberUserId, 4, '0', STR_PAD_LEFT)); ?></span></div>
        </div>

        <div class="divider"></div>

        <div class="row"><span class="label">Subtotal:</span> <span class="val"><?php echo $money($totalAmt); ?></span></div>
        <?php if ($discount > 0): ?>
            <div class="row"><span class="label">Discount:</span> <span class="val">-<?php echo $money($discount); ?></span></div>
        <?php endif; ?>
        <div class="total-row">
            <span>Total Paid:</span>
            <span><?php echo $money($paidAmt); ?></span>
        </div>

        <button class="btn-print" onclick="window.print()">Print Official Receipt</button>

        <div class="footer">
            <?php echo e(($tenant['invoice_footer'] ?? '') ?: 'Thank you for choosing ' . $gymName); ?>
            <p style="margin-top: 4px;">This is a system generated receipt.</p>
        </div>
    </div>
</body>
</html>
