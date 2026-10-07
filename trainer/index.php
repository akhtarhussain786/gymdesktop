<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['trainer', 'gym_admin']);

$page = 'trainer_dashboard';
$pageTitle = 'Trainer Dashboard';
$pageSubtitle = 'Manage your assigned trainees, track their body metrics, and prescribe workout/diet routines';
$tenantId = Tenant::getTenantId();
$trainerId = $_SESSION['user_id'];

$isTrainer = (($_SESSION['role'] ?? '') === 'trainer');

// Fetch trainees: trainers only see members explicitly assigned to them; gym admins see all
if ($isTrainer) {
    $trainees = DB::fetchAll("SELECT * FROM members WHERE tenant_id = ? AND trainer_id = ? ORDER BY fullname ASC", [$tenantId, $trainerId]);
} else {
    $trainees = DB::fetchAll("SELECT * FROM members WHERE tenant_id = ? ORDER BY fullname ASC", [$tenantId]);
}

$totalTrainees = count($trainees);
$activeTrainees = count(array_filter($trainees, fn($t) => $t['status'] === 'Active'));

// Assigned Plans count
$workoutCount = (int)DB::fetchValue("SELECT COUNT(*) FROM workout_plans WHERE tenant_id = ?", [$tenantId]);
$dietCount = (int)DB::fetchValue("SELECT COUNT(*) FROM diet_plans WHERE tenant_id = ?", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Trainer Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Assigned Trainees</h3>
            <div class="stat-value"><?php echo $activeTrainees; ?></div>
            <div class="stat-meta">Active gym members under your guidance</div>
        </div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Workout Routines</h3>
            <div class="stat-value"><?php echo $workoutCount; ?></div>
            <div class="stat-meta">Available fitness programs</div>
        </div>
        <div class="stat-icon"><i class="fas fa-running"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Diet Plans</h3>
            <div class="stat-value"><?php echo $dietCount; ?></div>
            <div class="stat-meta">Custom nutrition guides</div>
        </div>
        <div class="stat-icon"><i class="fas fa-apple-alt"></i></div>
    </div>
</div>

<!-- Trainees Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-dumbbell"></i>
            <span>My Trainees & Fitness Progress</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search trainee..." data-table-search="#trainees-table" class="form-control" style="width: 220px;" />
            <a href="<?php echo base_url('/admin/workouts.php'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Assign Workout
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="trainees-table">
                <thead>
                    <tr>
                        <th>Trainee Name</th>
                        <th>Contact</th>
                        <th>Program / Service</th>
                        <th>Initial Weight</th>
                        <th>Current Weight</th>
                        <th>Progress Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trainees)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No members assigned to you yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trainees as $t): ?>
                            <?php $diff = (float)$t['curr_weight'] - (float)$t['ini_weight']; ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--text-main);"><?php echo e($t['fullname']); ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($t['gender']); ?></div>
                                </td>
                                <td><?php echo e($t['contact']); ?></td>
                                <td><span class="status-badge badge-info"><?php echo e($t['services']); ?></span></td>
                                <td><?php echo e($t['ini_weight']); ?> kg</td>
                                <td><strong><?php echo e($t['curr_weight']); ?> kg</strong></td>
                                <td>
                                    <span style="font-weight: 700; color: <?php echo $diff >= 0 ? 'var(--secondary)' : 'var(--danger)'; ?>;">
                                        <?php echo ($diff > 0 ? '+' : '') . $diff; ?> kg
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <?php if (!$isTrainer): ?>
                                        <a href="<?php echo base_url('/admin/edit-memberform.php?id=' . (int)$t['user_id']); ?>" class="btn btn-secondary btn-sm" title="Update Weight & Metrics">
                                            <i class="fas fa-weight"></i> Update
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?php echo base_url('/admin/view-member-report.php?id=' . (int)$t['user_id']); ?>" class="btn btn-secondary btn-sm" title="View Full Report">
                                            <i class="fas fa-eye"></i>
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
