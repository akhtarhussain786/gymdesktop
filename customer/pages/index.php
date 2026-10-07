<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_dashboard';
$memberId = $_SESSION['user_id'];
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member && !empty($_SESSION['username'])) {
    $member = DB::fetchOne("SELECT * FROM members WHERE LOWER(username) = LOWER(?) AND tenant_id = ?", [$_SESSION['username'], $tenantId]);
    if ($member) {
        $_SESSION['user_id'] = (int)$member['user_id'];
        $memberId = $_SESSION['user_id'];
    }
}
if (!$member) {
    Auth::logout();
    redirect(base_url('/index2'), 'error', 'Member record not found.');
}

$pageTitle = 'Welcome back, ' . $member['fullname'] . '!';
$pageSubtitle = 'Your personal fitness portal, workouts, attendance, and membership status';

// Expiry Countdown
$paidDate = $member['paid_date'];
$planMonths = max(1, (int)$member['plan']);
$expiryDate = !empty($paidDate) ? date('Y-m-d', strtotime("+$planMonths months", strtotime($paidDate))) : date('Y-m-d', strtotime('-1 day'));
// Whole calendar days (time()-based division truncated toward zero and mis-reported the expiry day)
$daysLeft = (int)round((strtotime($expiryDate) - strtotime(date('Y-m-d'))) / 86400);

// Assigned Workout & Diet
$assignedPlan = DB::fetchOne("SELECT ap.*, wp.name as workout_name, wp.schedule_json, dp.name as diet_name, dp.meals_json 
                              FROM member_assigned_plans ap 
                              LEFT JOIN workout_plans wp ON ap.workout_plan_id = wp.id AND wp.tenant_id = ap.tenant_id 
                              LEFT JOIN diet_plans dp ON ap.diet_plan_id = dp.id AND dp.tenant_id = ap.tenant_id 
                              WHERE ap.member_id = ? AND ap.tenant_id = ? AND ap.status = 'active' 
                              ORDER BY ap.id DESC LIMIT 1", [$memberId, $tenantId]);

// Recent Announcements
$announcements = DB::fetchAll("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC LIMIT 3", [$tenantId]);

// My To-Do items
$todos = DB::fetchAll("SELECT * FROM todo WHERE user_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 5", [$memberId, $tenantId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<!-- Membership Status Alert -->
<?php if ($daysLeft <= 5 && $daysLeft >= 0): ?>
    <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #d97706; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <i class="fas fa-hourglass-half"></i> Your membership expires in <strong><?php echo $daysLeft; ?> day(s)</strong> (<?php echo format_date($expiryDate); ?>).
        </div>
        <span class="status-badge badge-warning">Renewal Due</span>
    </div>
<?php elseif ($daysLeft < 0): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #dc2626; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <i class="fas fa-exclamation-circle"></i> Your membership expired on <strong><?php echo format_date($expiryDate); ?></strong>. Please visit the reception to renew.
        </div>
        <span class="status-badge badge-danger">Expired</span>
    </div>
<?php endif; ?>

<!-- Member Stats Grid -->
<div class="stats-grid">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Membership Plan</h3>
            <div class="stat-value" style="font-size: 1.4rem;"><?php echo e($member['services']); ?></div>
            <div class="stat-meta">Valid until: <?php echo format_date($expiryDate); ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-certificate"></i></div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Total Attendance</h3>
            <div class="stat-value"><?php echo $member['attendance_count']; ?></div>
            <div class="stat-meta">Total gym check-in sessions</div>
        </div>
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Weight Tracking</h3>
            <div class="stat-value"><?php echo $member['curr_weight']; ?> kg</div>
            <div class="stat-meta">Initial: <?php echo $member['ini_weight']; ?> kg (<?php echo e($member['curr_bodytype'] ?: 'Normal'); ?>)</div>
        </div>
        <div class="stat-icon"><i class="fas fa-weight"></i></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Workout & Diet Prescriptions -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-dumbbell"></i>
                    <span>My Assigned Workout & Nutrition Routine</span>
                </div>
                <a href="my-routines.php" class="btn btn-secondary btn-sm">Full Routine View</a>
            </div>
            <div class="card-body">
                <?php if ($assignedPlan): ?>
                    <div style="margin-bottom: 18px;">
                        <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 6px;">
                            <i class="fas fa-running"></i> <?php echo e($assignedPlan['workout_name'] ?: 'Workout Plan'); ?>
                        </h4>
                        <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.6; max-height: 180px; overflow-y: auto;">
                            <?php echo e($assignedPlan['schedule_json'] ?: 'Workout instructions not available.'); ?>
                        </div>
                    </div>

                    <?php if (!empty($assignedPlan['diet_name'])): ?>
                        <div>
                            <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--secondary); margin-bottom: 6px;">
                                <i class="fas fa-apple-alt"></i> <?php echo e($assignedPlan['diet_name']); ?>
                            </h4>
                            <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.6; max-height: 180px; overflow-y: auto;">
                                <?php echo e($assignedPlan['meals_json']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                        <i class="fas fa-running" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No customized workout routine has been assigned to your profile yet. Speak with your gym trainer to get a personalized plan.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: To-Do Checklist & Announcements -->
    <div>
        <!-- My Workout Checklist -->
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="font-size: 0.95rem;">
                    <i class="fas fa-tasks"></i>
                    <span>My Workout To-Do</span>
                </div>
                <a href="to-do.php" class="btn btn-secondary btn-sm"><i class="fas fa-plus"></i></a>
            </div>
            <div class="card-body" style="padding: 14px;">
                <?php if (empty($todos)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 12px 0;">No pending tasks. Stay consistent!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($todos as $td): ?>
                            <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; font-size: 0.88rem;">
                                <span><?php echo e($td['task_desc']); ?></span>
                                <span class="status-badge <?php echo $td['task_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>" style="font-size: 0.72rem;">
                                    <?php echo e($td['task_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gym Announcements -->
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="font-size: 0.95rem;">
                    <i class="fas fa-bullhorn"></i>
                    <span>Gym Updates</span>
                </div>
            </div>
            <div class="card-body" style="padding: 14px;">
                <?php if (empty($announcements)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center;">No announcements today.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($announcements as $an): ?>
                            <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 10px 12px; border-radius: var(--radius-md);">
                                <div style="font-size: 0.72rem; color: var(--primary); font-weight: 700;">
                                    <?php echo format_date($an['date']); ?>
                                </div>
                                <div style="font-size: 0.82rem; color: var(--text-main); margin-top: 2px;">
                                    <?php echo e($an['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
