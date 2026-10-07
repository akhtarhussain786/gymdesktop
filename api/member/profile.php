<?php
/**
 * Member Profile Endpoint (GET & POST)
 * Fetches and updates member personal details with tenant isolation.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    
    // Only permit updating allowed member fields (validated)
    $updateData = [];
    $fieldErrors = [];
    if (isset($input['email'])) {
        $email = trim((string)$input['email']);
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
            $fieldErrors['email'] = 'Please enter a valid email address.';
        } else {
            $updateData['email'] = $email;
        }
    }
    if (isset($input['contact']) || isset($input['phone'])) {
        $contact = trim((string)($input['contact'] ?? $input['phone']));
        if ($contact !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $contact)) {
            $fieldErrors['phone'] = 'Please enter a valid phone number.';
        } else {
            $updateData['contact'] = $contact;
        }
    }
    if (isset($input['address'])) $updateData['address'] = mb_substr(trim((string)$input['address']), 0, 150);
    if (isset($input['curr_weight'])) {
        $w = (float)$input['curr_weight'];
        if ($w <= 0 || $w > 400) {
            $fieldErrors['curr_weight'] = 'Please enter a valid weight.';
        } else {
            $updateData['curr_weight'] = $w;
        }
    }
    if (isset($input['curr_bodytype'])) $updateData['curr_bodytype'] = mb_substr(trim((string)$input['curr_bodytype']), 0, 50);

    // Email / phone are login identifiers: they must not collide with another account in this gym
    foreach (['email' => 'email', 'contact' => 'phone'] as $col => $userCol) {
        if (!empty($updateData[$col])) {
            $clash = DB::fetchValue(
                "SELECT COUNT(*) FROM members WHERE tenant_id = ? AND user_id <> ? AND ({$col} = ? OR username = ?)",
                [$tenantId, $memberId, $updateData[$col], $updateData[$col]]
            );
            $clashUser = DB::fetchValue(
                "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (member_id IS NULL OR member_id <> ?) AND ({$userCol} = ? OR username = ?)",
                [$tenantId, $memberId, $updateData[$col], $updateData[$col]]
            );
            if ((int)$clash > 0 || (int)$clashUser > 0) {
                $fieldErrors[$col === 'contact' ? 'phone' : 'email'] = 'This ' . ($col === 'contact' ? 'phone number' : 'email') . ' is already in use by another account.';
            }
        }
    }

    if (!empty($fieldErrors)) {
        ApiResponse::error('Validation failed', 422, $fieldErrors);
    }

    if (!empty($updateData)) {
        DB::update('members', $updateData, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
        
        // Also update users table if email/phone changed (only this member's own login row)
        $userUpdate = [];
        if (isset($updateData['email'])) $userUpdate['email'] = $updateData['email'];
        if (isset($updateData['contact'])) $userUpdate['phone'] = $updateData['contact'];
        if (!empty($userUpdate)) {
            DB::update('users', $userUpdate, "tenant_id = ? AND member_id = ? AND role = 'member'", [$tenantId, $memberId]);
        }
    }

    // Refresh member record
    $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
}

$branch = DB::fetchOne("SELECT branch_name, address, phone FROM branches WHERE id = ? AND tenant_id = ?", [$member['branch_id'] ?? 1, $tenantId]);
$trainer = null;
if (!empty($member['trainer_id'])) {
    $trainer = DB::fetchOne("SELECT fullname, contact, email, designation FROM staffs WHERE user_id = ? AND tenant_id = ? AND status = 'active'", [$member['trainer_id'], $tenantId]);
}

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

$profile = [
    'gym' => [
        'id' => $tenantId,
        'name' => $tenant['gym_name'],
        'code' => $tenant['gym_code'] ?: 'GYM-' . $tenantId,
        'logo' => $logoUrl,
        'primary_color' => $tenant['primary_color'] ?: '#2563eb',
        'secondary_color' => $tenant['secondary_color'] ?: '#10b981'
    ],
    'member_id' => (int)$member['user_id'],
    'fullname' => $member['fullname'],
    'username' => $member['username'],
    'email' => $member['email'] ?? '',
    'phone' => $member['contact'] ?? '',
    'gender' => $member['gender'] ?? 'Not Specified',
    'address' => $member['address'] ?? '',
    'avatar' => !empty($member['avatar']) ? base_url('/img/' . $member['avatar']) : null,
    'current_weight' => (float)($member['current_weight'] ?? $member['curr_weight'] ?? 70),
    'initial_weight' => (float)($member['initial_weight'] ?? $member['ini_weight'] ?? 70),
    'body_type' => $member['curr_bodytype'] ?? $member['curr_body_type'] ?? $member['body_type'] ?? 'Athletic',
    'join_date' => $member['dor'] ?? $member['paid_date'] ?? date('Y-m-d'),
    'services' => $member['services'] ?: 'Fitness',
    'plan_months' => (int)($member['plan'] ?: 1),
    'branch' => $branch ? [
        'name' => $branch['branch_name'],
        'address' => $branch['address'],
        'phone' => $branch['phone']
    ] : null,
    'trainer' => $trainer ? [
        'name' => $trainer['fullname'],
        'designation' => $trainer['designation'],
        'phone' => (string)$trainer['contact'],
        'email' => $trainer['email']
    ] : null
];

ApiResponse::success($profile, 'Profile retrieved successfully.');
