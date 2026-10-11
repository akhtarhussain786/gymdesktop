<?php
/**
 * Gym Admin Edit & Delete Member API
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST: Update or Delete
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'update';

    if ($action === 'delete') {
        // Deleting a member is an owner action (same as the web panel)
        AdminAuthMiddleware::requireRole(AdminAuthMiddleware::ROLES_OWNER);

        $deleteId = (int)($input['member_id'] ?? $input['user_id'] ?? $input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Invalid member ID', 400);
        }

        $exists = DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        if (!$exists) {
            ApiResponse::error('Member not found', 404);
        }

        // Remove the member's login + app sessions and operational child rows. Invoices are KEPT for
        // financial/audit history (same as admin/actions/delete-member.php).
        foreach (DB::fetchAll("SELECT id FROM users WHERE member_id = ? AND tenant_id = ? AND role = 'member'", [$deleteId, $tenantId]) as $loginRow) {
            Auth::revokeApiTokens($tenantId, (int)$loginRow['id']);
        }
        Auth::revokeApiTokens($tenantId, null, $deleteId);
        DB::delete('users', "member_id = ? AND tenant_id = ? AND role = 'member'", [$deleteId, $tenantId]);
        DB::delete('member_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('device_tokens', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('attendance', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('member_assigned_plans', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('class_bookings', 'member_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('todo', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::query("UPDATE member_subscriptions SET status = 'cancelled' WHERE member_id = ? AND tenant_id = ? AND status IN ('active', 'upcoming', 'pending_payment')", [$deleteId, $tenantId]);
        DB::delete('members', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);

        Auth::auditLog('DELETE_MEMBER', "Deleted member ID " . $deleteId, $tenantId, (int)$auth['user_id']);
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
    // The app omits trainer_id: keep the current trainer unless the key is sent (empty = unassign)
    $trainerSent = array_key_exists('trainer_id', $input);
    $trainer_id = $trainerSent ? (!empty($input['trainer_id']) ? (int)$input['trainer_id'] : null) : ($existing['trainer_id'] ?? null);
    $customExpiry = !empty($input['expiry_date']) ? trim((string)$input['expiry_date']) : null;
    $customDor = !empty($input['dor']) ? trim((string)$input['dor']) : null;

    if ($customDor !== null && !edit_member_valid_date($customDor)) {
        ApiResponse::error('Invalid joining date. Use YYYY-MM-DD.', 422);
    }
    if ($customExpiry !== null && !edit_member_valid_date($customExpiry)) {
        ApiResponse::error('Invalid expiry date. Use YYYY-MM-DD.', 422);
    }

    if (!in_array($status, ['Active', 'Expired', 'Pending'], true)) $status = $existing['status'];
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = $existing['gender'];
    $plan = max(1, $plan);

    if ($trainerSent && $trainer_id !== null && !DB::fetchValue("SELECT user_id FROM staffs WHERE user_id = ? AND tenant_id = ?", [$trainer_id, $tenantId])) {
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

    // The app always sends the expiry date it displayed. Only a real change (or a plan change, which keeps the
    // displayed expiry) moves paid_date; an unrelated profile edit leaves the membership period alone.
    $existingPlan = max(1, (int)($existing['plan'] ?? 1));
    $currentExpiries = edit_member_current_expiries($existing, $existingPlan);
    $expiryChanged = $customExpiry !== null && !in_array($customExpiry, $currentExpiries, true);
    $planChanged = array_key_exists('plan', $input) && $plan !== $existingPlan;

    if ($customExpiry !== null && ($expiryChanged || $planChanged)) {
        // Calculate new paid_date so DATE_ADD(paid_date, INTERVAL plan MONTH) = customExpiry
        $updateData['paid_date'] = edit_member_start_for_expiry($customExpiry, $plan);
    }

    if ($expiryChanged) {
        // Extending into the future re-activates the membership
        if ($customExpiry >= date('Y-m-d')) {
            $updateData['status'] = 'Active';
        }

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
            $data = base64_decode($data, true);
            if ($data === false || @getimagesizefromstring($data) === false) {
                ApiResponse::error('The photo must be a valid PNG, JPG, GIF or WEBP image.', 422);
            }
            $uploadDir = __DIR__ . '/../../uploads/avatars';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $photoName = 'avatar_' . $tenantId . '_' . $memberId . '_' . time() . '.' . $type;
            if (@file_put_contents($uploadDir . '/' . $photoName, $data) !== false) {
                // members.avatar / users.avatar hold the photo (there is no `photo` column)
                $updateData['avatar'] = $photoName;
                DB::query("UPDATE users SET avatar = ? WHERE member_id = ? AND tenant_id = ? AND role = 'member'", [$photoName, $memberId, $tenantId]);
            }
        }
    }

    DB::$lastError = '';
    $updated = DB::update('members', $updateData, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
    if (!$updated && DB::$lastError !== '') {
        ApiResponse::error('Could not update the member profile. Please check the details and try again.', 500);
    }
    Auth::auditLog('UPDATE_MEMBER', "Updated profile for member $fullname (ID $memberId)", $tenantId, (int)$auth['user_id']);

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

// Never send the password hash to the app
unset($member['password']);

ApiResponse::success([
    'member' => array_merge($member, [
        'photo_url' => $photoUrl,
        'avatar_url' => $photoUrl,
        'expiry_date' => $member['computed_expiry'] ?? date('Y-m-d')
    ]),
    'rates' => $rates,
    'trainers' => $trainers
], 'Member details retrieved successfully');

function edit_member_valid_date($d) {
    if (!is_string($d) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/**
 * Expiry dates the app may have displayed for the member's current period: the members list uses MySQL
 * DATE_ADD on paid_date (month-end safe; today when there is no paid_date), member_detail falls back to dor,
 * and older builds of member_detail used strtotime.
 */
function edit_member_current_expiries($member, $planMonths) {
    $valid = function ($v) {
        $v = (string)$v;
        return $v !== '' && strpos($v, '0000-00-00') !== 0 && strtotime($v) !== false;
    };
    $dates = [];
    $start = null;
    if ($valid($member['paid_date'] ?? '')) {
        $start = substr((string)$member['paid_date'], 0, 10);
    } else {
        $dates[] = date('Y-m-d');
        if ($valid($member['dor'] ?? '')) {
            $start = substr((string)$member['dor'], 0, 10);
        }
    }
    if ($start !== null) {
        $dates[] = SubscriptionEngine::addMonths($start, $planMonths);
        $dates[] = date('Y-m-d', strtotime('+' . $planMonths . ' months', strtotime($start)));
    }
    return array_values(array_unique($dates));
}

/**
 * Period start whose expiry (start + plan months, month-end clamped) is the requested date. When no start
 * maps exactly (e.g. expiry on the 31st after a 30-day month) the earliest start that does not shorten the
 * membership is used.
 */
function edit_member_start_for_expiry($expiry, $planMonths) {
    $start = SubscriptionEngine::addMonths($expiry, -$planMonths);
    for ($i = 0; $i < 4 && SubscriptionEngine::addMonths($start, $planMonths) < $expiry; $i++) {
        $start = date('Y-m-d', strtotime('+1 day', strtotime($start)));
    }
    return $start;
}
