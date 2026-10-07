<?php
/**
 * Super Admin SaaS Payments, Orders & Credentials Delivery Management
 */
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/account_provisioner.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth('super_admin');

$page = 'super_payments';
$pageTitle = 'SaaS Payments & Orders';
$pageSubtitle = 'Monitor gateway transactions, payment verifications, and onboarding credentials delivery';

// Auto-purge abandoned pending/failed orders older than 2 days (48 hours)
$cutoffTime = date('Y-m-d H:i:s', strtotime('-2 days'));
DB::query("DELETE FROM saas_payments WHERE status IN ('pending', 'failed') AND created_at < ?", [$cutoffTime]);

// Handle Resend / Reset Credentials Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_credentials'])) {
    Auth::verifyCsrf();

    $paymentId = (int)$_POST['payment_id'];
    $payment = DB::fetchOne("SELECT p.*, t.gym_name, t.owner_name, t.email, t.currency, sp.name as plan_name 
                             FROM saas_payments p 
                             LEFT JOIN tenants t ON p.tenant_id = t.id 
                             LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                             WHERE p.id = ?", [$paymentId]);

    if ($payment && !empty($payment['tenant_id'])) {
        $adminUser = DB::fetchOne("SELECT * FROM users WHERE tenant_id = ? AND role = 'gym_admin' LIMIT 1", [$payment['tenant_id']]);
        if ($adminUser) {
            $newTempPass = AccountProvisioner::generateStrongTemporaryPassword(12);
            $newHash = password_hash($newTempPass, PASSWORD_DEFAULT);

            // Update user password and set must_change_password = 1
            DB::update('users', [
                'password' => $newHash,
                'must_change_password' => 1
            ], 'id = ?', [$adminUser['id']]);

            // Dispatch Credentials Email with force_resend = true
            $mailResult = Mailer::sendCredentialsEmail([
                'to_email' => $payment['email'],
                'owner_name' => $payment['owner_name'],
                'gym_name' => $payment['gym_name'],
                'plan_name' => $payment['plan_name'],
                'amount_paid' => $payment['total_payable'],
                'currency' => $payment['currency'] ?: '₹',
                'activation_date' => $payment['start_date'],
                'expiry_date' => $payment['end_date'],
                'username' => $adminUser['username'],
                'temp_password' => $newTempPass,
                'order_ref' => $payment['transaction_ref'] ?: ('GYM_' . $payment['tenant_id']),
                'tenant_id' => (int)$payment['tenant_id'],
                'force_resend' => true
            ]);

            DB::update('saas_payments', [
                'email_status' => $mailResult['success'] ? 'sent' : 'failed',
                'email_sent_at' => date('Y-m-d H:i:s'),
                'failure_reason' => $mailResult['error'] ?? null
            ], 'id = ?', [$paymentId]);

            Auth::auditLog('RESEND_CREDENTIALS', "Resent credentials to {$payment['email']} for Gym #{$payment['tenant_id']}");
            if ($mailResult['success']) {
                set_flash('success', "New temporary credentials generated and emailed to {$payment['email']}.");
            } else {
                set_flash('warning', "New temporary credentials generated, but email delivery encountered an issue: " . htmlspecialchars($mailResult['error']));
            }
        } else {
            set_flash('error', "No Gym Admin user found for this tenant.");
        }
    } else {
        set_flash('error', "Payment or Gym record not found.");
    }

    redirect(base_url('/superadmin/payments.php'));
}

// Handle Tenant Status Toggle (Activate/Suspend) — POST + CSRF only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    Auth::verifyCsrf();
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $tenantRow = DB::fetchOne("SELECT id, status FROM tenants WHERE id = ?", [$tenantId]);
    if ($tenantRow) {
        $newStatus = ($tenantRow['status'] === 'suspended') ? 'active' : 'suspended';
        DB::update('tenants', ['status' => $newStatus], 'id = ?', [$tenantId]);
        Auth::auditLog('TOGGLE_TENANT_STATUS', "Changed tenant #$tenantId status to $newStatus");
        set_flash('success', "Gym status updated to " . ucfirst($newStatus) . ".");
    } else {
        set_flash('error', "Gym not found.");
    }
    redirect(base_url('/superadmin/payments.php'));
}

