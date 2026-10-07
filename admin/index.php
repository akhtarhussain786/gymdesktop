<?php
require_once __DIR__ . '/../core/auth.php';
// Auth::requireAuth() sends every unauthorized role here; send trainers/members to their own
// portal instead of bouncing them back into this page (infinite redirect loop).
if (Auth::check()) {
    $roleHome = ['trainer' => '/trainer/index.php', 'member' => '/customer/pages/index.php'];
    $r = $_SESSION['role'] ?? '';
    if (isset($roleHome[$r])) {
        redirect(base_url($roleHome[$r]), 'error', 'You do not have access to that page.');
    }
}
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'dashboard';
$pageTitle = 'Dashboard Overview';
$tenant = Tenant::getCurrent();
$tenantId = Tenant::getTenantId();
$pageSubtitle = 'Real-time metrics and operations for ' . $tenant['gym_name'];

// Impersonation banner check
$isImpersonating = !empty($_SESSION['is_impersonating']);

// Real-time Dashboard KPIs (Tenant-Scoped)
$totalMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ?", [$tenantId]);
// members.status is only flipped to 'Expired' by the subscription engine, so a lapsed plan can still
// read 'Active'. Derive active/expired from the plan end date (paid_date + plan months).
$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$activeMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'Active' AND $planEndSql >= CURDATE()", [$tenantId]);
$expiredMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND (status = 'Expired' OR (status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE())))", [$tenantId]);

// Expiring soon in next 7 days
$expiringSoon = (int)DB::fetchValue("SELECT COUNT(*) FROM members 
                                     WHERE tenant_id = ? AND status = 'Active' 
                                     AND $planEndSql BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)", [$tenantId]);

// Today's attendance
$todayDate = date('Y-m-d');
$todayAttendance = (int)DB::fetchValue("SELECT COUNT(*) FROM attendance WHERE tenant_id = ? AND curr_date = ?", [$tenantId, $todayDate]);

// Financial KPIs (This Month)
$thisMonth = date('Y-m');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
// Revenue = amounts actually collected on paid/partial invoices (pending/cancelled are not income).
// Legacy fallback to members.amount only for tenants with no invoices at all.
if ((int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]) > 0) {
    $monthlyRevenue = (float)DB::fetchValue("SELECT SUM(COALESCE(paid_amount, 0)) FROM invoices WHERE tenant_id = ? AND status IN ('paid', 'partial') AND payment_date BETWEEN ? AND ?", [$tenantId, $monthStart, $monthEnd]);
} else {
    $monthlyRevenue = (float)DB::fetchValue("SELECT SUM(amount) FROM members WHERE tenant_id = ? AND paid_date BETWEEN ? AND ?", [$tenantId, $monthStart, $monthEnd]);
}

$monthlyExpenses = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date LIKE ?", [$tenantId, "$thisMonth%"]);
$equipmentTotal = (float)DB::fetchValue("SELECT SUM(amount * quantity) FROM equipment WHERE tenant_id = ?", [$tenantId]);

$totalStaff = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ?", [$tenantId]);
$totalTrainers = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);

// Service distribution
$serviceStats = DB::fetchAll("SELECT services, COUNT(*) as count FROM members WHERE tenant_id = ? GROUP BY services", [$tenantId]);

// Gender distribution
$genderStats = DB::fetchAll("SELECT gender, COUNT(*) as count FROM members WHERE tenant_id = ? GROUP BY gender", [$tenantId]);

// Recent 6 Members
$recentMembers = DB::fetchAll("SELECT * FROM members WHERE tenant_id = ? ORDER BY dor DESC, user_id DESC LIMIT 6", [$tenantId]);

// Recent Announcements
$announcements = DB::fetchAll("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC LIMIT 4", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<?php if ($isImpersonating): ?>
    <div style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 12px 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-md);">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 600;">
            <i class="fas fa-user-secret" style="font-size: 1.2rem;"></i>
            <span>Super Admin Impersonation Mode: You are viewing <strong><?php echo e($tenant['gym_name']); ?></strong></span>
        </div>
        <form method="POST" action="<?php echo base_url('/superadmin/stop-impersonate.php'); ?>" style="margin: 0;">
            <?php echo Auth::csrfField(); ?>
            <button type="submit" class="btn btn-secondary btn-sm" style="background: white; color: #dc2626; border: none;">
                <i class="fas fa-arrow-left"></i> Exit to Super Admin
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Mobile App Gym Code & Quick Branding Banner -->
<div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15)); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--bg-surface); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
            <?php if (!empty($tenant['logo'])): ?>
                <img src="<?php echo str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo']); ?>" style="width: 100%; height: 100%; object-fit: contain;" />
            <?php else: ?>
                <i class="fas fa-dumbbell" style="color: var(--primary); font-size: 1.2rem;"></i>
            <?php endif; ?>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Member Mobile App Pass Code</div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.25rem; font-weight: 800; color: var(--primary); letter-spacing: 1px; font-family: monospace;">
                    <?php echo e($tenant['gym_code'] ?? 'GYM-' . $tenantId); ?>
                </span>
                <span class="status-badge badge-success" style="font-size: 0.75rem;">Active</span>
            </div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="<?php echo base_url('/admin/settings.php'); ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-camera"></i> Change / Upload Gym Logo
        </a>
    </div>
