<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_plans';
$pageTitle = 'My Workout & Diet Routines';
$pageSubtitle = 'Customized fitness regimen and daily meal guides prescribed by your trainer';
$memberId = $_SESSION['user_id'];
$tenantId = Tenant::getTenantId();

$assignedPlans = DB::fetchAll("SELECT ap.*, wp.name as workout_name, wp.goal as workout_goal, wp.level as workout_level, wp.schedule_json, 
                               dp.name as diet_name, dp.target as diet_target, dp.calories as diet_calories, dp.meals_json, 
                               s.fullname as trainer_name 
                               FROM member_assigned_plans ap 
                               LEFT JOIN workout_plans wp ON ap.workout_plan_id = wp.id AND wp.tenant_id = ap.tenant_id 
                               LEFT JOIN diet_plans dp ON ap.diet_plan_id = dp.id AND dp.tenant_id = ap.tenant_id 
                               LEFT JOIN staffs s ON ap.trainer_id = s.user_id AND s.tenant_id = ap.tenant_id 
                               WHERE ap.member_id = ? AND ap.tenant_id = ? 
                               ORDER BY ap.id DESC", [$memberId, $tenantId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<?php if (empty($assignedPlans)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-dumbbell" style="font-size: 3rem; color: var(--primary); margin-bottom: 16px; opacity: 0.5;"></i>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">No Plans Assigned Yet</h3>
        <p style="color: var(--text-muted); max-width: 480px; margin: 8px auto 0;">Your personal trainer has not attached a customized workout or diet plan to your profile yet. Please ask your trainer on your next gym session.</p>
    </div>
<?php else: ?>
    <?php foreach ($assignedPlans as $plan): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <?php if (!empty($plan['workout_name'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-running"></i>
                            <span><?php echo e($plan['workout_name']); ?></span>
                        </div>
                        <span class="status-badge badge-info"><?php echo e($plan['workout_level']); ?></span>
                    </div>
                    <div class="card-body">
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                            Goal: <strong><?php echo e($plan['workout_goal']); ?></strong> • Assigned by: <strong><?php echo e($plan['trainer_name'] ?: 'Gym Trainer'); ?></strong>
                        </div>
                        <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; font-family: monospace; font-size: 0.88rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.7;">
                            <?php echo e($plan['schedule_json']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($plan['diet_name'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-apple-alt"></i>
                            <span><?php echo e($plan['diet_name']); ?></span>
                        </div>
                        <span class="status-badge badge-success"><?php echo $plan['diet_calories']; ?> kcal</span>
                    </div>
                    <div class="card-body">
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                            Target: <strong><?php echo e($plan['diet_target']); ?></strong> • Assigned on: <strong><?php echo format_date($plan['assigned_date']); ?></strong>
                        </div>
                        <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; font-family: monospace; font-size: 0.88rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.7;">
                            <?php echo e($plan['meals_json']); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
