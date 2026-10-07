<?php
/**
 * Gym Admin Classes & Group Schedules API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST: Add or Delete
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ApiResponse::error('Invalid class ID for deletion', 400);
        }
        if (DB::delete('classes', 'id = ? AND tenant_id = ?', [$id, $tenantId])) {
            DB::delete('class_bookings', 'class_id = ? AND tenant_id = ?', [$id, $tenantId]);
            ApiResponse::success([], 'Class removed from schedule');
        } else {
            ApiResponse::error('Class not found or could not be deleted', 404);
        }
    }

    // Default: Add Class
    $title = trim($input['title'] ?? '');
    $trainer_id = !empty($input['trainer_id']) ? (int)$input['trainer_id'] : null;
    $day_of_week = $input['day_of_week'] ?? 'Monday';
    $start_time = $input['start_time'] ?? '06:00:00';
    $end_time = $input['end_time'] ?? '07:00:00';
    $capacity = (int)($input['capacity'] ?? 20);
    $room = trim($input['room'] ?? 'Main Studio');

    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'];
    if (!in_array($day_of_week, $validDays, true)) $day_of_week = 'Monday';

    if ($trainer_id !== null && !DB::fetchValue("SELECT user_id FROM staffs WHERE user_id = ? AND tenant_id = ?", [$trainer_id, $tenantId])) {
        $trainer_id = null;
    }

    if ($title === '' || $capacity < 1) {
        ApiResponse::error('Class title and a positive capacity are required', 400);
    }

    $newId = DB::insert('classes', [
        'tenant_id' => $tenantId,
        'branch_id' => 1,
        'title' => $title,
        'trainer_id' => $trainer_id,
        'day_of_week' => $day_of_week,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'capacity' => $capacity,
        'room' => $room,
        'status' => 'active'
    ]);

    Auth::auditLog('ADD_CLASS', "Scheduled fitness class '$title' on $day_of_week");
    ApiResponse::success(['id' => $newId], "Fitness class '$title' added to schedule");
}

// GET: Retrieve classes & trainers
$classes = DB::fetchAll("SELECT c.*, s.fullname as instructor_name 
                         FROM classes c 
                         LEFT JOIN staffs s ON c.trainer_id = s.user_id 
                         WHERE c.tenant_id = ? 
                         ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'), c.start_time ASC", [$tenantId]);

$trainers = DB::fetchAll("SELECT user_id, fullname, designation FROM staffs WHERE tenant_id = ? AND designation IN ('Trainer', 'Gym Admin', 'Staff')", [$tenantId]);

// Populate sample schedules if empty
if (empty($classes)) {
    DB::insert('classes', [
        'tenant_id' => $tenantId,
        'title' => 'Morning HIIT & Cardio Blast',
        'trainer_id' => !empty($trainers[0]['user_id']) ? $trainers[0]['user_id'] : null,
        'day_of_week' => 'Monday',
        'start_time' => '06:30:00',
        'end_time' => '07:30:00',
        'capacity' => 25,
        'room' => 'Studio A',
        'status' => 'active'
    ]);
    DB::insert('classes', [
        'tenant_id' => $tenantId,
        'title' => 'Power Yoga & Flexibility',
        'trainer_id' => !empty($trainers[0]['user_id']) ? $trainers[0]['user_id'] : null,
        'day_of_week' => 'Wednesday',
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'capacity' => 20,
        'room' => 'Zen Studio',
        'status' => 'active'
    ]);
    $classes = DB::fetchAll("SELECT c.*, s.fullname as instructor_name 
                             FROM classes c 
                             LEFT JOIN staffs s ON c.trainer_id = s.user_id 
                             WHERE c.tenant_id = ? 
                             ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'), c.start_time ASC", [$tenantId]);
}

ApiResponse::success([
    'classes' => $classes,
    'trainers' => $trainers
], 'Classes retrieved successfully');
