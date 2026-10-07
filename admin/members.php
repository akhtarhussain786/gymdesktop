<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']); // trainers use trainer/index.php (their trainees only)

$page = 'members';
$pageTitle = 'Members Directory';
$pageSubtitle = 'Manage gym members, active plans, renewals, and profiles';
$tenantId = Tenant::getTenantId();

// Handle Delete Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    Auth::requireAuth(['gym_admin']);
    $deleteId = (int)$_POST['delete'];
    // Remove the member's login + session artefacts so a deleted member can no longer sign in
    // (users mirror row, mobile API tokens) and drop operational child rows. Invoices and
    // payment records are intentionally KEPT for financial/audit history; pending/queued
    // subscriptions are cancelled rather than deleted.
    if (DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$deleteId, $tenantId])) {
        DB::delete('users', "member_id = ? AND tenant_id = ? AND role = 'member'", [$deleteId, $tenantId]);
        DB::delete('member_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('device_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('attendance', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('member_assigned_plans', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('class_bookings', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('todo', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::query("UPDATE member_subscriptions SET status = 'cancelled' WHERE member_id = ? AND tenant_id = ? AND status IN ('active', 'upcoming', 'pending_payment')", [$deleteId, $tenantId]);
        DB::delete('members', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        Auth::auditLog('DELETE_MEMBER', "Deleted member ID " . $deleteId);
    }
    redirect('members.php', 'success', 'Member record deleted successfully.');
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Effective status: a plan past its end date (paid_date + plan months) is Expired even if the
// stored status still says 'Active' (it is only flipped by the subscription engine).
$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE status END";
$sql = "SELECT *, $effStatusSql AS effective_status FROM members WHERE tenant_id = ?";
$params = [$tenantId];

if (!empty($search)) {
    $sql .= " AND (fullname LIKE ? OR username LIKE ? OR contact LIKE ? OR address LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND $effStatusSql = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY dor DESC, user_id DESC";
$members = DB::fetchAll($sql, $params);

// Quota Check
$quota = Tenant::checkLimit('members');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Quota Banner -->
<div style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.92rem;">
        <i class="fas fa-users" style="color: var(--primary);"></i>
        <span>Member Capacity: <strong><?php echo $quota['current']; ?></strong> / <strong><?php echo $quota['max']; ?></strong> active members</span>
    </div>
    <?php if ($quota['allowed']): ?>
        <a href="member-entry.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add New Member
        </a>
    <?php else: ?>
        <a href="subscription.php" class="btn btn-danger btn-sm">
            <i class="fas fa-arrow-up"></i> Member Limit Reached - Upgrade Plan
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-users"></i>
            <span>Members List (<?php echo count($members); ?>)</span>
        </div>
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search member name, phone..." class="form-control" style="width: 220px;" />
            <select name="status" class="form-select" style="width: 140px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Expired" <?php echo $statusFilter === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($search || $statusFilter): ?>
                <a href="members.php" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member Details</th>
                        <th>Service / Plan</th>
                        <th>Payment / Fee</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Attendance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No members found matching your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $idx => $m): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo e($m['fullname']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        <i class="fas fa-phone" style="font-size: 0.75rem;"></i> <?php echo e($m['contact']); ?> • <?php echo e($m['gender']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><strong><?php echo e($m['services']); ?></strong></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($m['plan']); ?> Month(s)</div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--primary);"><?php echo format_currency($m['amount']); ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">Paid: <?php echo format_date($m['paid_date']); ?></div>
                                </td>
                                <td><?php echo format_date($m['dor']); ?></td>
                                <td><?php echo status_badge($m['effective_status'] ?? $m['status']); ?></td>
                                <td>
                                    <span class="status-badge badge-info"><i class="fas fa-check"></i> <?php echo $m['attendance_count']; ?> days</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="user-payment.php?id=<?php echo $m['user_id']; ?>" class="btn btn-secondary btn-sm" title="Payment / Renew">
                                            <i class="fas fa-dollar-sign" style="color: var(--secondary);"></i>
                                        </a>
                                        <a href="edit-memberform.php?id=<?php echo $m['user_id']; ?>" class="btn btn-secondary btn-sm" title="Edit Member Profile">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view-member-report.php?id=<?php echo $m['user_id']; ?>" class="btn btn-secondary btn-sm" title="View Progress / ID Card">
                                            <i class="fas fa-id-card"></i>
                                        </a>
                                        <form method="POST" action="members.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$m['user_id']; ?>" class="btn btn-secondary btn-sm" title="Delete Member" onclick="return confirm('Are you sure you want to delete this member?')">
                                            <i class="fas fa-trash" style="color: var(--danger);"></i>
                                        </button></form>
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
