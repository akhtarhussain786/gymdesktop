<?php
/**
 * Gym Admin Official Member Registration & Onboarding HTML Docket
 * Renders an ultra-clean, printable A4 membership registration certificate with signatures.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate(true);
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$memberId = (int)($_GET['member_id'] ?? $_GET['id'] ?? 0);
if ($memberId <= 0) {
    ApiResponse::error('Member ID is required', 400);
}

// Fetch member
$member = DB::fetchOne(
    "SELECT m.*, u.email as user_email, u.status as account_status 
     FROM members m 
     LEFT JOIN users u ON (m.user_id = u.id AND u.tenant_id = m.tenant_id)
     WHERE m.user_id = ? AND m.tenant_id = ?",
    [$memberId, $tenantId]
);

if (!$member) {
    ApiResponse::notFound('Member not found.');
}

// Fetch invoice
$invoice = DB::fetchOne(
    "SELECT * FROM invoices 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY id ASC LIMIT 1",
    [$tenantId, $memberId]
);
if (!$invoice) {
    $invoice = DB::fetchOne(
        "SELECT * FROM invoices 
         WHERE tenant_id = ? AND member_id = ? 
         ORDER BY id DESC LIMIT 1",
        [$tenantId, $memberId]
    );
}

$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . basename($tenant['logo']))) {
        $logoUrl = base_url('/uploads/logos/' . basename($tenant['logo']));
    }
}

$avatarUrl = api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null);

$dor = $member['dor'] ?: date('Y-m-d');
$planMonths = max(1, (int)($member['plan'] ?? $invoice['plan_months'] ?? 1));
$computedExpiry = date('Y-m-d', strtotime(($member['paid_date'] ?: $dor) . " +$planMonths months"));

$totalFee = (float)($invoice['amount'] ?? $member['amount'] ?? 0.0);
$paidFee = (float)($invoice['paid_amount'] ?? ($totalFee - (float)($member['due_amount'] ?? 0.0)));
$discount = (float)($invoice['discount'] ?? 0.0);
$dueBalance = max(0.0, (float)($member['due_amount'] ?? ($totalFee - $paidFee - $discount)));
$currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';

$invNumber = $invoice['invoice_number'] ?? ('REG-' . strtoupper(substr($tenant['gym_name'] ?? 'GYM', 0, 3)) . '-' . str_pad($memberId, 4, '0', STR_PAD_LEFT));
$paymentMethod = $invoice['payment_method'] ?? 'Cash';
$paymentStatus = ($dueBalance <= 0) ? 'Paid' : ($paidFee > 0 ? 'Partial' : 'Unpaid');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Docket - <?php echo e($member['fullname']); ?> (#MEM-<?php echo str_pad($memberId, 4, '0', STR_PAD_LEFT); ?>)</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', -apple-system, Roboto, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .doc-wrapper { max-width: 800px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 32px; position: relative; }
        .top-accent { height: 6px; background: linear-gradient(90deg, #10b981, #00d9ff, #3b82f6); border-radius: 16px 16px 0 0; position: absolute; top: 0; left: 0; right: 0; }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-top: 6px; margin-bottom: 20px; }
        .brand-info { display: flex; gap: 14px; align-items: center; }
        .logo-box { width: 64px; height: 64px; border-radius: 12px; background: #f1f5f9; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .gym-name { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.2; }
        .gym-contact { font-size: 0.82rem; color: #64748b; margin-top: 4px; line-height: 1.4; }
        .doc-title-badge { text-align: right; }
        .badge-main { display: inline-block; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 800; font-size: 0.78rem; padding: 6px 14px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px; }
        .reg-number { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 6px; }
        .reg-date { font-size: 0.8rem; color: #64748b; margin-top: 2px; }
        
        .section-title { font-size: 0.82rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; margin: 18px 0 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
        
        .profile-card { display: flex; gap: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; align-items: center; }
        .photo-box { width: 70px; height: 70px; border-radius: 50%; background: #e2e8f0; border: 2px solid #10b981; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.88rem; width: 100%; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; font-size: 0.88rem; }
        .info-cell span { display: block; font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-cell strong { font-size: 0.92rem; color: #0f172a; }
        
        .table-data { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 0.88rem; }
        .table-data th { background: #f1f5f9; text-align: left; padding: 10px 14px; font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; border-radius: 6px; }
        .table-data td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
        
        .payment-summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; margin-top: 12px; }
        .pay-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 0.88rem; }
        .pay-row-bold { font-size: 1.05rem; font-weight: 800; border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 6px; }
        
        .terms-box { font-size: 0.76rem; color: #64748b; line-height: 1.5; background: #fafafa; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px; margin-top: 16px; }
        
        .sign-row { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 0.82rem; }
        .sign-col { text-align: center; width: 200px; }
        .sign-line { border-top: 1px solid #94a3b8; margin-top: 36px; padding-top: 4px; font-weight: 700; color: #0f172a; }
        
        .btn-bar { display: flex; gap: 12px; justify-content: center; margin-bottom: 20px; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-decoration: none; border: none; }
        .btn-print { background: #10b981; color: #ffffff; }
        .btn-close { background: #64748b; color: #ffffff; }
        @media print { .no-print { display: none !important; } body { background: #fff; padding: 0; } .doc-wrapper { box-shadow: none; border: none; padding: 0; } }
    </style>
</head>
<body>

    <div class="btn-bar no-print">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Registration Docket / Save PDF</button>
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <div class="doc-wrapper">
        <div class="top-accent"></div>
        
        <!-- Header -->
        <div class="doc-header">
            <div class="brand-info">
                <div class="logo-box">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo e($logoUrl); ?>" alt="Gym Logo">
                    <?php else: ?>
                        <span style="font-weight:900; color:#10b981; font-size:1.1rem;">FIT</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="gym-name"><?php echo e($tenant['gym_name'] ?: 'FITISIFY FITNESS'); ?></h1>
                    <div class="gym-contact">
                        <?php echo e($tenant['phone'] ?: ''); ?> <?php echo $tenant['email'] ? '• ' . e($tenant['email']) : ''; ?><br>
                        <?php echo e($tenant['address'] ?: 'Gym Headquarters'); ?>
                    </div>
                </div>
            </div>
            <div class="doc-title-badge">
                <div class="badge-main">Official Member Docket</div>
                <div class="reg-number">#MEM-<?php echo str_pad($memberId, 4, '0', STR_PAD_LEFT); ?></div>
                <div class="reg-date">Issued: <?php echo date('d M Y', strtotime($dor)); ?></div>
            </div>
        </div>

        <!-- 1. Member Profile Details -->
        <div class="section-title">1. Member Personal Profile</div>
        <div class="profile-card">
            <div class="photo-box">
                <?php if ($avatarUrl): ?>
                    <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($member['fullname']); ?>">
                <?php else: ?>
                    <span style="font-weight:900; font-size:1.3rem; color:#10b981;">
                        <?php echo strtoupper(substr($member['fullname'] ?: 'M', 0, 1)); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="grid-2">
                <div class="info-cell">
                    <span>Full Name</span>
                    <strong><?php echo e($member['fullname']); ?></strong>
                </div>
                <div class="info-cell">
                    <span>Mobile Number</span>
                    <strong><?php echo e($member['contact'] ?: 'N/A'); ?></strong>
                </div>
                <div class="info-cell">
                    <span>Email Address</span>
                    <strong><?php echo e($member['user_email'] ?: ($member['email'] ?: 'N/A')); ?></strong>
                </div>
                <div class="info-cell">
                    <span>Gender / DOB</span>
                    <strong><?php echo e($member['gender'] ?: 'Male'); ?> <?php echo !empty($member['dob']) ? '• ' . date('d M Y', strtotime($member['dob'])) : ''; ?></strong>
                </div>
                <div class="info-cell" style="grid-column: span 2;">
                    <span>Address</span>
                    <strong><?php echo e($member['address'] ?: 'On Record'); ?></strong>
                </div>
            </div>
        </div>

        <!-- 2. Membership Plan & Schedule -->
        <div class="section-title">2. Membership & Fitness Regimen Details</div>
        <table class="table-data">
            <thead>
                <tr>
                    <th>Service / Plan</th>
                    <th>Duration</th>
                    <th>Start Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo e($member['services'] ?: 'General Fitness'); ?></strong></td>
                    <td><?php echo (int)$planMonths; ?> Months</td>
                    <td><?php echo date('d M Y', strtotime($dor)); ?></td>
                    <td><strong style="color:#2563eb;"><?php echo date('d M Y', strtotime($computedExpiry)); ?></strong></td>
                    <td><span style="color:#15803d; font-weight:800;">● <?php echo e(ucfirst($member['status'] ?: 'Active')); ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- 3. Financial & Fee Breakdown -->
        <div class="section-title">3. Registration & Initial Payment Record</div>
        <div class="payment-summary-box">
            <div class="grid-3" style="margin-bottom: 12px;">
                <div class="info-cell">
                    <span>Invoice / Receipt #</span>
                    <strong><?php echo e($invNumber); ?></strong>
                </div>
                <div class="info-cell">
                    <span>Payment Mode</span>
                    <strong><?php echo e($paymentMethod); ?></strong>
                </div>
                <div class="info-cell">
                    <span>Payment Date</span>
                    <strong><?php echo date('d M Y', strtotime($invoice['payment_date'] ?? $dor)); ?></strong>
                </div>
            </div>
            <div class="pay-row">
                <span>Total Registration & Package Fee:</span>
                <span><?php echo e($currency) . number_format($totalFee, 2); ?></span>
            </div>
            <?php if ($discount > 0): ?>
                <div class="pay-row" style="color: #059669;">
                    <span>Discount Applied:</span>
                    <span>-<?php echo e($currency) . number_format($discount, 2); ?></span>
                </div>
            <?php endif; ?>
            <div class="pay-row">
                <span>Amount Paid at Registration:</span>
                <strong style="color:#059669;"><?php echo e($currency) . number_format($paidFee, 2); ?></strong>
            </div>
            <div class="pay-row pay-row-bold">
                <span>Remaining Due Balance:</span>
                <span style="color: <?php echo $dueBalance > 0 ? '#b91c1c' : '#059669'; ?>;">
                    <?php echo $dueBalance > 0 ? e($currency) . number_format($dueBalance, 2) . ' (PARTIAL)' : '₹0.00 (CLEAR)'; ?>
                </span>
            </div>
        </div>

        <!-- 4. Rules & Terms -->
        <div class="terms-box">
            <strong>Membership Terms & Health Declaration:</strong><br>
            • Member certifies that they are physically fit to participate in physical exercise.<br>
            • Fees paid are non-refundable and non-transferable under any circumstance.<br>
            • Digital QR Member Pass is required for every entry at turnstile or front desk.<br>
            • Gym management reserves the right to terminate membership for violation of facility decorum.
        </div>

        <!-- 5. Signatures -->
        <div class="sign-row">
            <div class="sign-col">
                <div class="sign-line">Member Signature</div>
            </div>
            <div class="sign-col">
                <div class="sign-line">Authorized Gym Signatory</div>
            </div>
        </div>
        
        <div style="text-align:center; font-size:0.72rem; color:#94a3b8; margin-top:20px;">
            This is a computer-generated official membership onboarding record powered by Fitisify SaaS.
        </div>
    </div>

</body>
</html>
