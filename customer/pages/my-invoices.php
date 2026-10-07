<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_invoices';
$pageTitle = 'My Invoices & Payment Receipts';
$pageSubtitle = 'View and download receipts for your gym membership fees';
$memberId = $_SESSION['user_id'];
$tenantId = Tenant::getTenantId();

$invoices = DB::fetchAll("SELECT * FROM invoices WHERE member_id = ? AND tenant_id = ? ORDER BY payment_date DESC, id DESC", [$memberId, $tenantId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-receipt"></i>
            <span>Payment History & Receipts (<?php echo count($invoices); ?>)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Service / Plan</th>
                        <th>Amount Paid</th>
                        <th>Payment Mode</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No payment invoices found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo e($inv['invoice_number']); ?></strong></td>
                                <td><?php echo e($inv['service_name']); ?> (<?php echo $inv['plan_months']; ?> Month<?php echo $inv['plan_months'] > 1 ? 's' : ''; ?>)</td>
                                <td><strong style="color: var(--primary);"><?php echo format_currency($inv['paid_amount']); ?></strong></td>
                                <td><?php echo e($inv['payment_method']); ?></td>
                                <td><?php echo format_date($inv['payment_date']); ?></td>
                                <td><?php echo status_badge($inv['status']); ?></td>
                                <td>
                                    <a href="<?php echo base_url('/admin/userpay.php?id=' . $inv['id']); ?>" class="btn btn-secondary btn-sm" title="View & Print Official Receipt">
                                        <i class="fas fa-print"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
