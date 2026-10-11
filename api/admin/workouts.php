<?php
/**
 * Gym Admin Workout Plans & Member Assignment API
 * List, Create, and Assign Workout Plans matching admin/workouts.php
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $memberId = !empty($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

    // Plans list
    $plans = DB::fetchAll(
        "SELECT id, name, goal, level, description, schedule_json, created_at 
         FROM workout_plans 
         WHERE tenant_id = ? 
         ORDER BY id DESC",
        [$tenantId]
    );

    // If specific member requested, get assigned plan
    $assigned = null;
    if ($memberId > 0) {
        $assigned = DB::fetchOne(
            "SELECT map.*, wp.name, wp.goal, wp.level, wp.schedule_json
             FROM member_assigned_plans map
             JOIN workout_plans wp ON map.workout_plan_id = wp.id AND wp.tenant_id = map.tenant_id
             WHERE map.tenant_id = ? AND map.member_id = ? AND map.status = 'active'
             ORDER BY map.id DESC LIMIT 1",
            [$tenantId, $memberId]
        );
    }

    // The app reads 'workout_plans' with the routine text in 'days_json' (stored as schedule_json)
    $appPlans = array_map(function ($p) {
        return array_merge($p, [
            'days_json' => (string)($p['schedule_json'] ?? ''),
            'exercises_json' => ''
        ]);
    }, $plans);

    ApiResponse::success([
        'plans' => $plans,
        'workout_plans' => $appPlans,
        'assigned' => $assigned
    ], 'Workout plans retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = trim($input['action'] ?? 'create');

    if ($action === 'assign') {
        $memberId = (int)($input['member_id'] ?? 0);
        $planId = (int)($input['plan_id'] ?? $input['workout_plan_id'] ?? 0);

        if ($memberId <= 0 || $planId <= 0) {
            ApiResponse::error('Member ID and Plan ID are required for assignment.', 400);
        }

        // Both IDs come from the client: they must belong to this gym
        $memberExists = DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        $planExists = DB::fetchValue("SELECT id FROM workout_plans WHERE id = ? AND tenant_id = ?", [$planId, $tenantId]);
        if (!$memberExists || !$planExists) {
            ApiResponse::error('Invalid member or workout plan selected.', 400);
        }

        // Archive previous active workout plans for this member
        DB::query("UPDATE member_assigned_plans SET status = 'archived' WHERE tenant_id = ? AND member_id = ? AND workout_plan_id IS NOT NULL", [$tenantId, $memberId]);

        $id = DB::insert('member_assigned_plans', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'workout_plan_id' => $planId,
            'trainer_id' => workouts_trainer_ref($auth, $tenantId),
            'assigned_date' => date('Y-m-d'),
            'notes' => trim($input['notes'] ?? 'Assigned by gym admin'),
            'status' => 'active'
        ]);

        if (!$id) {
            ApiResponse::error('Could not assign the workout plan. Please try again.', 500);
        }

        ApiResponse::success(['id' => $id], 'Workout plan assigned to member successfully!');
    }

    if ($action === 'delete') {
        $deleteId = (int)($input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Workout plan ID is required.', 422);
        }
        DB::delete('member_assigned_plans', 'workout_plan_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('workout_plans', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        ApiResponse::success(null, 'Workout plan deleted');
    }

    // Create Workout Plan
    $name = trim($input['name'] ?? '');
    $goal = trim($input['goal'] ?? 'General Fitness');
    $level = trim($input['level'] ?? 'Beginner');
    $description = trim($input['description'] ?? '');
    $schedule = $input['schedule_json'] ?? null;
    if (is_array($schedule)) {
        $schedule = json_encode($schedule, JSON_UNESCAPED_UNICODE);
    }
    // The app sends the routine as days_json (+ exercises_json) instead of schedule_json
    if ($schedule === null || $schedule === '') {
        $parts = [];
        foreach (['days_json', 'exercises_json'] as $key) {
            $val = $input[$key] ?? null;
            if (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $val = trim((string)$val);
            if ($val !== '') {
                $parts[] = $val;
            }
        }
        $schedule = implode("\n\n", $parts);
    }

    if (empty($name)) {
        ApiResponse::error('Workout Plan name is required.', 422);
    }

    $id = DB::insert('workout_plans', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'goal' => $goal,
        'level' => in_array($level, ['Beginner', 'Intermediate', 'Advanced']) ? $level : 'Beginner',
        'description' => $description ?: null,
        'schedule_json' => $schedule ?: null,
        'created_by' => $auth['user_id'] ?? null
    ]);

    if (!$id) {
        ApiResponse::error('Could not save the workout plan. Please try again.', 500);
    }

    ApiResponse::success(['id' => $id], 'Workout plan created successfully!', 201);
}

/**
 * Trainer reference stored on member_assigned_plans: the caller's users.id, same as the web panel stores.
 * Readers (api_trainer_join_sql / api_find_plan_trainer) map a users.id to its staff record via users.staff_id,
 * so storing staffs.user_id here could collide with an unrelated users.id.
 */
function workouts_trainer_ref($auth, $tenantId) {
    return $auth['user_id'] ?? null;
}