</div>

<!-- Stats Overview Grid -->
<div class="stats-grid">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Active Members</h3>
            <div class="stat-value"><?php echo $activeMembers; ?></div>
            <div class="stat-meta">
                <span>Total Registered: <strong><?php echo $totalMembers; ?></strong></span>
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-user-check"></i>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Today's Attendance</h3>
            <div class="stat-value"><?php echo $todayAttendance; ?></div>
            <div class="stat-meta">Checked in today</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-clipboard-check"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Expiring Soon</h3>
            <div class="stat-value"><?php echo $expiringSoon; ?></div>
            <div class="stat-meta">Next 7 days renewals</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-hourglass-half"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3>Monthly Revenue</h3>
            <div class="stat-value"><?php echo format_currency($monthlyRevenue); ?></div>
            <div class="stat-meta">This Month's collection</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-wallet"></i>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <a href="member-entry.php" class="btn btn-secondary" style="padding: 14px; justify-content: flex-start;">
        <i class="fas fa-user-plus" style="color: var(--primary);"></i>
        <span>Add Member</span>
    </a>
    <a href="attendance.php" class="btn btn-secondary" style="padding: 14px; justify-content: flex-start;">
        <i class="fas fa-calendar-check" style="color: var(--secondary);"></i>
        <span>Mark Attendance</span>
    </a>
    <a href="payment.php" class="btn btn-secondary" style="padding: 14px; justify-content: flex-start;">
        <i class="fas fa-file-invoice-dollar" style="color: var(--accent);"></i>
        <span>Record Payment</span>
    </a>
    <a href="expenses.php" class="btn btn-secondary" style="padding: 14px; justify-content: flex-start;">
        <i class="fas fa-receipt" style="color: var(--danger);"></i>
        <span>Add Expense</span>
    </a>
    <a href="workouts.php" class="btn btn-secondary" style="padding: 14px; justify-content: flex-start;">
        <i class="fas fa-running" style="color: var(--info);"></i>
        <span>Workout Builder</span>
    </a>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-chart-bar"></i>
                <span>Services Breakdown</span>
            </div>
        </div>
        <div class="card-body">
            <canvas id="serviceChart" style="max-height: 260px;"></canvas>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-venus-mars"></i>
                <span>Gender Ratio</span>
            </div>
        </div>
        <div class="card-body" style="display: flex; align-items: center; justify-content: center;">
            <canvas id="genderChart" style="max-height: 240px;"></canvas>
        </div>
    </div>
</div>

<!-- Tables Row: Recent Members & Gym Announcements -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Recent Members -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-users"></i>
                <span>Recently Joined Members</span>
            </div>
            <a href="members.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Service / Plan</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMembers)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    No members registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentMembers as $m): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e($m['fullname']); ?></div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">Joined: <?php echo format_date($m['dor']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($m['services']); ?></div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo $m['plan']; ?> Month(s)</div>
                                    </td>
                                    <td><?php echo e($m['contact']); ?></td>
                                    <td><?php echo status_badge($m['status']); ?></td>
                                    <td>
                                        <a href="edit-memberform.php?id=<?php echo $m['user_id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i>
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

    <!-- Gym Announcements -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </div>
            <a href="manage-announcement.php" class="btn btn-secondary btn-sm">Manage</a>
        </div>
        <div class="card-body">
            <?php if (empty($announcements)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 20px 0;">No active announcements.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($announcements as $a): ?>
                        <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-md);">
                            <div style="font-size: 0.75rem; color: var(--primary); font-weight: 700; margin-bottom: 4px;">
                                <i class="fas fa-calendar-alt"></i> <?php echo format_date($a['date']); ?>
                            </div>
                            <div style="font-size: 0.88rem; color: var(--text-main); line-height: 1.4;">
                                <?php echo e($a['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Service Chart
    const serviceCtx = document.getElementById('serviceChart').getContext('2d');
    const serviceLabels = <?php echo json_encode(array_column($serviceStats, 'services')); ?>;
    const serviceData = <?php echo json_encode(array_map('intval', array_column($serviceStats, 'count'))); ?>;

    new Chart(serviceCtx, {
        type: 'bar',
        data: {
            labels: serviceLabels.length ? serviceLabels : ['Fitness', 'Cardio', 'Sauna'],
            datasets: [{
                label: 'Enrolled Members',
                data: serviceData.length ? serviceData : [10, 5, 2],
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Gender Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    const genderLabels = <?php echo json_encode(array_column($genderStats, 'gender')); ?>;
    const genderData = <?php echo json_encode(array_map('intval', array_column($genderStats, 'count'))); ?>;

    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: genderLabels.length ? genderLabels : ['Male', 'Female'],
            datasets: [{
                data: genderData.length ? genderData : [65, 35],
                backgroundColor: ['#3b82f6', '#ec4899', '#10b981'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
