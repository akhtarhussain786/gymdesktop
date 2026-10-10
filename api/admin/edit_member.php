<?php
/**
 * Gym Admin Edit & Delete Member API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST: Update or Delete
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'update';

    if ($action === 'delete') {
        $deleteId = (int)($input['member_id'] ?? $input['user_id'] ?? $input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Invalid member ID', 400);
        }

        $exists = DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        if (!$exists) {
            ApiResponse::error('Member not found', 404);
        }

        // Clean up cascaded data
        DB::delete('users', "member_id = ? AND tenant_id = ? AND role = 'member'", [$deleteId, $tenantId]);
        DB::delete('member_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('device_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('attendance', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('member_assigned_plans', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('class_bookings', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('todo', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('invoices', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::query("UPDATE member_subscriptions SET status = 'cancelled' WHERE member_id = ? AND tenant_id = ? AND status IN ('active', 'upcoming', 'pending_payment')", [$deleteId, $tenantId]);
        DB::delete('members', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);

        Auth::auditLog('DELETE_MEMBER', "Deleted member ID " . $deleteId);
        ApiResponse::success([], 'Member deleted successfully');
    }

    // Default: Update Member Profile
    $memberId = (int)($input['member_id'] ?? $input['user_id'] ?? $input['id'] ?? 0);
    if ($memberId <= 0) {
        ApiResponse::error('Valid member ID is required', 400);
    }

    $existing = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
    if (!$existing) {
        ApiResponse::error('Member not found', 404);
    }

    $fullname = trim($input['fullname'] ?? $existing['fullname']);
    $gender = $input['gender'] ?? $existing['gender'];
    $services = $input['services'] ?? $existing['services'];
    $plan = (int)($input['plan'] ?? $existing['plan']);
    $address = trim($input['address'] ?? $existing['address']);
    $contact = trim($input['contact'] ?? $existing['contact']);
    $email = trim($input['email'] ?? ($existing['email'] ?? ''));
    $status = $input['status'] ?? $existing['status'];
    $trainer_id = !empty($input['trainer_id']) ? (int)$input['trainer_id'] : null;
    $customExpiry = !empty($input['expiry_date']) ? trim($input['expiry_date']) : null;
    $customDor = !empty($input['dor']) ? trim($input['dor']) : null;

    if (!in_array($status, ['Active', 'Expired', 'Pending'], true)) $status = $existing['status'];
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = $existing['gender'];
    $plan = max(1, $plan);

    if ($trainer_id !== null && !DB::fetchValue("SELECT user_id FROM staffs WHERE user_id = ? AND tenant_id = ?", [$trainer_id, $tenantId])) {
        $trainer_id = null;
    }

    $ini_weight = (float)($input['ini_weight'] ?? $existing['ini_weight'] ?? 70);
    $curr_weight = (float)($input['curr_weight'] ?? $existing['curr_weight'] ?? 70);
    $ini_bodytype = trim($input['ini_bodytype'] ?? $existing['ini_bodytype'] ?? 'Athletic');
    $curr_bodytype = trim($input['curr_bodytype'] ?? $existing['curr_bodytype'] ?? 'Athletic');

    $updateData = [
        'fullname' => $fullname,
        'gender' => $gender,
        'services' => $services,
        'plan' => $plan,
        'address' => $address,
        'contact' => $contact,
        'email' => $email,
        'status' => $status,
        'trainer_id' => $trainer_id,
        'ini_weight' => $ini_weight,
        'curr_weight' => $curr_weight,
        'initial_weight' => $ini_weight,
        'current_weight' => $curr_weight,
        'ini_bodytype' => $ini_bodytype,
        'curr_bodytype' => $curr_bodytype,
        'ini_body_type' => $ini_bodytype,
        'curr_body_type' => $curr_bodytype
    ];

    if (!empty($customDor)) {
        $updateData['dor'] = $customDor;
    }

    // If custom expiry date is explicitly set by admin
    if (!empty($customExpiry) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customExpiry)) {
        $baseDate = !empty($customDor) ? $customDor : ($existing['dor'] ?: $existing['paid_date'] ?: date('Y-m-d'));
        // If expiry is in the future, activate status
        if ($customExpiry >= date('Y-m-d')) {
            $updateData['status'] = 'Active';
        }
        // Calculate new paid_date so DATE_ADD(paid_date, INTERVAL plan MONTH) = customExpiry
        $newPaidDate = date('Y-m-d', strtotime("-$plan months", strtotime($customExpiry)));
        $updateData['paid_date'] = $newPaidDate;

        // Update member_subscriptions if exists
        if (api_table_exists('member_subscriptions')) {
            DB::query("UPDATE member_subscriptions 
                       SET expiry_date = ?, status = CASE WHEN ? >= CURDATE() THEN 'active' ELSE 'expired' END 
                       WHERE member_id = ? AND tenant_id = ? 
                       ORDER BY id DESC LIMIT 1", 
                       [$customExpiry, $customExpiry, $memberId, $tenantId]);
        }
    }

    // Handle base64 photo upload if provided
    if (!empty($input['photo_base64'])) {
        $data = $input['photo_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                $type = 'png';
            }
            $data = base64_decode($data);
            if ($data !== false) {
                $uploadDir = __DIR__ . '/../../uploads/avatars';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $photoName = 'avatar_' . $tenantId . '_' . $memberId . '_' . time() . '.' . $type;
                file_put_contents($uploadDir . '/' . $photoName, $data);
                $updateData['photo'] = $photoName;
                $updateData['avatar'] = $photoName;
                DB::query("UPDATE users SET avatar = ? WHERE member_id = ? AND tenant_id = ?", [$photoName, $memberId, $tenantId]);
            }
        }
    }

    DB::update('members', $updateData, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
    Auth::auditLog('UPDATE_MEMBER', "Updated profile for member $fullname (ID $memberId)");

    ApiResponse::success(['member_id' => $memberId], 'Member profile updated successfully');
}

// GET: Retrieve member details for editing
$memberId = (int)($_GET['id'] ?? 0);
if ($memberId <= 0) {
    ApiResponse::error('Member ID is required', 400);
}

$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE status END";

$member = DB::fetchOne("SELECT *, 
                               $effStatusSql AS effective_status,
                               $planEndSql AS computed_expiry,
                               DATEDIFF($planEndSql, CURDATE()) AS days_left
                        FROM members 
                        WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    ApiResponse::error('Member not found', 404);
}

$rates = Tenant::getRates($tenantId);
$trainers = DB::fetchAll("SELECT user_id, fullname, designation FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);

$photoUrl = api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null);

ApiResponse::success([
    'member' => array_merge($member, [
        'photo_url' => $photoUrl,
        'avatar_url' => $photoUrl,
        'expiry_date' => $member['computed_expiry'] ?? date('Y-m-d')
    ]),
    'rates' => $rates,
    'trainers' => $trainers
], 'Member details retrieved successfully');
