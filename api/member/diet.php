<?php
/**
 * Member Diet & Nutrition Plans Endpoint
 * Enforces diet plan subscription feature gating.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate('diet');
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

// Fetch all assigned diet plans
$assignedDiets = DB::fetchAll(
    "SELECT ap.id as assignment_id, ap.assigned_date, ap.notes as trainer_notes, ap.status as assignment_status,
            dp.id as plan_id, dp.name as plan_name, dp.target, dp.calories, dp.description, dp.meals_json,
            s.fullname as nutritionist_name, s.contact as trainer_contact
     FROM member_assigned_plans ap
     JOIN diet_plans dp ON ap.diet_plan_id = dp.id AND dp.tenant_id = ap.tenant_id
     LEFT JOIN staffs s ON ap.trainer_id = s.user_id AND s.tenant_id = ap.tenant_id
     WHERE ap.member_id = ? AND ap.tenant_id = ?
     ORDER BY ap.id DESC",
    [$memberId, $tenantId]
);

// If no assigned diet, check default gym diet
if (empty($assignedDiets)) {
    $defaultDiet = DB::fetchOne("SELECT id as plan_id, name as plan_name, target, calories, description, meals_json FROM diet_plans WHERE tenant_id = ? ORDER BY id ASC LIMIT 1", [$tenantId]);
    if ($defaultDiet) {
        $assignedDiets[] = [
            'assignment_id' => 0,
            'assigned_date' => date('Y-m-d'),
            'trainer_notes' => 'General balanced nutrition guide',
            'assignment_status' => 'active',
            'plan_id' => (int)$defaultDiet['plan_id'],
            'plan_name' => $defaultDiet['plan_name'],
            'target' => $defaultDiet['target'],
            'calories' => (int)$defaultDiet['calories'],
            'description' => $defaultDiet['description'],
            'meals_json' => $defaultDiet['meals_json'],
            'nutritionist_name' => 'Gym Nutritionist',
            'trainer_contact' => $tenant['phone']
        ];
    }
}

$response = [
    'has_diet_plan' => !empty($assignedDiets),
    'diet_plans' => $assignedDiets
];

ApiResponse::success($response, 'Diet & nutrition plans retrieved.');
