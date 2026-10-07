<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'member']);

$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

$invoiceId = (int)($_GET['id'] ?? 0);
$memberId = (int)($_GET['member_id'] ?? 0);

$invoice = null;

// Members may only ever see their own invoices — never another member's, never a backfilled one
$isMemberViewer = (($_SESSION['role'] ?? '') === 'member');
if ($isMemberViewer) {
    $memberId = (int)($_SESSION['user_id'] ?? 0);
    if ($invoiceId <= 0 || $memberId <= 0) {
        redirect(base_url('/customer/pages/my-invoices.php'), 'error', 'Invoice record not found.');
    }
    $invoice = DB::fetchOne(
        "SELECT i.*, m.fullname, m.contact, m.email, m.address
         FROM invoices i
         LEFT JOIN members m ON i.member_id = m.user_id AND m.tenant_id = i.tenant_id
         WHERE i.id = ? AND i.tenant_id = ? AND i.member_id = ?",
        [$invoiceId, $tenantId, $memberId]
    );
    if (!$invoice) {
        redirect(base_url('/customer/pages/my-invoices.php'), 'error', 'Invoice record not found.');
    }
}

// ─── Step 1: Try to find invoice from `invoices` table ────────────────────
try {
    if (!$isMemberViewer && $invoiceId > 0) {
        $invoice = DB::fetchOne(
            "SELECT i.*, m.fullname, m.contact, m.email, m.address
             FROM invoices i
             LEFT JOIN members m ON i.member_id = m.user_id AND m.tenant_id = i.tenant_id
             WHERE i.id = ? AND i.tenant_id = ?",
            [$invoiceId, $tenantId]
        );
    }

    if (!$isMemberViewer && !$invoice && $memberId > 0) {
        $invoice = DB::fetchOne(
            "SELECT i.*, m.fullname, m.contact, m.email, m.address
             FROM invoices i
             LEFT JOIN members m ON i.member_id = m.user_id AND m.tenant_id = i.tenant_id
             WHERE i.member_id = ? AND i.tenant_id = ?
             ORDER BY i.id DESC LIMIT 1",
            [$memberId, $tenantId]
        );
    }

    if (!$isMemberViewer && !$invoice && $invoiceId <= 0 && $memberId <= 0) {
        $invoice = DB::fetchOne(
            "SELECT i.*, m.fullname, m.contact, m.email, m.address
             FROM invoices i
             LEFT JOIN members m ON i.member_id = m.user_id AND m.tenant_id = i.tenant_id
             WHERE i.tenant_id = ?
             ORDER BY i.id DESC LIMIT 1",
            [$tenantId]
        );
    }
} catch (Throwable $e) {
    $invoice = null;
}

// ─── Step 2: Idempotent persistent backfill from member profile ────────────
if (!$invoice && !$isMemberViewer) {
    $member = null;

    if ($memberId > 0) {
        $member = DB::fetchOne(
            "SELECT * FROM members WHERE user_id = ? AND tenant_id = ?",
            [$memberId, $tenantId]
        );
    }

    if (!$member && $invoiceId <= 0) {
        // Fallback to latest member in this gym
        $member = DB::fetchOne(
            "SELECT * FROM members WHERE tenant_id = ? ORDER BY user_id DESC LIMIT 1",
            [$tenantId]
        );
    }

    if ($member) {
        $realMemId = (int)($member['user_id'] ?? $member['id'] ?? 1);
        $amount = (float)($member['amount'] ?? 0);
        $planMonths = max(1, (int)($member['plan'] ?? 1));
        $services = !empty($member['services']) ? $member['services'] : 'Fitness Membership';
        $paidDate = !empty($member['paid_date']) ? $member['paid_date'] : date('Y-m-d');

        // Check if an invoice was created in the meantime to prevent duplicates
        $existingInv = DB::fetchOne(
            "SELECT * FROM invoices WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1",
            [$realMemId, $tenantId]
        );

        if ($existingInv) {
            $invoice = $existingInv;
        } else {
            // Collision-free invoice number (assigned from the inserted id below)
            require_once __DIR__ . '/../core/subscription_engine.php';
            $invoiceNumber = SubscriptionEngine::tempInvoiceNumber();

            // Insert into invoices table
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
                'notes' => 'Official membership payment invoice',
                'created_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if ($newInvId) {
                SubscriptionEngine::assignInvoiceNumber($newInvId, $tenant, $paidDate);
                $invoice = DB::fetchOne("SELECT * FROM invoices WHERE id = ?", [$newInvId]);
            }
        }

        if (!$invoice) {
            $invoice = [
                'id'              => 0,
                'invoice_number'  => 'INV-' . str_pad($realMemId, 5, '0', STR_PAD_LEFT),
                'tenant_id'       => $tenantId,
                'member_id'       => $realMemId,
                'amount'          => $amount,
                'paid_amount'     => $amount,
                'discount'        => 0,
                'status'          => 'Paid',
                'service_name'    => $services,
                'plan_months'     => $planMonths,
                'payment_date'    => $paidDate,
                'payment_method'  => 'Cash',
                'transaction_ref' => 'AUTO-REC-' . str_pad($realMemId, 4, '0', STR_PAD_LEFT),
                'notes'           => ''
            ];
        }

        $invoice['fullname'] = $member['fullname'] ?? 'Gym Member';
        $invoice['contact']  = $member['contact'] ?? '';
        $invoice['email']    = $member['email'] ?? '';
        $invoice['address']  = $member['address'] ?? '';
    }
}

