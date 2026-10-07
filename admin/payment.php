<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'payment';
$pageTitle = 'Payments & Invoicing';
$pageSubtitle = 'Manage member subscriptions, renewals, fee payments, and printable receipts';
$tenantId = Tenant::getTenantId();

// Approve / reject member-submitted UPI renewals (UTR awaiting verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_upi']) || isset($_POST['reject_upi']))) {
    Auth::verifyCsrf();
    $pendingPayId = (int)($_POST['membership_payment_id'] ?? 0);
    $mp = DB::fetchOne(
        "SELECT * FROM membership_payments WHERE id = ? AND tenant_id = ? AND payment_status = 'PENDING' AND payment_method = 'Direct UPI QR'",
        [$pendingPayId, $tenantId]
    );
    if (!$mp) {
        redirect('payment.php', 'error', 'Pending payment not found or already processed.');
    }

    if (isset($_POST['approve_upi'])) {
        if (!Tenant::canWrite()) {
            redirect('payment.php', 'error', 'Your gym subscription has expired. Renew to record member payments.');
        }
        $res = SubscriptionEngine::processSuccessfulPayment(
            $mp['cashfree_order_id'],
            $mp['cashfree_payment_id'],
            ['verified_by' => $_SESSION['user_id'] ?? null, 'verified_at' => date('Y-m-d H:i:s'), 'utr' => $mp['cashfree_payment_id']],
            ['manual' => true, 'tenant_id' => $tenantId]
        );
        if (!$res['success']) {
            redirect('payment.php', 'error', 'Could not approve payment: ' . ($res['error'] ?? 'Unknown error'));
        }
        Auth::auditLog('APPROVE_UPI_RENEWAL', "Verified member UPI renewal (UTR {$mp['cashfree_payment_id']}) for member #{$mp['member_id']}");
        redirect(!empty($res['invoice_id']) ? "userpay.php?id={$res['invoice_id']}" : 'payment.php', 'success', 'Payment verified. ' . ($res['message'] ?? ''));
    } else {
        DB::update('membership_payments', ['payment_status' => 'CANCELLED'], 'id = ? AND payment_status = ?', [$mp['id'], 'PENDING']);
        DB::update('invoices', ['status' => 'cancelled', 'paid_amount' => 0], 'tenant_id = ? AND payment_id = ?', [$tenantId, $mp['id']]);
        Auth::auditLog('REJECT_UPI_RENEWAL', "Rejected member UPI renewal (UTR {$mp['cashfree_payment_id']}) for member #{$mp['member_id']}");
        redirect('payment.php', 'success', 'Payment submission rejected.');
    }
}

$pendingUpiPayments = DB::fetchAll(
    "SELECT mp.*, m.fullname, m.contact, r.name AS plan_name
     FROM membership_payments mp
     JOIN members m ON m.user_id = mp.member_id AND m.tenant_id = mp.tenant_id
     LEFT JOIN rates r ON r.id = mp.plan_id AND r.tenant_id = mp.tenant_id
     WHERE mp.tenant_id = ? AND mp.payment_status = 'PENDING' AND mp.payment_method = 'Direct UPI QR'
     ORDER BY mp.id ASC",
    [$tenantId]
);

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Auto-activate any expired plans fallback
SubscriptionEngine::activateUpcomingSubscriptions($tenantId);

$sql = "SELECT m.*, 
        DATE_ADD(m.paid_date, INTERVAL CAST(m.plan AS UNSIGNED) MONTH) as expiry_date,
        DATEDIFF(DATE_ADD(m.paid_date, INTERVAL CAST(m.plan AS UNSIGNED) MONTH), CURDATE()) as days_remaining 
        FROM members m 
        WHERE m.tenant_id = ?";
$params = [$tenantId];

