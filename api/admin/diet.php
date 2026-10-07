<?php
/**
 * Gym Admin Diet & Nutrition Plans API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST actions
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ApiResponse::error('Invalid diet plan ID', 400);
        }
        $deleted = DB::delete('diet_plans', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($deleted) {
            ApiResponse::success([], 'Diet plan deleted successfully');
        } else {
            ApiResponse::error('Diet plan not found or deletion failed', 404);
        }
    }

    if ($action === 'assign') {
        $memberId = (int)($input['member_id'] ?? 0);
        $dietPlanId = (int)($input['diet_plan_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');

        if ($memberId <= 0 || $dietPlanId <= 0) {
            ApiResponse::error('Please select a valid member and diet plan', 400);
        }

        // Verify tenant isolation
        $memberExists = DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        $planExists = DB::fetchValue("SELECT id FROM diet_plans WHERE id = ? AND tenant_id = ?", [$dietPlanId, $tenantId]);

        if (!$memberExists || !$planExists) {
            ApiResponse::error('Invalid member or diet plan selected', 400);
        }

        DB::insert('member_assigned_plans', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'diet_plan_id' => $dietPlanId,
            'trainer_id' => $auth['user_id'] ?? null,
            'assigned_date' => date('Y-m-d'),
            'notes' => $notes,
            'status' => 'active'
        ]);

        ApiResponse::success([], 'Diet plan assigned to member successfully');
    }

    // Default: Add Diet Plan
    $name = trim($input['name'] ?? '');
    $target = trim($input['target'] ?? 'High Protein');
    $calories = (int)($input['calories'] ?? 2200);
    $description = trim($input['description'] ?? '');
    $meals = trim($input['meals'] ?? '');

    if (empty($name)) {
        ApiResponse::error('Diet plan name is required', 400);
    }

    $newId = DB::insert('diet_plans', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'target' => $target,
        'calories' => $calories,
        'description' => $description,
        'meals_json' => $meals,
        'created_by' => $auth['user_id'] ?? null
    ]);

    Auth::auditLog('ADD_DIET_PLAN', "Created diet plan '$name'");
    ApiResponse::success(['id' => $newId], "Diet plan '$name' saved successfully");
}

// GET: Retrieve diet plans & assigned plans
$dietPlans = DB::fetchAll("SELECT * FROM diet_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);

// Seed template if empty
if (empty($dietPlans)) {
    DB::insert('diet_plans', [
        'tenant_id' => $tenantId,
        'name' => 'High-Protein Muscle Gain Plan',
        'target' => 'Lean Muscle Bulking',
        'calories' => 2600,
        'description' => 'Clean macro breakdown with 180g protein, 300g carbs, and 65g healthy fats.',
        'meals_json' => "Breakfast (8:00 AM): 4 Whole Eggs + 2 Egg Whites, 100g Oats with Banana & Honey\nMid-Morning Snack (11:00 AM): 1 Scoop Whey Protein + 30g Almonds\nLunch (1:30 PM): 200g Grilled Chicken Breast, 150g Brown Rice, Steamed Broccoli\nPre-Workout (4:30 PM): 2 Slices Whole Grain Toast with Peanut Butter & 1 Apple\nPost-Workout Dinner (7:30 PM): 200g Fish or Paneer, Sweet Potato, Mixed Green Salad",
        'created_by' => $auth['user_id'] ?? null
    ]);
    $dietPlans = DB::fetchAll("SELECT * FROM diet_plans WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);
}

$assignedSql = "SELECT ap.*, m.fullname as member_name, dp.name as plan_name 
                FROM member_assigned_plans ap 
                JOIN members m ON ap.member_id = m.user_id 
                JOIN diet_plans dp ON ap.diet_plan_id = dp.id 
                WHERE ap.tenant_id = ? AND ap.diet_plan_id IS NOT NULL 
                ORDER BY ap.id DESC LIMIT 50";
$assignedPlans = DB::fetchAll($assignedSql, [$tenantId]);

$members = DB::fetchAll("SELECT user_id, fullname, contact FROM members WHERE tenant_id = ? AND status = 'Active' ORDER BY fullname ASC", [$tenantId]);

ApiResponse::success([
    'diet_plans' => $dietPlans,
    'assigned_plans' => $assignedPlans,
    'members' => $members
], 'Diet plans retrieved successfully');
