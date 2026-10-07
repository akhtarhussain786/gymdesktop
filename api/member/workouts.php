<?php
/**
 * Member Workout Plans & Routine Checklist Endpoint
 * Enforces workout plan subscription feature gating.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate('workouts');
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

// Handle POST to toggle or add workout todo checklist item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'add';

    if ($action === 'toggle' && !empty($input['todo_id'])) {
        $todoId = (int)$input['todo_id'];
        $todo = DB::fetchOne("SELECT * FROM todo WHERE id = ? AND tenant_id = ? AND user_id = ?", [$todoId, $tenantId, $memberId]);
        if (!$todo) {
            ApiResponse::notFound('Task not found.');
        }
        $newStatus = ($todo['task_status'] === 'Completed') ? 'Pending' : 'Completed';
        DB::update('todo', ['task_status' => $newStatus], 'id = ? AND tenant_id = ? AND user_id = ?', [$todoId, $tenantId, $memberId]);
        ApiResponse::success(['id' => $todoId, 'task_status' => $newStatus], 'Task status updated.');
    } elseif ($action === 'add' && trim((string)($input['task_desc'] ?? '')) !== '') {
        $taskDesc = mb_substr(trim((string)$input['task_desc']), 0, 500);
        // Basic spam guard on the checklist
        $todoCount = (int)DB::fetchValue("SELECT COUNT(*) FROM todo WHERE tenant_id = ? AND user_id = ?", [$tenantId, $memberId]);
        if ($todoCount >= 200) {
            ApiResponse::error('Checklist limit reached. Please remove some tasks first.', 422);
        }
        $newId = DB::insert('todo', [
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'task_desc' => $taskDesc,
            'task_status' => 'Pending'
        ]);
        ApiResponse::success(['id' => (int)$newId, 'task_desc' => $taskDesc, 'task_status' => 'Pending'], 'Task added.');
    } else {
        ApiResponse::error("Invalid action. Use 'add' with task_desc or 'toggle' with todo_id.", 422);
    }
}

// Fetch all assigned workout plans
$assignedPlans = DB::fetchAll(
    "SELECT ap.id as assignment_id, ap.assigned_date, ap.notes as trainer_notes, ap.status as assignment_status,
            wp.id as plan_id, wp.name as plan_name, wp.goal, wp.level, wp.description, wp.schedule_json,
            s.fullname as trainer_name, s.contact as trainer_contact
     FROM member_assigned_plans ap
     JOIN workout_plans wp ON ap.workout_plan_id = wp.id AND wp.tenant_id = ap.tenant_id
     LEFT JOIN staffs s ON ap.trainer_id = s.user_id AND s.tenant_id = ap.tenant_id
     WHERE ap.member_id = ? AND ap.tenant_id = ?
     ORDER BY ap.id DESC",
    [$memberId, $tenantId]
);

// If no assigned plan, fetch default gym beginner plan if available
if (empty($assignedPlans)) {
    $defaultPlan = DB::fetchOne("SELECT id as plan_id, name as plan_name, goal, level, description, schedule_json FROM workout_plans WHERE tenant_id = ? ORDER BY id ASC LIMIT 1", [$tenantId]);
    if ($defaultPlan) {
        $assignedPlans[] = [
            'assignment_id' => 0,
            'assigned_date' => date('Y-m-d'),
            'trainer_notes' => 'General gym starter routine',
            'assignment_status' => 'active',
            'plan_id' => (int)$defaultPlan['plan_id'],
            'plan_name' => $defaultPlan['plan_name'],
            'goal' => $defaultPlan['goal'],
            'level' => $defaultPlan['level'],
            'description' => $defaultPlan['description'],
            'schedule_json' => $defaultPlan['schedule_json'],
            'trainer_name' => 'Floor Trainer',
            'trainer_contact' => $tenant['phone']
        ];
    }
}

// Fetch member's workout checklist
$todos = DB::fetchAll(
    "SELECT id, task_desc, task_status FROM todo WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC",
    [$tenantId, $memberId]
);

$response = [
    'has_workout_plan' => !empty($assignedPlans),
    'plans' => $assignedPlans,
    'todos' => array_map(function($t) {
        return [
            'id' => (int)$t['id'],
            'task_desc' => $t['task_desc'],
            'is_completed' => ($t['task_status'] === 'Completed'),
            'status' => $t['task_status']
        ];
    }, $todos)
];

ApiResponse::success($response, 'Workout routine retrieved.');
