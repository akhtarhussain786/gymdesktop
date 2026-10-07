<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth('super_admin');

$page = 'super_dashboard';
$pageTitle = 'Platform Control Center';
$pageSubtitle = 'Global SaaS metrics, gym tenant statuses, and subscription health';

// Platform Statistics
$totalTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants");
$activeTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status = 'active'");
$trialTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status = 'trial'");
$suspendedTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status IN ('suspended','inactive')");

$totalPlatformMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members");
$totalPlatformStaff = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs");
$totalRevenuePlatform = (float)DB::fetchValue("SELECT SUM(amount) FROM invoices WHERE status = 'Paid'");

$recentTenants = DB::fetchAll("SELECT t.*, p.name as plan_name, 
                               (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) as member_count 
                               FROM tenants t 
                               LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
                               ORDER BY t.created_at DESC LIMIT 6");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Platform Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Registered Gyms</h3>
            <div class="stat-value"><?php echo $totalTenants; ?></div>
            <div class="stat-meta">
                <span style="color: var(--secondary);"><i class="fas fa-check-circle"></i> <?php echo $activeTenants; ?> Active</span>
                <span style="color: var(--text-light); margin: 0 4px;">•</span>
                <span style="color: var(--accent);"><?php echo $trialTenants; ?> Trial</span>
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-building"></i>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Platform Members</h3>
            <div class="stat-value"><?php echo number_format($totalPlatformMembers); ?></div>
            <div class="stat-meta">Across all gym branches</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Platform Staff</h3>
            <div class="stat-value"><?php echo number_format($totalPlatformStaff); ?></div>
            <div class="stat-meta">Trainers & Administrators</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-user-shield"></i>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Platform Volume</h3>
            <div class="stat-value">₹<?php echo number_format($totalRevenuePlatform, 2); ?></div>
            <div class="stat-meta">Processed payments total</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-rupee-sign"></i>
        </div>
    </div>
</div>

<!-- Tenants List Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-dumbbell"></i>
            <span>Recently Registered Gym Tenants</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Filter gyms..." data-table-search="#tenants-table" class="form-control" style="width: 220px; height: 36px;" />
            <a href="<?php echo base_url('/superadmin/tenants.php'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Manage & Add Gyms
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="tenants-table">
                <thead>
                    <tr>
                        <th>Gym Business</th>
                        <th>Owner Details</th>
                        <th>Subscription Tier</th>
                        <th>Active Members</th>
                        <th>Status</th>
                        <th>Expiry Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTenants)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No gym tenants registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTenants as $t): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo e($t['gym_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($t['slug']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo e($t['owner_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($t['email']); ?> • <?php echo e($t['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-info"><?php echo e($t['plan_name'] ?? 'Pro'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo $t['member_count']; ?></strong> members
                                </td>
                                <td>
                                    <?php echo status_badge($t['status']); ?>
                                </td>
                                <td>
                                    <?php echo format_date($t['subscription_expiry']); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <form method="POST" action="<?php echo base_url('/superadmin/impersonate.php'); ?>" style="display: inline; margin: 0;">
                                        <?php echo Auth::csrfField(); ?>
                                        <input type="hidden" name="tenant_id" value="<?php echo (int)$t['id']; ?>" />
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Log in as Gym Admin">
                                            <i class="fas fa-external-link-alt"></i> Access
                                        </button>
                                    </form>
                                        <a href="<?php echo base_url('/superadmin/tenants.php?edit=' . $t['id']); ?>" class="btn btn-secondary btn-sm" title="Edit Tenant">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
