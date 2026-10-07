<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$page = 'diet';
$pageTitle = 'Diet & Nutrition Plans';
$pageSubtitle = 'Create meal plans, macro targets, and assign nutrition guides to gym members';
$tenantId = Tenant::getTenantId();

// Handle New Diet Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_diet'])) {
    Auth::verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $target = trim($_POST['target'] ?? 'High Protein');
    $calories = (int)($_POST['calories'] ?? 2200);
    $description = trim($_POST['description'] ?? '');
    $meals = trim($_POST['meals'] ?? '');

    DB::insert('diet_plans', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'target' => $target,
        'calories' => $calories,
        'description' => $description,
        'meals_json' => $meals,
        'created_by' => $_SESSION['user_id']
    ]);

    Auth::auditLog('ADD_DIET_PLAN', "Created diet plan '$name'");
    redirect('diet.php', 'success', "Diet plan '$name' saved successfully!");
}

// Handle Assign to Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_diet'])) {
    Auth::verifyCsrf();

    $member_id = (int)($_POST['member_id'] ?? 0);
    $diet_plan_id = (int)($_POST['diet_plan_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Both the member and the plan must belong to this gym (IDs come from the client)
    if (!DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$member_id, $tenantId])
        || !DB::fetchValue("SELECT id FROM diet_plans WHERE id = ? AND tenant_id = ?", [$diet_plan_id, $tenantId])) {
        redirect('diet.php', 'error', 'Invalid member or plan selected.');
    }

    DB::insert('member_assigned_plans', [
        'tenant_id' => $tenantId,
        'member_id' => $member_id,
        'diet_plan_id' => $diet_plan_id,
        'trainer_id' => $_SESSION['user_id'],
        'assigned_date' => date('Y-m-d'),
        'notes' => $notes,
        'status' => 'active'
    ]);

    redirect('diet.php', 'success', "Diet plan assigned to member successfully!");
}

// Handle Delete Diet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    DB::delete('diet_plans', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    redirect('diet.php', 'success', 'Diet plan deleted.');
}

$dietPlans = DB::fetchAll("SELECT * FROM diet_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);
$members = DB::fetchAll("SELECT user_id, fullname, contact FROM members WHERE tenant_id = ? AND status = 'Active' ORDER BY fullname ASC", [$tenantId]);

// Pre-populate sample diet templates if empty
if (empty($dietPlans)) {
    DB::insert('diet_plans', [
        'tenant_id' => $tenantId,
        'name' => 'High-Protein Muscle Gain Plan',
        'target' => 'Lean Muscle Bulking',
        'calories' => 2600,
        'description' => 'Clean macro breakdown with 180g protein, 300g carbs, and 65g healthy fats.',
        'meals_json' => "Breakfast (8:00 AM): 4 Whole Eggs + 2 Egg Whites, 100g Oats with Banana & Honey\nMid-Morning Snack (11:00 AM): 1 Scoop Whey Protein + 30g Almonds\nLunch (1:30 PM): 200g Grilled Chicken Breast, 150g Brown Rice, Steamed Broccoli\nPre-Workout (4:30 PM): 2 Slices Whole Grain Toast with Peanut Butter & 1 Apple\nPost-Workout Dinner (7:30 PM): 200g Fish or Paneer, Sweet Potato, Mixed Green Salad",
        'created_by' => $_SESSION['user_id']
    ]);
    $dietPlans = DB::fetchAll("SELECT * FROM diet_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px;">
    <button type="button" class="btn btn-secondary btn-sm" onclick="App.openModal('assign-diet-modal')">
        <i class="fas fa-user-check"></i> Assign Diet to Member
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-diet-modal')">
        <i class="fas fa-plus"></i> Create Diet Plan
    </button>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach ($dietPlans as $dp): ?>
        <div class="card" style="display: flex; flex-direction: column;">
            <div class="card-header">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main);"><?php echo e($dp['name']); ?></h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                        Target: <strong><?php echo e($dp['target']); ?></strong> • <span class="status-badge badge-success"><?php echo $dp['calories']; ?> kcal/day</span>
                    </div>
                </div>
                <form method="POST" action="diet.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$dp['id']; ?>" class="btn-icon" title="Delete" style="color: var(--danger); width: 32px; height: 32px;" onclick="return confirm('Delete this diet plan?')">
                    <i class="fas fa-trash"></i>
                </button></form>
            </div>
            <div class="card-body" style="flex: 1;">
                <?php if (!empty($dp['description'])): ?>
                    <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 14px;"><?php echo e($dp['description']); ?></p>
                <?php endif; ?>

                <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; font-family: monospace; font-size: 0.85rem; color: var(--text-main); white-space: pre-wrap; line-height: 1.6; max-height: 220px; overflow-y: auto;">
                    <?php echo e($dp['meals_json']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Create Diet Modal -->
<div class="modal-backdrop" id="add-diet-modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-apple-alt"></i>
                <span>Build New Nutrition Plan</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-diet-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Diet Plan Title *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ketogenic Fat Loss Diet" required />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Diet Target / Type</label>
                        <input type="text" name="target" class="form-control" placeholder="e.g. High Protein, Keto, Vegan, Weight Loss" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Daily Calorie Target (kcal)</label>
                        <input type="number" name="calories" class="form-control" value="2200" required />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description / Guidelines</label>
                    <input type="text" name="description" class="form-control" placeholder="Hydration rules, macro breakdown, forbidden foods" />
                </div>
                <div class="form-group">
                    <label class="form-label">Meal Breakdown & Timings *</label>
                    <textarea name="meals" class="form-control" rows="8" placeholder="Breakfast (8:00 AM): 4 Eggs, Oatmeal, Fruit...&#10;Lunch (1:00 PM): Chicken/Fish/Tofu, Brown Rice, Vegetables...&#10;Evening Snack (5:00 PM): Protein shake + Nuts...&#10;Dinner (8:00 PM): Light protein + Salad..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-diet-modal')">Cancel</button>
                <button type="submit" name="save_diet" value="1" class="btn btn-primary">Save Diet Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Diet Modal -->
<div class="modal-backdrop" id="assign-diet-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-user-check"></i>
                <span>Assign Diet Plan to Member</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('assign-diet-modal')">
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
                    <label class="form-label">Select Nutrition Plan *</label>
                    <select name="diet_plan_id" class="form-select" required>
                        <?php foreach ($dietPlans as $dp): ?>
                            <option value="<?php echo $dp['id']; ?>"><?php echo e($dp['name']); ?> (<?php echo $dp['calories']; ?> kcal)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Custom Dietary Instructions</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Drink minimum 3.5 liters of water, avoid sugar"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('assign-diet-modal')">Cancel</button>
                <button type="submit" name="assign_diet" value="1" class="btn btn-primary">Assign Diet Plan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