if (!empty($search)) {
    $sql .= " AND (m.fullname LIKE ? OR m.contact LIKE ? OR m.services LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}

if (!empty($statusFilter) && $statusFilter !== 'Upcoming') {
    $sql .= " AND m.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY days_remaining ASC, m.user_id DESC";
$members = DB::fetchAll($sql, $params);

// Fetch all Upcoming Subscriptions Queue for this tenant
$allUpcomingSubs = DB::fetchAll(
    "SELECT s.*, m.fullname, m.contact, m.user_id
     FROM member_subscriptions s
     JOIN members m ON s.member_id = m.user_id AND s.tenant_id = m.tenant_id
     WHERE s.tenant_id = ? AND s.status = 'upcoming'
     ORDER BY s.start_date ASC, s.queue_position ASC",
    [$tenantId]
);

// Group upcoming subscriptions by member_id for quick row lookup
$upcomingByMember = [];
foreach ($allUpcomingSubs as $ups) {
    $upcomingByMember[$ups['member_id']][] = $ups;
}

// Financial summary
// Revenue comes from paid invoices (members.amount only holds each member's latest fee)
$totalCollected = (float)DB::fetchValue("SELECT SUM(paid_amount) FROM invoices WHERE tenant_id = ? AND LOWER(status) IN ('paid','partial')", [$tenantId]);
$thisMonthCollected = (float)DB::fetchValue("SELECT SUM(paid_amount) FROM invoices WHERE tenant_id = ? AND LOWER(status) IN ('paid','partial') AND payment_date >= ? AND payment_date < ?", [$tenantId, date('Y-m-01'), date('Y-m-01', strtotime('first day of next month'))]);
$upcomingTotalAmount = (float)DB::fetchValue("SELECT SUM(plan_price_snapshot) FROM member_subscriptions WHERE tenant_id = ? AND status = 'upcoming'", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Revenue Summary Row -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 20px;">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>This Month Collection</h3>
            <div class="stat-value"><?php echo format_currency($thisMonthCollected); ?></div>
            <div class="stat-meta"><?php echo date('F Y'); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Total All-Time Revenue</h3>
            <div class="stat-value"><?php echo format_currency($totalCollected); ?></div>
            <div class="stat-meta">Lifetime member payments</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-vault"></i>
        </div>
    </div>

    <?php if ($statusFilter === 'Upcoming'): ?>
    <div class="stat-card" style="border-left: 4px solid var(--primary); background: rgba(99, 102, 241, 0.05);">
        <div class="stat-info">
            <h3>Upcoming Queue Revenue</h3>
            <div class="stat-value" style="color: var(--primary);"><?php echo format_currency($upcomingTotalAmount); ?></div>
            <div class="stat-meta"><?php echo count($allUpcomingSubs); ?> Advance Renewals Queued</div>
        </div>
        <div class="stat-icon" style="color: var(--primary);">
            <i class="fas fa-clock"></i>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($pendingUpiPayments)): ?>
<!-- Member UPI renewals awaiting verification -->
<div class="card" style="border-left: 4px solid #f59e0b;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-hourglass-half"></i>
            <span>UPI Renewals Awaiting Verification (<?php echo count($pendingUpiPayments); ?>)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Plan</th>
                        <th>Amount Due</th>
                        <th>UTR / Reference</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUpiPayments as $pu): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-main);"><?php echo e($pu['fullname']); ?></strong>
                                <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($pu['contact']); ?></div>
                            </td>
                            <td><?php echo e($pu['plan_name'] ?: 'Membership'); ?> (<?php echo (int)($pu['plan_months'] ?? 0) ?: '?'; ?> Mo)</td>
                            <td><strong><?php echo format_currency($pu['amount']); ?></strong></td>
                            <td><code><?php echo e($pu['cashfree_payment_id']); ?></code></td>
                            <td><?php echo format_date($pu['created_at']); ?></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode('Confirm you received ' . format_currency($pu['amount']) . ' with UTR ' . $pu['cashfree_payment_id'] . '?')); ?>);">
                                        <?php echo Auth::csrfField(); ?>
                                        <input type="hidden" name="membership_payment_id" value="<?php echo (int)$pu['id']; ?>" />
                                        <button type="submit" name="approve_upi" value="1" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Reject this payment submission?');">
                                        <?php echo Auth::csrfField(); ?>
                                        <input type="hidden" name="membership_payment_id" value="<?php echo (int)$pu['id']; ?>" />
                                        <button type="submit" name="reject_upi" value="1" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Member Payment List Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>
                <?php echo ($statusFilter === 'Upcoming') ? 'Upcoming Queued Membership Plans' : 'Membership Payment Status & Expiry List'; ?> 
                (<?php echo ($statusFilter === 'Upcoming') ? count($allUpcomingSubs) : count($members); ?>)
            </span>
        </div>
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search member..." class="form-control" style="width: 200px;" />
            <select name="status" class="form-select" style="width: 160px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Expired" <?php echo $statusFilter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                <option value="Upcoming" <?php echo $statusFilter === 'Upcoming' ? 'selected' : ''; ?>>Upcoming Queue</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <?php if ($statusFilter === 'Upcoming'): ?>
                <!-- Dedicated Upcoming Queue Table View -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>Queue #</th>
                            <th>Member Name</th>
                            <th>Upcoming Service Plan</th>
                            <th>Duration</th>
                            <th>Paid Fee</th>
                            <th>Scheduled Start Date</th>
                            <th>Scheduled Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allUpcomingSubs)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No upcoming queued memberships scheduled at this moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allUpcomingSubs as $uSub): ?>
                                <tr>
                                    <td>
                                        <span class="status-badge badge-info" style="font-weight: 800;">
                                            #<?php echo $uSub['queue_position']; ?> Queue
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-main);"><?php echo e($uSub['fullname']); ?></strong>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($uSub['contact']); ?></div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary);"><?php echo e($uSub['plan_name_snapshot']); ?></strong>
                                    </td>
                                    <td><?php echo $uSub['plan_duration_snapshot']; ?> Month(s)</td>
                                    <td>
                                        <strong style="color: var(--success-color);"><?php echo format_currency($uSub['plan_price_snapshot']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo format_date($uSub['start_date']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo format_date($uSub['expiry_date']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-info">
                                            <i class="fas fa-clock"></i> Upcoming
                                        </span>
                                    </td>
                                    <td>
                                        <a href="user-payment.php?id=<?php echo $uSub['user_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Member
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- Standard Member Payment Table View -->
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member Name</th>
                            <th>Service / Plan</th>
                            <th>Last Payment</th>
                            <th>Expiry Date</th>
                            <th>Countdown</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No member payment records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $idx => $m): ?>
                                <?php 
                                $days = (int)$m['days_remaining'];
                                $countdownBadge = '';
                                if ($m['status'] === 'Expired' || $days < 0) {
                                    $countdownBadge = '<span class="status-badge badge-danger">Expired (' . abs($days) . 'd ago)</span>';
                                } elseif ($days <= 7) {
                                    $countdownBadge = '<span class="status-badge badge-warning">Expires in ' . $days . 'd</span>';
                                } else {
                                    $countdownBadge = '<span class="status-badge badge-success">' . $days . ' days left</span>';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td>
                                        <strong style="color: var(--text-main);"><?php echo e($m['fullname']); ?></strong>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($m['contact']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($m['services']); ?></div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo $m['plan']; ?> Month(s)</div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary);"><?php echo format_currency($m['amount']); ?></strong>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo format_date($m['paid_date']); ?></div>
                                    </td>
                                    <td><?php echo format_date($m['expiry_date']); ?></td>
                                    <td><?php echo $countdownBadge; ?></td>
                                    <td><?php echo status_badge($m['status']); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <a href="user-payment.php?id=<?php echo $m['user_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-redo"></i> Renew / Pay
                                            </a>
                                            <?php $targetMemId = !empty($m['user_id']) ? $m['user_id'] : (!empty($m['id']) ? $m['id'] : 0); ?>
                                            <a href="userpay.php?member_id=<?php echo $targetMemId; ?>" class="btn btn-secondary btn-sm" title="View & Print Invoice">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