// Approve / Reject pending SaaS renewal payments (manual payments, or re-check a gateway order)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_payment']) || isset($_POST['reject_payment']))) {
    Auth::verifyCsrf();
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $payment = DB::fetchOne("SELECT * FROM saas_payments WHERE id = ?", [$paymentId]);

    if (!$payment || !in_array($payment['status'], ['pending', 'failed'], true)) {
        set_flash('error', "Payment not found or already processed.");
    } elseif ((int)$payment['tenant_id'] <= 0) {
        set_flash('error', "Onboarding payments are activated automatically after gateway verification.");
    } elseif (isset($_POST['approve_payment'])) {
        if ($payment['payment_method'] === 'cashfree') {
            // Gateway orders are only approved after Cashfree confirms full payment
            $res = SubscriptionEngine::finalizeSaasGatewayOrder($payment['transaction_ref']);
        } else {
            $res = SubscriptionEngine::applySaasPayment($paymentId, ['approved_by' => (int)($_SESSION['user_id'] ?? 0)]);
        }
        if ($res['success']) {
            Auth::auditLog('APPROVE_SAAS_PAYMENT', "Approved SaaS payment #$paymentId for tenant #{$payment['tenant_id']} (new expiry " . ($res['end_date'] ?? '-') . ")");
            set_flash('success', "Payment approved. Subscription extended until " . format_date($res['end_date'] ?? '') . ".");
        } else {
            set_flash('error', "Could not approve payment: " . ($res['error'] ?? 'Unknown error'));
        }
    } else {
        DB::query("UPDATE saas_payments SET status = 'rejected', approved_by = ? WHERE id = ? AND status IN ('pending','failed')", [(int)($_SESSION['user_id'] ?? 0), $paymentId]);
        Auth::auditLog('REJECT_SAAS_PAYMENT', "Rejected SaaS payment #$paymentId for tenant #{$payment['tenant_id']}");
        set_flash('success', "Payment rejected.");
    }
    redirect(base_url('/superadmin/payments.php'));
}

// Delete single order (pending, failed, rejected, or test orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment'])) {
    Auth::verifyCsrf();
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $payment = DB::fetchOne("SELECT * FROM saas_payments WHERE id = ?", [$paymentId]);

    if (!$payment) {
        set_flash('error', "Payment record not found.");
    } else {
        DB::query("DELETE FROM saas_payments WHERE id = ?", [$paymentId]);
        Auth::auditLog('DELETE_SAAS_PAYMENT', "Deleted SaaS payment #$paymentId (Ref: {$payment['transaction_ref']})");
        set_flash('success', "Order #{$payment['transaction_ref']} deleted successfully.");
    }
    redirect(base_url('/superadmin/payments.php'));
}

// Bulk purge all pending / failed orders older than 2 days
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_old_pending'])) {
    Auth::verifyCsrf();
    $cutoff = date('Y-m-d H:i:s', strtotime('-2 days'));
    DB::query("DELETE FROM saas_payments WHERE status IN ('pending', 'failed', 'rejected') AND created_at < ?", [$cutoff]);
    Auth::auditLog('PURGE_OLD_PENDING_PAYMENTS', "Purged abandoned SaaS orders older than 2 days");
    set_flash('success', "All pending / unpaid orders older than 2 days have been purged.");
    redirect(base_url('/superadmin/payments.php'));
}

