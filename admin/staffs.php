<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']);

$page = 'staffs';
$pageTitle = 'Staff & Trainer Management';
$pageSubtitle = 'Manage gym employees, trainers, managers, cashiers, and login access';
$tenantId = Tenant::getTenantId();

// Handle Delete Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    $staffUsername = DB::fetchValue("SELECT username FROM staffs WHERE user_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
    DB::delete('staffs', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    if ($staffUsername !== null) {
        DB::delete('users', "tenant_id = ? AND username = ? AND role IN ('staff', 'trainer')", [$tenantId, $staffUsername]);
        DB::query("UPDATE members SET trainer_id = NULL WHERE trainer_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        Auth::auditLog('DELETE_STAFF', "Deleted staff member ID $deleteId");
    }
    redirect('staffs.php', 'success', 'Staff account removed successfully.');
}

// Fetch Staff Quota
$staffQuota = Tenant::checkLimit('staff');

$staffs = DB::fetchAll("SELECT * FROM staffs WHERE tenant_id = ? ORDER BY user_id DESC", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Staff Quota Banner -->
<div style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.92rem;">
        <i class="fas fa-user-shield" style="color: var(--primary);"></i>
        <span>Staff & Trainer Capacity: <strong><?php echo $staffQuota['current']; ?></strong> / <strong><?php echo $staffQuota['max']; ?></strong> accounts</span>
    </div>
    <?php if ($staffQuota['allowed']): ?>
        <a href="staffs-entry.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New Staff Member
        </a>
    <?php else: ?>
        <a href="subscription.php" class="btn btn-danger btn-sm">
            <i class="fas fa-arrow-up"></i> Staff Limit Reached - Upgrade Plan
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-users-cog"></i>
            <span>Staff Members (<?php echo count($staffs); ?>)</span>
        </div>
        <input type="text" placeholder="Filter staff..." data-table-search="#staff-table" class="form-control" style="width: 220px;" />
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="staff-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role / Designation</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Gender</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffs)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No staff or trainers added yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffs as $idx => $s): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><strong style="color: var(--text-main);"><?php echo e($s['fullname']); ?></strong></td>
                                <td><code><?php echo e($s['username']); ?></code></td>
                                <td>
                                    <?php 
                                    $desig = strtolower($s['designation']);
                                    $badgeClass = $desig === 'trainer' ? 'badge-success' : ($desig === 'manager' ? 'badge-info' : 'badge-warning');
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo e($s['designation']); ?></span>
                                </td>
                                <td><?php echo e($s['email']); ?></td>
                                <td><?php echo e($s['contact']); ?></td>
                                <td><?php echo e($s['gender']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="edit-staff-form.php?id=<?php echo $s['user_id']; ?>" class="btn btn-secondary btn-sm" title="Edit Staff">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="staffs.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$s['user_id']; ?>" class="btn btn-secondary btn-sm" title="Delete Staff" onclick="return confirm('Are you sure you want to remove this staff account?')">
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
