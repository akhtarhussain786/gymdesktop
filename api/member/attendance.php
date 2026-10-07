<?php
/**
 * Member Attendance History, Check-In & Check-Out Endpoint
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = (int)$auth['tenant_id'];
$memberId = (int)$auth['member_id'];
$userId = (int)($auth['user']['id'] ?? $memberId);

// Middleware has already applied the gym's timezone, so "today" is the gym's local calendar day
$today = date('Y-m-d');
$nowTime = date('H:i:s');

// Handle Check-In / Check-Out Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = strtolower(trim($input['action'] ?? $_GET['action'] ?? 'check_in'));

    // Serialise concurrent check-in/out requests for this member (prevents double check-ins from double taps)
    $lockName = 'att_' . $tenantId . '_' . $memberId;
    DB::fetchValue("SELECT GET_LOCK(?, 5)", [$lockName]);
    register_shutdown_function(function () use ($lockName) {
        DB::fetchValue("SELECT RELEASE_LOCK(?)", [$lockName]);
    });

    // Check today's existing attendance record
    $existing = DB::fetchOne(
        "SELECT * FROM attendance WHERE tenant_id = ? AND user_id = ? AND curr_date = ? ORDER BY id ASC LIMIT 1",
        [$tenantId, $memberId, $today]
    );

    if ($action === 'check_in') {
        // Membership must be valid today to check in
        $window = api_membership_window($tenantId, $member);
        if (!$window['is_active']) {
            ApiResponse::forbidden('Your membership expired on ' . $window['expiry_date'] . '. Please renew your plan to check in.');
        }

        if ($existing) {
            ApiResponse::error("You have already checked in today at {$existing['curr_time']}.", 400, null, [
                'check_in_time' => $existing['curr_time'],
                'check_out_time' => $existing['check_out_time']
            ]);
        }

        // Insert new check-in
        $attRow = [
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'curr_date' => $today,
            'curr_time' => $nowTime,
            'check_out_time' => null,
            'present' => 1
        ];
        if (api_column_exists('attendance', 'branch_id')) {
            $attRow['branch_id'] = (int)($member['branch_id'] ?? 1);
        }
        $insertId = DB::insert('attendance', $attRow);
        if (!$insertId) {
            ApiResponse::error('Unable to record check-in right now. Please try again.', 500);
        }

        // Increment member's lifetime attendance count
        DB::query("UPDATE members SET attendance_count = attendance_count + 1 WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);

        ApiResponse::success([
            'id' => (int)$insertId,
            'date' => $today,
            'check_in_time' => $nowTime,
            'check_out_time' => null,
            'status' => 'Checked In'
        ], "Check-in successful! Have a great workout session.");
    } elseif ($action === 'check_out') {
        if (!$existing) {
            ApiResponse::error("You haven't checked in yet today. Please check in first.", 400);
        }

        if (!empty($existing['check_out_time'])) {
            ApiResponse::error("You have already checked out today at {$existing['check_out_time']}.", 400, null, [
                'check_in_time' => $existing['curr_time'],
                'check_out_time' => $existing['check_out_time']
            ]);
        }

        // Update checkout time
        DB::query(
            "UPDATE attendance SET check_out_time = ? WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$nowTime, $existing['id'], $tenantId, $memberId]
        );

        ApiResponse::success([
            'id' => (int)$existing['id'],
            'date' => $today,
            'check_in_time' => $existing['curr_time'],
            'check_out_time' => $nowTime,
            'status' => 'Checked Out'
        ], "Check-out successful! Rest and recover well.");
    } else {
        ApiResponse::error("Invalid action '{$action}'. Valid actions are 'check_in' or 'check_out'.", 422);
    }
}

// GET Request: Retrieve History & Monthly Summary
$month = (string)($_GET['month'] ?? date('Y-m')); // e.g. 2026-08
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

// Fetch attendance records strictly for this member
$attendances = DB::fetchAll(
    "SELECT id, curr_date, curr_time, check_out_time, present 
     FROM attendance 
     WHERE tenant_id = ? AND user_id = ? 
     ORDER BY curr_date DESC, id DESC",
    [$tenantId, $memberId]
);

$monthRecords = [];
$checkinDates = [];
$totalLifetime = count($attendances);
$totalMonth = 0;

foreach ($attendances as $a) {
    $date = $a['curr_date'];
    $checkinDates[] = $date;
    if (strpos($date, $month) === 0) {
        $totalMonth++;
        $monthRecords[] = [
            'id' => (int)$a['id'],
            'date' => $date,
            'check_in_time' => $a['curr_time'],
            'check_out_time' => $a['check_out_time'] ?: null,
            'status' => 'Present'
        ];
    }
}

// Today status check
$todayRecord = DB::fetchOne(
    "SELECT * FROM attendance WHERE tenant_id = ? AND user_id = ? AND curr_date = ? ORDER BY id ASC LIMIT 1",
    [$tenantId, $memberId, $today]
);

$todayStatus = 'Not Checked In';
if ($todayRecord) {
    $todayStatus = empty($todayRecord['check_out_time']) ? 'Checked In' : 'Completed';
}

$response = [
    'summary' => [
        'total_lifetime_sessions' => max($totalLifetime, (int)($member['attendance_count'] ?? 0)),
        'month_sessions' => $totalMonth,
        'current_month' => $month,
        'today_status' => $todayStatus,
        'today_check_in' => $todayRecord['curr_time'] ?? null,
        'today_check_out' => $todayRecord['check_out_time'] ?? null
    ],
    'checkin_dates' => array_values(array_unique($checkinDates)),
    'history' => array_map(function($a) {
        return [
            'id' => (int)$a['id'],
            'date' => $a['curr_date'],
            'check_in_time' => $a['curr_time'],
            'check_out_time' => $a['check_out_time'] ?: null,
            'status' => 'Present'
        ];
    }, $attendances)
];

ApiResponse::success($response, 'Attendance records retrieved.');