// Bulk purge all test and orphaned records (Keep Demo Gym only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_keep_demo_only'])) {
    Auth::verifyCsrf();
    // Delete all payments where tenant_id is 0 or tenant is not Demo Gym
    DB::query("DELETE FROM saas_payments WHERE tenant_id <= 0 OR tenant_id NOT IN (SELECT id FROM tenants WHERE gym_name LIKE '%demo%' OR owner_name LIKE '%demo%')");
    // Delete non-demo orphaned test tenants
    DB::query("DELETE FROM tenants WHERE gym_name NOT LIKE '%demo%' AND owner_name NOT LIKE '%demo%' AND email NOT LIKE '%akhtar%' AND id NOT IN (SELECT tenant_id FROM saas_payments WHERE tenant_id > 0)");
    Auth::auditLog('CLEANUP_SAAS_PAYMENTS', "Purged all test and orphaned orders, keeping only Demo Gym");
    set_flash('success', "All test and inactive onboarding orders removed. Only Demo Gym is retained.");
    redirect(base_url('/superadmin/payments.php'));
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$query = "SELECT p.*, t.gym_name, t.owner_name, t.email as tenant_email, t.phone as tenant_phone, t.status as tenant_status, sp.name as plan_name 
          FROM saas_payments p 
          LEFT JOIN tenants t ON p.tenant_id = t.id 
          LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
          WHERE 1=1 ";
$params = [];

if (!empty($search)) {
    $query .= "AND (t.gym_name LIKE ? OR t.owner_name LIKE ? OR t.email LIKE ? OR p.transaction_ref LIKE ? OR p.cf_order_id LIKE ? OR p.cf_payment_id LIKE ?) ";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
}

if (!empty($statusFilter)) {
    $query .= "AND p.status = ? ";
    $params[] = $statusFilter;
}

$query .= "ORDER BY p.id DESC";
$payments = DB::fetchAll($query, $params);

// Stats Summary
$totalRevenue = (float)DB::fetchValue("SELECT SUM(total_payable) FROM saas_payments WHERE status = 'approved'");
$totalTransactions = (int)DB::fetchValue("SELECT COUNT(*) FROM saas_payments");
$successfulPayments = (int)DB::fetchValue("SELECT COUNT(*) FROM saas_payments WHERE status = 'approved'");
$pendingPayments = (int)DB::fetchValue("SELECT COUNT(*) FROM saas_payments WHERE status = 'pending'");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Financial Stats Grid -->
<div class="stats-grid">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Total SaaS Revenue</h3>
            <div class="stat-value">₹<?php echo number_format($totalRevenue, 2); ?></div>
            <div class="stat-meta">Verified Cashfree Collections</div>
        </div>
        <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Successful Payments</h3>
            <div class="stat-value"><?php echo $successfulPayments; ?> / <?php echo $totalTransactions; ?></div>
            <div class="stat-meta">Approved Transactions</div>
        </div>
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Pending Checkouts</h3>
            <div class="stat-value"><?php echo $pendingPayments; ?></div>
            <div class="stat-meta">Awaiting Gateway Confirmation</div>
        </div>
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
</div>

