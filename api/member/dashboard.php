<?php
/**
 * Member Dashboard Endpoint (Consolidated, Tenant-Isolated & Resilient)
 * Returns all necessary data for the rich Member Dashboard in a single secure request.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = (int)$auth['tenant_id'];
$memberId = (int)$auth['member_id'];

// 1. Membership Expiry & Calculations
// Prefers the renewal engine's member_subscriptions window; falls back to paid_date (period start) + plan months.
// Expiry date is inclusive (last valid day), counted in the gym's local calendar.
$window = api_membership_window($tenantId, $member);
$paidDate = $window['start_date'];
$planMonths = $window['plan_months'];
$expiryDate = $window['expiry_date'];
$today = date('Y-m-d');
$daysLeft = $window['days_left'];

// 2. Today's Attendance Check
$todayAttendance = null;
try {
    $todayAttendance = DB::fetchOne(
        "SELECT * FROM attendance WHERE tenant_id = ? AND user_id = ? AND curr_date = ? ORDER BY id ASC LIMIT 1",
        [$tenantId, $memberId, $today]
    );
} catch (Throwable $e) {}

// 3. Outstanding Payments & Invoices
$invoices = [];
try {
    $invoices = DB::fetchAll(
        "SELECT * FROM invoices WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC",
        [$tenantId, $memberId]
    ) ?: [];
} catch (Throwable $e) {}

$totalPaid = 0.0;
$totalDue = 0.0;
if (is_array($invoices)) {
    foreach ($invoices as $inv) {
        $paidAmt = (float)($inv['paid_amount'] ?? 0);
        $totalPaid += $paidAmt;
        $amt = (float)($inv['amount'] ?? $inv['total_amount'] ?? 0);
        $status = strtolower((string)($inv['status'] ?? ''));
        if ($status === 'unpaid' || $status === 'partial') {
            $disc = (float)($inv['discount'] ?? 0);
            $totalDue += max(0.0, $amt - $paidAmt - $disc);
        }
    }
}

// 4. Assigned Workout & Diet Routine
$assignedPlan = null;
try {
    $assignedPlan = DB::fetchOne(
        "SELECT ap.*, 
                wp.name as workout_name, wp.goal as workout_goal, wp.level as workout_level, wp.schedule_json, 
                dp.name as diet_name, dp.target as diet_target, dp.calories as diet_calories, dp.meals_json, 
                s.fullname as trainer_name, s.designation as trainer_designation, s.contact as trainer_contact, s.email as trainer_email
         FROM member_assigned_plans ap 
         LEFT JOIN workout_plans wp ON ap.workout_plan_id = wp.id AND wp.tenant_id = ap.tenant_id 
         LEFT JOIN diet_plans dp ON ap.diet_plan_id = dp.id AND dp.tenant_id = ap.tenant_id 
         LEFT JOIN staffs s ON ap.trainer_id = s.user_id AND s.tenant_id = ap.tenant_id 
         WHERE ap.member_id = ? AND ap.tenant_id = ? AND ap.status = 'active' 
         ORDER BY ap.id DESC LIMIT 1",
        [$memberId, $tenantId]
    );
} catch (Throwable $e) {}

// Fallback to direct workout_plans / diet_plans / trainers tables
$workoutData = null;
$dietData = null;
$trainerData = null;

if ($assignedPlan) {
    if (!empty($assignedPlan['workout_name'])) {
        $workoutData = [
            'name' => (string)$assignedPlan['workout_name'],
            'goal' => (string)($assignedPlan['workout_goal'] ?: 'Fitness'),
            'level' => (string)($assignedPlan['workout_level'] ?: 'All Levels'),
            'schedule' => $assignedPlan['schedule_json'] ?? null,
            'trainer_name' => (string)($assignedPlan['trainer_name'] ?: 'Personal Trainer')
        ];
    }
    if (!empty($assignedPlan['diet_name'])) {
        $dietData = [
            'name' => (string)$assignedPlan['diet_name'],
            'target' => (string)($assignedPlan['diet_target'] ?: 'Nutrition Goal'),
            'calories' => (int)($assignedPlan['diet_calories'] ?: 2000),
            'meals' => $assignedPlan['meals_json'] ?? null
        ];
    }
    if (!empty($assignedPlan['trainer_name'])) {
        $trainerData = [
            'id' => (int)($assignedPlan['trainer_id'] ?? 0),
            'name' => (string)$assignedPlan['trainer_name'],
            'designation' => (string)($assignedPlan['trainer_designation'] ?: 'Personal Trainer'),
            'phone' => (string)($assignedPlan['trainer_contact'] ?? ''),
            'email' => (string)($assignedPlan['trainer_email'] ?? ''),
            'specialization' => 'Strength & Conditioning',
            'timings' => '06:00 AM - 09:00 PM'
        ];
    }
} else {
    try {
        $directWp = api_column_exists('workout_plans', 'member_id')
            ? DB::fetchOne("SELECT * FROM workout_plans WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1", [$memberId, $tenantId])
            : null;
        if ($directWp) {
            $workoutData = [
                'name' => (string)($directWp['plan_name'] ?? $directWp['name'] ?? 'Workout Routine'),
                'goal' => (string)(($directWp['goal'] ?? '') ?: 'General Fitness'),
                'level' => (string)(($directWp['level'] ?? '') ?: 'Intermediate'),
                'schedule' => $directWp['schedule_json'] ?? null,
                'trainer_name' => (string)(($directWp['trainer_name'] ?? '') ?: 'Personal Trainer')
            ];
        }
    } catch (Throwable $e) {}

    try {
        $directDp = api_column_exists('diet_plans', 'member_id')
            ? DB::fetchOne("SELECT * FROM diet_plans WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1", [$memberId, $tenantId])
            : null;
        if ($directDp) {
            $dietData = [
                'name' => (string)($directDp['plan_name'] ?? $directDp['name'] ?? 'Nutrition Plan'),
                'target' => (string)($directDp['target'] ?: 'Health & Fitness'),
                'calories' => (int)($directDp['calories'] ?: 2200),
                'meals' => $directDp['meals_json'] ?? null
            ];
        }
    } catch (Throwable $e) {}

    try {
        $directTr = api_table_exists('trainers')
            ? DB::fetchOne("SELECT * FROM trainers WHERE member_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1", [$memberId, $tenantId])
            : null;
        if (!$directTr && !empty($member['trainer_id'])) {
            // Member's directly assigned trainer (staffs), same tenant, still active
            $st = DB::fetchOne(
                "SELECT user_id, fullname, designation, contact, email FROM staffs WHERE user_id = ? AND tenant_id = ? AND status = 'active'",
                [(int)$member['trainer_id'], $tenantId]
            );
            if ($st) {
                $directTr = ['id' => $st['user_id'], 'fullname' => $st['fullname'], 'designation' => $st['designation'], 'phone' => $st['contact'], 'email' => $st['email']];
            }
        }
        if ($directTr) {
            $trainerData = [
                'id' => (int)($directTr['id'] ?? 0),
                'name' => (string)($directTr['fullname'] ?? 'Trainer'),
                'designation' => (string)(($directTr['designation'] ?? '') ?: 'Personal Trainer'),
                'phone' => (string)($directTr['phone'] ?? ''),
                'email' => (string)($directTr['email'] ?? ''),
                'specialization' => (string)(($directTr['specializations'] ?? '') ?: 'Fitness & Training'),
                'timings' => (string)(($directTr['available_timings'] ?? '') ?: '06:00 AM - 02:00 PM')
            ];
        }
    } catch (Throwable $e) {}
}

// 6. Recent Gym Announcements
$announcements = [];
try {
    $announcements = DB::fetchAll(
        "SELECT id, message, date FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC LIMIT 5",
        [$tenantId]
    ) ?: [];
} catch (Throwable $e) {}

// 7. Member Todo / Checklist Items
$todos = [];
try {
    if (api_table_exists('member_workout_todos')) {
        $todos = DB::fetchAll(
            "SELECT id, task_desc, is_completed FROM member_workout_todos WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 10",
            [$tenantId, $memberId]
        ) ?: [];
    }
    if (empty($todos)) {
        $todos = DB::fetchAll(
            "SELECT id, task_desc, task_status FROM todo WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC LIMIT 10",
            [$tenantId, $memberId]
        ) ?: [];
        $todos = array_map(fn($t) => [
            'id' => (int)$t['id'],
            'task_desc' => $t['task_desc'],
            'is_completed' => ($t['task_status'] === 'Completed')
        ], $todos);
    } else {
        $todos = array_map(fn($t) => [
            'id' => (int)$t['id'],
            'task_desc' => $t['task_desc'],
            'is_completed' => (bool)$t['is_completed']
        ], $todos);
    }
} catch (Throwable $e) {}

// 8. Generate Digital QR Membership Card Signature
$qrTimestamp = time();
$qrHash = hash('sha256', "{$tenantId}:{$memberId}:" . ($member['username'] ?? '') . ":{$qrTimestamp}");
$qrPayload = json_encode([
    'type' => 'GYM_MEMBERSHIP_PASS',
    'gym_code' => $tenant['gym_code'] ?: 'GYM-' . $tenantId,
    'gym_id' => $tenantId,
    'member_id' => $memberId,
    'member_name' => (string)($member['fullname'] ?? 'Member'),
    'status' => ($daysLeft >= 0) ? 'ACTIVE' : 'EXPIRED',
    'expiry' => $expiryDate,
    'sig' => substr($qrHash, 0, 16)
]);

// Format Logo URL
$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . $tenant['logo'])) {
        $logoUrl = base_url('/uploads/logos/' . $tenant['logo']);
    } else {
        $logoUrl = base_url('/img/' . $tenant['logo']);
    }
}

// 9. Consolidated Dashboard Payload
$dashboard = [
    'gym' => [
        'id' => $tenantId,
        'name' => (string)($tenant['gym_name'] ?? 'Fitisify Gym'),
        'code' => (string)($tenant['gym_code'] ?: 'GYM-' . $tenantId),
        'logo' => $logoUrl,
        'primary_color' => (string)($tenant['primary_color'] ?: '#2563eb'),
        'secondary_color' => (string)($tenant['secondary_color'] ?: '#10b981'),
        'currency' => (string)($tenant['currency'] ?: '$'),
        'phone' => (string)($tenant['phone'] ?: ''),
        'email' => (string)($tenant['email'] ?: ''),
        'address' => (string)($tenant['address'] ?: '')
    ],
    'member' => [
        'id' => $memberId,
        'fullname' => (string)($member['fullname'] ?? 'Member'),
        'username' => (string)($member['username'] ?? ''),
        'email' => (string)($member['email'] ?? ''),
        'phone' => (string)($member['contact'] ?? ''),
        'avatar' => api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null),
        'weight_current' => (float)($member['current_weight'] ?? $member['curr_weight'] ?? 0),
        'weight_initial' => (float)($member['initial_weight'] ?? $member['ini_weight'] ?? 0),
        'body_type' => (string)($member['curr_body_type'] ?? $member['curr_bodytype'] ?? $member['body_type'] ?? 'Athletic')
    ],
    'membership' => [
        'plan_name' => (string)(($member['services'] ?? '') ?: 'General Fitness Plan'),
        'plan_duration_months' => $planMonths,
        'status' => ($daysLeft >= 0) ? 'Active' : 'Expired',
        'start_date' => $paidDate,
        'expiry_date' => $expiryDate,
        'days_remaining' => max(0, $daysLeft),
        'is_expiring_soon' => ($daysLeft >= 0 && $daysLeft <= 7),
        'total_fee' => (float)($member['amount'] ?? 0)
    ],
    'attendance' => [
        'today_status' => $todayAttendance ? (empty($todayAttendance['check_out_time']) ? 'Checked In' : 'Completed') : 'Not Checked In',
        'today_check_in' => (string)($todayAttendance['curr_time'] ?? ''),
        'today_check_out' => (string)($todayAttendance['check_out_time'] ?? ''),
        'total_lifetime_sessions' => (int)($member['attendance_count'] ?? 0)
    ],
    'payments' => [
        'total_paid' => $totalPaid,
        'outstanding_due' => $totalDue,
        'latest_invoice' => (!empty($invoices) && is_array($invoices) && isset($invoices[0])) ? [
            'id' => (int)($invoices[0]['id'] ?? 0),
            'number' => (string)($invoices[0]['invoice_number'] ?? 'INV-001'),
            'amount' => (float)($invoices[0]['paid_amount'] ?? 0),
            'date' => (string)($invoices[0]['payment_date'] ?? date('Y-m-d')),
            'status' => (string)($invoices[0]['status'] ?? 'Paid')
        ] : null
    ],
    'workout' => $workoutData,
    'diet' => $dietData,
    'trainer' => $trainerData,
    'announcements' => $announcements,
    'todos' => $todos,
    'qr_pass' => [
        'payload' => $qrPayload,
        'code' => "MEM-{$tenantId}-{$memberId}",
        'valid_until' => $expiryDate
    ]
];

ApiResponse::success($dashboard, 'Member dashboard loaded.');
