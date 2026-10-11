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
    $date = !empty($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
    if (!attendance_valid_date($date)) {
        $date = date('Y-m-d');
    }

    // Fetch attendance records for specified date (id order = check-in order; curr_time holds mixed
    // 12h/24h strings on older installs, so it cannot be sorted reliably)
    $records = DB::fetchAll(
        "SELECT a.id, a.user_id as member_id, a.curr_date, a.curr_time, a.check_out_time, a.present,
                m.fullname, m.username, m.contact as phone, m.avatar, m.services, m.status as member_status
         FROM attendance a
         LEFT JOIN members m ON a.user_id = m.user_id AND a.tenant_id = m.tenant_id
         WHERE a.tenant_id = ? AND a.curr_date = ?
         ORDER BY a.id DESC",
        [$tenantId, $date]
    );

    $formatted = [];
    $appRows = [];
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

        $row = [
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
        $formatted[] = $row;

        // The app's attendance screen reads these names (contact / curr_date / curr_time / checkout_time / present)
        $checkedOut = !empty($r['check_out_time']);
        $appRows[] = array_merge($row, [
            'contact' => $r['phone'] ?? '',
            'curr_date' => $r['curr_date'],
            'curr_time' => attendance_display_time($r['curr_time']),
            'checkout_time' => $checkedOut ? attendance_display_time($r['check_out_time']) : null,
            'present' => (string)(int)($r['present'] ?? 1),
            'status' => $checkedOut ? 'Checked Out' : 'Checked In'
        ]);
    }

    ApiResponse::success([
        'date' => $date,
        'total_checkins' => count($formatted),
        'records' => $formatted,
        'attendance' => $appRows
    ], 'Attendance records retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = strtolower(trim((string)($input['action'] ?? 'checkin'))); // 'checkin', 'checkout', 'toggle', 'delete'
    $memberId = (int)($input['member_id'] ?? $input['user_id'] ?? 0);
    $date = !empty($input['date']) ? trim((string)$input['date']) : date('Y-m-d');
    if (!attendance_valid_date($date)) {
        ApiResponse::error('Invalid date. Use YYYY-MM-DD.', 422);
    }
    // curr_time is a TIME column on newer installs: always store 24h H:i:s (12h 'h:i A' lost AM/PM there)
    $time = date('H:i:s');
    if (!empty($input['time'])) {
        $time = attendance_parse_time((string)$input['time']);
        if ($time === null) {
            ApiResponse::error('Invalid time. Use HH:MM (24h) or HH:MM AM/PM.', 422);
        }
    }
    $timeLabel = attendance_display_time($time);

    if ($action === 'delete') {
        $attendanceId = (int)($input['attendance_id'] ?? $input['id'] ?? 0);
        if ($attendanceId <= 0) {
            ApiResponse::error('Attendance record ID is required.', 422);
        }
        DB::delete('attendance', 'id = ? AND tenant_id = ?', [$attendanceId, $tenantId]);
        ApiResponse::success(null, 'Attendance record removed');
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
            // Only an explicit toggle turns a second check-in into a check-out
            if ($action === 'toggle' && empty($existing['check_out_time'])) {
                DB::update('attendance', ['check_out_time' => $time], 'id = ?', [$existing['id']]);
                ApiResponse::success(['attendance_id' => $existing['id'], 'check_out_time' => $time, 'action' => 'checkout'], 'Member checked out at ' . $timeLabel);
            }
            $inLabel = attendance_display_time($existing['curr_time']);
            ApiResponse::error(
                empty($existing['check_out_time'])
                    ? 'Member is already checked in today (since ' . $inLabel . ').'
                    : 'Member already checked in today at ' . $inLabel . ' and has checked out.',
                409,
                null,
                [
                    'attendance_id' => (int)$existing['id'],
                    'check_in_time' => $existing['curr_time'],
                    'check_out_time' => $existing['check_out_time'],
                    'already_checked_in' => true
                ]
            );
        } else {
            // New check-in
            $id = DB::insert('attendance', [
                'tenant_id' => $tenantId,
                'user_id' => $memberId,
                'curr_date' => $date,
                'curr_time' => $time,
                'present' => 1
            ]);

            if (!$id) {
                ApiResponse::error('Could not record the check-in. Please try again.', 500);
            }

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

ApiResponse::error('Method not allowed', 405);

function attendance_valid_date($d) {
    if (!is_string($d) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/**
 * "06:30 PM", "6:30pm", "18:30", "18:30:00" -> "18:30:00"; null when unparseable
 */
function attendance_parse_time($raw) {
    $raw = strtoupper(trim((string)$raw));
    if (!preg_match('/^(\d{1,2})(?::(\d{2}))?(?::(\d{2}))?\s*(A\.?M\.?|P\.?M\.?)?$/', $raw, $m)) return null;
    $h = (int)$m[1];
    $i = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
    $sec = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 0;
    $ampm = isset($m[4]) ? str_replace('.', '', $m[4]) : '';
    if ($i > 59 || $sec > 59) return null;
    if ($ampm !== '') {
        if ($h < 1 || $h > 12) return null;
        if ($ampm === 'AM') $h = ($h === 12) ? 0 : $h;
        else $h = ($h === 12) ? 12 : $h + 12;
    } elseif ($h > 23 || (!isset($m[2]) || $m[2] === '')) {
        return null;
    }
    return sprintf('%02d:%02d:%02d', $h, $i, $sec);
}

/**
 * Stored check-in times are "H:i:s" (TIME column / web) or legacy "h:i A" strings; show them as "h:i A"
 */
function attendance_display_time($t) {
    if ($t === null || $t === '') return $t;
    $parsed = attendance_parse_time((string)$t);
    return $parsed === null ? (string)$t : date('h:i A', strtotime('2000-01-01 ' . $parsed));
}
