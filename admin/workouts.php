<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$page = 'workouts';
$pageTitle = 'Workout Plan Builder';
$pageSubtitle = 'Create tailored exercise routines, splits, and assign workout plans to members';
$tenantId = Tenant::getTenantId();

// Handle New Workout Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workout'])) {
    Auth::verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $goal = trim($_POST['goal'] ?? 'Muscle Building');
    $level = $_POST['level'] ?? 'Beginner';
    $description = trim($_POST['description'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');

    DB::insert('workout_plans', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'goal' => $goal,
        'level' => $level,
        'description' => $description,
        'schedule_json' => $schedule,
        'created_by' => $_SESSION['user_id']
    ]);

    Auth::auditLog('ADD_WORKOUT_PLAN', "Created workout plan '$name'");
    redirect('workouts.php', 'success', "Workout plan '$name' saved successfully!");
}

// Handle Assign to Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_plan'])) {
    Auth::verifyCsrf();

    $member_id = (int)($_POST['member_id'] ?? 0);
    $workout_plan_id = (int)($_POST['workout_plan_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Both the member and the plan must belong to this gym (IDs come from the client)
    if (!DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$member_id, $tenantId])
        || !DB::fetchValue("SELECT id FROM workout_plans WHERE id = ? AND tenant_id = ?", [$workout_plan_id, $tenantId])) {
        redirect('workouts.php', 'error', 'Invalid member or plan selected.');
    }

    DB::insert('member_assigned_plans', [
        'tenant_id' => $tenantId,
        'member_id' => $member_id,
        'workout_plan_id' => $workout_plan_id,
        'trainer_id' => $_SESSION['user_id'],
        'assigned_date' => date('Y-m-d'),
        'notes' => $notes,
        'status' => 'active'
    ]);

    redirect('workouts.php', 'success', "Workout routine assigned to member successfully!");
}

// Handle Delete Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    DB::delete('workout_plans', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    redirect('workouts.php', 'success', 'Workout plan deleted.');
}

$workoutPlans = DB::fetchAll("SELECT * FROM workout_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);
$members = DB::fetchAll("SELECT user_id, fullname, contact FROM members WHERE tenant_id = ? AND status = 'Active' ORDER BY fullname ASC", [$tenantId]);

// Pre-populate standard workout templates if empty
if (empty($workoutPlans)) {
    DB::insert('workout_plans', [
        'tenant_id' => $tenantId,
        'name' => 'Full Body Hypertrophy Split',
        'goal' => 'Muscle Building',
        'level' => 'Intermediate',
        'description' => '3-day full body split focusing on compound lifts and progressive overload.',
        'schedule_json' => "Day 1 (Chest & Triceps): Barbell Bench Press 4x8, Incline Dumbbell Press 3x10, Cable Tricep Pushdown 3x12\nDay 2 (Back & Biceps): Lat Pulldowns 4x10, Barbell Rows 4x8, Bicep Hammer Curls 3x12\nDay 3 (Legs & Shoulders): Barbell Squats 4x8, Romanian Deadlifts 3x10, Overhead Dumbbell Press 4x8",
        'created_by' => $_SESSION['user_id']
    ]);
    $workoutPlans = DB::fetchAll("SELECT * FROM workout_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px;">
    <button type="button" class="btn btn-secondary btn-sm" onclick="App.openModal('assign-modal')">
        <i class="fas fa-user-check"></i> Assign Plan to Member
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-workout-modal')">
        <i class="fas fa-plus"></i> Create Workout Routine
    </button>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach ($workoutPlans as $wp): ?>
        <div class="card" style="display: flex; flex-direction: column;">
            <div class="card-header">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);"><?php echo e($wp['name']); ?></h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                        Goal: <strong><?php echo e($wp['goal']); ?></strong> • Level: <span class="status-badge badge-info"><?php echo e($wp['level']); ?></span>
                    </div>
                </div>
                <form method="POST" action="workouts.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$wp['id']; ?>" class="btn-icon" title="Delete" style="color: var(--danger); width: 32px; height: 32px;" onclick="return confirm('Delete this workout plan?')">
                    <i class="fas fa-trash"></i>
                </button></form>
            </div>
            <div class="card-body" style="flex: 1;">
                <?php if (!empty($wp['description'])): ?>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 14px;"><?php echo e($wp['description']); ?></p>
                <?php endif; ?>

                <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.6; max-height: 220px; overflow-y: auto;">
                    <?php echo e($wp['schedule_json']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Create Workout Modal -->
<div class="modal-backdrop" id="add-workout-modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-dumbbell"></i>
                <span>Build New Workout Plan</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-workout-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Plan Title *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. 5-Day Push/Pull/Legs Split" required />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Primary Fitness Goal</label>
                        <select name="goal" class="form-select">
                            <option value="Muscle Building">Muscle Building / Hypertrophy</option>
                            <option value="Fat Loss & Toning">Fat Loss & Toning</option>
                            <option value="Strength & Power">Strength & Powerlifting</option>
                            <option value="Endurance & Cardio">Cardiovascular Endurance</option>
                            <option value="General Fitness">General Fitness</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Difficulty Level</label>
                        <select name="level" class="form-select">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Plan Overview</label>
                    <input type="text" name="description" class="form-control" placeholder="Short description or instructions" />
                </div>
                <div class="form-group">
                    <label class="form-label">Daily Exercise Schedule & Sets/Reps *</label>
                    <textarea name="schedule" class="form-control" rows="8" placeholder="Day 1 (Chest): Bench Press 4x8, Incline DB Press 3x10...&#10;Day 2 (Back): Lat Pulldowns 4x10, Barbell Row 4x8...&#10;Day 3 (Legs): Squats 4x8, Leg Press 3x12..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-workout-modal')">Cancel</button>
                <button type="submit" name="save_workout" value="1" class="btn btn-primary">Save Workout Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Plan Modal -->
<div class="modal-backdrop" id="assign-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-user-check"></i>
                <span>Assign Workout Routine to Member</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('assign-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Member *</label>
                    <select name="member_id" class="form-select" required>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['user_id']; ?>"><?php echo e($m['fullname']); ?> (<?php echo e($m['contact']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Workout Routine *</label>
                    <select name="workout_plan_id" class="form-select" required>
                        <?php foreach ($workoutPlans as $wp): ?>
                            <option value="<?php echo $wp['id']; ?>"><?php echo e($wp['name']); ?> (<?php echo e($wp['goal']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Custom Trainer Instructions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Focus on form, rest 90s between heavy sets"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('assign-modal')">Cancel</button>
                <button type="submit" name="assign_plan" value="1" class="btn btn-primary">Assign to Member</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