<!-- Payments & Orders Table -->
<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div class="card-title">
            <i class="fas fa-credit-card"></i>
            <span>SaaS Gateway Orders & Invoices</span>
        </div>

        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="Search Gym, Order ID, Email..." value="<?php echo e($search); ?>" style="width: 240px; height: 36px;" />
            <select name="status" class="form-select" style="width: 140px; height: 36px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved / Paid</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="payments.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset</a>
            <?php endif; ?>
        </form>

        <form method="POST" action="" style="margin: 0; display: flex; gap: 8px;" onsubmit="return confirm('Delete all test & orphaned checkouts and keep only Demo Gym?');">
            <?php echo Auth::csrfField(); ?>
            <button type="submit" name="cleanup_keep_demo_only" value="1" class="btn btn-sm btn-danger" title="Delete all test/orphaned checkouts and keep only Demo Gym">
                <i class="fas fa-trash"></i> Clean Test Orders (Keep Demo Only)
            </button>
        </form>

        <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Delete all abandoned pending / unpaid orders older than 2 days?');">
            <?php echo Auth::csrfField(); ?>
            <button type="submit" name="purge_old_pending" value="1" class="btn btn-sm btn-outline-danger" title="Purge abandoned orders older than 48 hours">
                <i class="fas fa-broom"></i> Clean Pending (>2 Days)
            </button>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order & Txn Ref</th>
                        <th>Gym / Owner</th>
                        <th>Plan & Cycle</th>
                        <th>Amount (₹)</th>
                        <th>Payment Status</th>
                        <th>Email Delivery</th>
                        <th>Gym Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No SaaS payment records match your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 0.9rem; color: var(--text-main); font-family: monospace;">
                                        <?php echo e($p['transaction_ref']); ?>
                                    </strong>
                                    <?php if (!empty($p['cf_payment_id'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">CF: <?php echo e($p['cf_payment_id']); ?></div>
                                    <?php endif; ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo format_date($p['created_at']); ?></div>
                                </td>
                                <td>
                                    <strong style="font-size: 0.95rem; color: var(--text-main);"><?php echo e($p['gym_name'] ?: 'Pending Registration'); ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($p['owner_name']); ?> • <?php echo e($p['tenant_email']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo e($p['plan_name'] ?: 'SaaS Tier'); ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e(ucfirst($p['billing_cycle'])); ?> • <?php echo e(strtoupper($p['payment_method'])); ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 800; color: var(--primary); font-size: 1.05rem;">
                                        ₹<?php echo number_format($p['total_payable'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'approved'): ?>
                                        <span class="status-badge badge-success"><i class="fas fa-check"></i> Paid</span>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <span class="status-badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger"><i class="fas fa-times"></i> <?php echo e(ucfirst($p['status'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['email_status'] === 'sent'): ?>
                                        <span class="status-badge badge-success" title="Email dispatched at <?php echo e($p['email_sent_at']); ?>">
                                            <i class="fas fa-envelope"></i> Sent
                                        </span>
                                    <?php elseif ($p['email_status'] === 'failed'): ?>
                                        <span class="status-badge badge-danger" title="<?php echo e($p['failure_reason']); ?>">
                                            <i class="fas fa-exclamation-triangle"></i> Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge badge-info"><i class="fas fa-minus"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['tenant_id'])): ?>
                                        <?php if ($p['tenant_status'] === 'active'): ?>
                                            <span class="status-badge badge-success">Active</span>
                                        <?php elseif ($p['tenant_status'] === 'trial'): ?>
                                            <span class="status-badge badge-info">Trial</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-danger"><?php echo e(ucfirst($p['tenant_status'] ?? 'Inactive')); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">Not Activated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <?php if (!empty($p['tenant_id']) && !empty($p['tenant_status']) && $p['status'] === 'approved'): /* gym may have been deleted */ ?>
                                            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode('Generate a new secure temporary password and email it to ' . $p['tenant_email'] . '?')); ?>);">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>" />
                                                <button type="submit" name="resend_credentials" value="1" class="btn btn-secondary btn-sm" title="Resend / Reset Credentials">
                                                    <i class="fas fa-key"></i> Resend Email
                                                </button>
                                            </form>

                                            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode('Change status of ' . ($p['gym_name'] ?? '') . '?')); ?>);">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="tenant_id" value="<?php echo (int)$p['tenant_id']; ?>" />
                                                <button type="submit" name="toggle_status" value="1"
                                                        class="btn btn-sm <?php echo $p['tenant_status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>"
                                                        title="<?php echo $p['tenant_status'] === 'active' ? 'Suspend Gym' : 'Activate Gym'; ?>">
                                                    <i class="fas <?php echo $p['tenant_status'] === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!empty($p['tenant_id']) && in_array($p['status'], ['pending', 'failed'], true)): ?>
                                            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode(($p['payment_method'] === 'cashfree' ? 'Re-verify this order with Cashfree and apply it' : 'Confirm payment received and extend subscription') . ' for ' . ($p['gym_name'] ?? '') . '?')); ?>);">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>" />
                                                <button type="submit" name="approve_payment" value="1" class="btn btn-success btn-sm" title="Approve payment">
                                                    <i class="fas fa-check-double"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode('Permanently delete order ' . $p['transaction_ref'] . '?')); ?>);">
                                            <?php echo Auth::csrfField(); ?>
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>" />
                                            <button type="submit" name="delete_payment" value="1" class="btn btn-sm" style="background-color: #ef4444; border-color: #ef4444; color: #ffffff; padding: 4px 8px; border-radius: 6px;" title="Delete Order">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
