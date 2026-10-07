<?php
/**
 * Gym Admin Attendance Management & Live Scanner API
 * View attendance history, today's checked-in members, mark check-in / check-out by member ID or QR code
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

    // Fetch attendance records for specified date
    $records = DB::fetchAll(
        "SELECT a.id, a.user_id as member_id, a.curr_date, a.curr_time, a.check_out_time, a.present,
                m.fullname, m.username, m.contact as phone, m.avatar, m.services, m.status as member_status
         FROM attendance a
         LEFT JOIN members m ON a.user_id = m.user_id AND a.tenant_id = m.tenant_id
         WHERE a.tenant_id = ? AND a.curr_date = ?
         ORDER BY a.curr_time DESC",
        [$tenantId, $date]
    );

    $formatted = [];
    foreach ($records as $r) {
        $avatarUrl = null;
        if (!empty($r['avatar'])) {
            if (str_starts_with($r['avatar'], 'http')) {
                $avatarUrl = $r['avatar'];
            } elseif (file_exists(__DIR__ . '/../../uploads/avatars/' . $r['avatar'])) {
                $avatarUrl = base_url('/uploads/avatars/' . $r['avatar']);
            } else {
                $avatarUrl = base_url('/img/' . $r['avatar']);
            }
        }

        $formatted[] = [
            'id' => (int)$r['id'],
            'member_id' => (int)$r['member_id'],
            'fullname' => $r['fullname'] ?? 'Member #' . $r['member_id'],
            'username' => $r['username'] ?? '',
            'phone' => $r['phone'] ?? '',
            'avatar' => $avatarUrl,
            'services' => $r['services'] ?? 'General Fitness',
            'date' => $r['curr_date'],
            'check_in_time' => $r['curr_time'],
            'check_out_time' => $r['check_out_time'],
            'is_present' => (int)($r['present'] ?? 1) === 1
        ];
    }

    ApiResponse::success([
        'date' => $date,
        'total_checkins' => count($formatted),
        'records' => $formatted
    ], 'Attendance records retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = trim($input['action'] ?? 'checkin'); // 'checkin', 'checkout', 'toggle', 'delete'
    $memberId = (int)($input['member_id'] ?? $input['user_id'] ?? 0);
    $date = !empty($input['date']) ? trim($input['date']) : date('Y-m-d');
    $time = !empty($input['time']) ? trim($input['time']) : date('h:i A');

    if ($action === 'delete') {
        $attendanceId = (int)($input['attendance_id'] ?? $input['id'] ?? 0);
        if ($attendanceId > 0) {
            DB::delete('attendance', 'id = ? AND tenant_id = ?', [$attendanceId, $tenantId]);
            ApiResponse::success(null, 'Attendance record removed');
        }
    }

    if ($memberId <= 0) {
        ApiResponse::error('Member ID is required', 400);
    }

    $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
    if (!$member) {
        ApiResponse::notFound('Member record not found in this gym.');
    }

    // Check existing attendance for today
    $existing = DB::fetchOne("SELECT * FROM attendance WHERE user_id = ? AND curr_date = ? AND tenant_id = ? LIMIT 1", [$memberId, $date, $tenantId]);

    if ($action === 'checkout') {
        if ($existing) {
            DB::update('attendance', ['check_out_time' => $time], 'id = ?', [$existing['id']]);
            ApiResponse::success(['attendance_id' => $existing['id'], 'check_out_time' => $time], 'Member checked out successfully!');
        } else {
            ApiResponse::error('No check-in record found for today to check out.', 400);
        }
    } else {
        // Check in
        if ($existing) {
            // Already checked in today, update check-out or toggle
            if (empty($existing['check_out_time'])) {
                DB::update('attendance', ['check_out_time' => $time], 'id = ?', [$existing['id']]);
                ApiResponse::success(['attendance_id' => $existing['id'], 'check_out_time' => $time, 'action' => 'checkout'], 'Member checked out at ' . $time);
            } else {
                ApiResponse::success(['attendance_id' => $existing['id'], 'check_in_time' => $existing['curr_time']], 'Member already checked in today at ' . $existing['curr_time']);
            }
        } else {
            // New check-in
            $id = DB::insert('attendance', [
                'tenant_id' => $tenantId,
                'user_id' => $memberId,
                'curr_date' => $date,
                'curr_time' => $time,
                'present' => 1
            ]);

            // Increment member attendance_count
            DB::query("UPDATE members SET attendance_count = attendance_count + 1 WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);

            ApiResponse::success([
                'attendance_id' => $id,
                'member_id' => $memberId,
                'fullname' => $member['fullname'],
                'check_in_time' => $time,
                'action' => 'checkin'
            ], 'Check-in recorded for ' . $member['fullname'] . '!', 201);
        }
    }
}