if (!$invoice) {
    redirect('payment.php', 'error', 'Invoice record not found.');
}

$pageTitle = 'Invoice ' . ($invoice['invoice_number'] ?? 'N/A');
$pageSubtitle = 'Official payment receipt and invoice details';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px;" class="no-print">
    <a href="payment.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Payments
    </a>
    <?php if (!empty($invoice['id']) && $invoice['id'] > 0): ?>
    <a href="download-receipt-pdf.php?id=<?php echo $invoice['id']; ?>" class="btn btn-success btn-sm" target="_blank">
        <i class="fas fa-file-pdf"></i> Download PDF Receipt
    </a>
    <?php else: ?>
    <a href="download-receipt-pdf.php?member_id=<?php echo $invoice['member_id']; ?>" class="btn btn-success btn-sm" target="_blank">
        <i class="fas fa-file-pdf"></i> Download PDF Receipt
    </a>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fas fa-print"></i> Print Invoice / Receipt
    </button>
</div>

<!-- Printable Invoice Card -->
<div class="card" style="max-width: 800px; margin: 0 auto; padding: 36px;">
    <!-- Invoice Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border-color); padding-bottom: 24px; margin-bottom: 24px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <div style="width: 46px; height: 46px; border-radius: var(--radius-md); background: var(--bg-surface); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; overflow: hidden; flex-shrink: 0;">
                    <?php if (!empty($tenant['logo'])): ?>
                        <img src="<?php echo e(str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo'])); ?>" style="width: 100%; height: 100%; object-fit: contain;" />
                    <?php else: ?>
                        <i class="fas fa-dumbbell" style="color: var(--primary);"></i>
                    <?php endif; ?>
                </div>
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                    <?php echo e($tenant['gym_name']); ?>
                </h2>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                <?php echo e($tenant['address']); ?><br>
                Phone: <?php echo e($tenant['phone']); ?> • Email: <?php echo e($tenant['email']); ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary); font-family: var(--font-display);">INVOICE / RECEIPT</div>
            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-top: 4px;">#<?php echo e($invoice['invoice_number'] ?? 'N/A'); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Date: <?php echo format_date($invoice['payment_date'] ?? date('Y-m-d')); ?></div>
            <div style="margin-top: 6px;"><?php echo status_badge($invoice['status'] ?? 'Paid'); ?></div>
        </div>
    </div>

    <!-- Bill To / Customer Details -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <div style="background: var(--bg-app); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Billed To</div>
            <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin-top: 4px;"><?php echo e($invoice['fullname'] ?? 'Gym Member'); ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;">
                Member ID: #MEM-<?php echo str_pad($invoice['member_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?><br>
                Contact: <?php echo e($invoice['contact'] ?? ''); ?><br>
                <?php if (!empty($invoice['address'])): ?>
                    Address: <?php echo e($invoice['address']); ?>
                <?php endif; ?>
            </div>
        </div>

        <div style="background: var(--bg-app); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Payment Details</div>
            <div style="font-size: 0.88rem; color: var(--text-main); margin-top: 6px; line-height: 1.8;">
                Method: <strong><?php echo e($invoice['payment_method'] ?? 'Cash'); ?></strong><br>
                Payment Status: <strong><?php echo e($invoice['status'] ?? 'Paid'); ?></strong><br>
                Receipt Ref: <code><?php echo e($invoice['transaction_ref'] ?? $invoice['invoice_number'] ?? 'N/A'); ?></code>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <?php
    $totalAmt = (float)($invoice['amount'] ?? $invoice['paid_amount'] ?? $invoice['total_amount'] ?? 0);
    $planMonths = max(1, (int)($invoice['plan_months'] ?? 1));
    $discountAmt = (float)($invoice['discount'] ?? 0);
    $paidAmt = (float)($invoice['paid_amount'] ?? $invoice['amount'] ?? $totalAmt);
    ?>
    <div class="table-responsive" style="margin-bottom: 24px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Duration</th>
                    <th>Rate</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo e($invoice['service_name'] ?? 'Fitness'); ?> Membership</strong>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Gym access, training facilities & wellness services</div>
                    </td>
                    <td><?php echo $planMonths; ?> Month(s)</td>
                    <td><?php echo format_currency($planMonths > 0 ? $totalAmt / $planMonths : $totalAmt); ?> / mo</td>
                    <td style="text-align: right; font-weight: 700;"><?php echo format_currency($totalAmt); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Invoice Summary Calculation -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
        <div style="width: 280px; line-height: 2;">
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                <span style="color: var(--text-muted);">Subtotal:</span>
                <span><?php echo format_currency($totalAmt); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                <span style="color: var(--text-muted);">Discount:</span>
                <span><?php echo format_currency($discountAmt); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 800; border-top: 2px solid var(--border-color); padding-top: 6px; margin-top: 6px; color: var(--primary);">
                <span>Total Paid:</span>
                <span><?php echo format_currency($paidAmt); ?></span>
            </div>
        </div>
    </div>

    <!-- Invoice Footer / Terms -->
    <div style="border-top: 1px solid var(--border-color); padding-top: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
        <p style="font-weight: 600; color: var(--text-main); margin-bottom: 4px;"><?php echo e($tenant['invoice_footer'] ?? 'Thank you for your business!'); ?></p>
        <p>This is a computer-generated invoice and requires no physical signature.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
