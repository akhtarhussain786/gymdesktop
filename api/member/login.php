<?php
/**
 * Member Login Endpoint
 * Authenticates a member within the specified Gym Tenant context.
 * Generates secure API Bearer token and returns authenticated session data.
 */

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = get_json_input();
$gymCode = trim($input['gym_code'] ?? '');
$loginId = trim($input['login_id'] ?? $input['username'] ?? $input['email'] ?? '');
$password = trim($input['password'] ?? '');
$deviceId = trim($input['device_id'] ?? '');
$deviceName = trim($input['device_name'] ?? 'Mobile Device');
$rawPlatform = strtolower(trim($input['platform'] ?? 'android'));
$platform = in_array($rawPlatform, ['android', 'ios', 'web']) ? $rawPlatform : 'android';

// Validation
$errors = [];
if (empty($gymCode)) $errors['gym_code'] = 'Gym Code is required.';
if (empty($loginId)) $errors['login_id'] = 'Username, Member ID, Email or Phone is required.';
if (empty($password)) $errors['password'] = 'Password is required.';

if (!empty($errors)) {
    ApiResponse::error('Validation failed', 422, $errors);
}

// 0. Brute-force throttling: per (gym + login id + IP) and per IP
$clientIp = api_client_ip();
$rateKeyUser = api_rate_key(['login', $gymCode, $loginId, $clientIp]);
$rateKeyIp = api_rate_key(['login_ip', $clientIp]);
if (api_rate_limited('login', $rateKeyUser, 5, 900) || api_rate_limited('login_ip', $rateKeyIp, 30, 900)) {
    ApiResponse::error('Too many failed login attempts. Please wait 15 minutes and try again.', 429);
}

$recordLoginFailure = function () use ($rateKeyUser, $rateKeyIp) {
    api_rate_record('login', $rateKeyUser);
    api_rate_record('login_ip', $rateKeyIp);
};

$cleanGymCode = strtolower($gymCode);
$numericGymId = is_numeric($gymCode) ? (int)$gymCode : 0;
$tenant = DB::fetchOne(
    "SELECT t.*, p.name as plan_name, p.features 
     FROM tenants t 
     LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
     WHERE LOWER(t.gym_code) = ? OR LOWER(t.slug) = ? OR LOWER(t.gym_name) = ? OR t.id = ? LIMIT 1",
    [$cleanGymCode, $cleanGymCode, $cleanGymCode, $numericGymId]
);

if (!$tenant) {
    $recordLoginFailure();
    ApiResponse::error('Invalid Gym Code.', 404, ['gym_code' => 'Gym not found.']);
}

$tenantId = (int)$tenant['id'];

// Gym local time for membership date maths
api_apply_tenant_timezone($tenant);

// Check tenant status & SaaS subscription
$blockReason = api_tenant_block_reason($tenant);
if ($blockReason !== null) {
    ApiResponse::forbidden($blockReason);
}

// 2. Locate User or Member within this specific tenant
// Match case-insensitively by Username, Email/Gmail, Phone, or Member ID
$cleanLogin = strtolower($loginId);
$numId = is_numeric($loginId) ? (int)$loginId : -1;

$user = DB::fetchOne(
    "SELECT * FROM users 
     WHERE tenant_id = ? 
       AND (
         LOWER(TRIM(username)) = ? 
         OR (email IS NOT NULL AND email != '' AND LOWER(TRIM(email)) = ?) 
         OR (phone IS NOT NULL AND phone != '' AND TRIM(phone) = ?) 
         OR member_id = ?
       ) 
     LIMIT 1",
    [$tenantId, $cleanLogin, $cleanLogin, $loginId, $numId]
);

$member = DB::fetchOne(
    "SELECT * FROM members 
     WHERE tenant_id = ? 
       AND (
         LOWER(TRIM(username)) = ? 
         OR (email IS NOT NULL AND email != '' AND LOWER(TRIM(email)) = ?) 
         OR (contact IS NOT NULL AND contact != '' AND TRIM(contact) = ?) 
         OR user_id = ?
       ) 
     LIMIT 1",
    [$tenantId, $cleanLogin, $cleanLogin, $loginId, $numId]
);

// Cross-link user and member if one was found but not the other
if ($user && !$member) {
    if (!empty($user['member_id'])) {
        $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [(int)$user['member_id'], $tenantId]);
    }
    if (!$member) {
        $member = DB::fetchOne(
            "SELECT * FROM members WHERE tenant_id = ? AND (LOWER(TRIM(username)) = ? OR (email IS NOT NULL AND email != '' AND LOWER(TRIM(email)) = ?)) LIMIT 1",
            [$tenantId, strtolower(trim($user['username'])), strtolower(trim($user['email'] ?? ''))]
        );
    }
} elseif ($member && !$user) {
    $user = DB::fetchOne(
        "SELECT * FROM users WHERE tenant_id = ? AND (member_id = ? OR LOWER(TRIM(username)) = ? OR (email IS NOT NULL AND email != '' AND LOWER(TRIM(email)) = ?)) LIMIT 1",
        [$tenantId, (int)$member['user_id'], strtolower(trim($member['username'])), strtolower(trim($member['email'] ?? ''))]
    );
}

$isValid = false;
$userRole = $user ? strtolower((string)$user['role']) : 'member';
$needsRehash = false;

// Attempt password verification against user hash
if ($user && !empty($user['password'])) {
    $pwCheck = api_verify_password($password, $user['password']);
    if ($pwCheck !== false) {
        $isValid = true;
        if ($pwCheck === 'rehash') $needsRehash = true;
    }
}

// Fallback: try password against member hash if user check did not pass
if (!$isValid && $member && !empty($member['password'])) {
    $pwCheck = api_verify_password($password, $member['password']);
    if ($pwCheck !== false) {
        $isValid = true;
        if ($pwCheck === 'rehash') $needsRehash = true;
    }
}

if ($isValid) {
    // If Admin / Staff / Trainer, return authenticated staff session
    if ($user && in_array($userRole, ['gym_admin', 'staff', 'super_admin', 'trainer'], true)) {
        if ($user['status'] !== 'active') {
            ApiResponse::forbidden('Your account is currently ' . $user['status'] . '. Please contact gym support.');
        }

        api_rate_clear('login', $rateKeyUser);

        $userId = (int)$user['id'];
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiryDate = gmdate('Y-m-d H:i:s', time() + 90 * 86400);

        // Clean expired tokens
        DB::query("DELETE FROM member_tokens WHERE tenant_id = ? AND user_id = ? AND expires_at < ?", [$tenantId, $userId, gmdate('Y-m-d H:i:s')]);

        $tokenRow = [
            'tenant_id' => (int)$tenantId,
            'member_id' => 0,
            'user_id' => (int)$userId,
            'token_hash' => (string)$tokenHash,
            'device_id' => mb_substr((string)$deviceId, 0, 100),
            'device_name' => mb_substr((string)($deviceName ?: 'Mobile Device'), 0, 100),
            'platform' => (string)$platform,
            'expires_at' => (string)$expiryDate
        ];
        $cols = array_keys($tokenRow);
        DB::execute(
            "INSERT INTO `member_tokens` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")",
            array_values($tokenRow)
        );

        $adminResponse = [
            'token' => $rawToken,
            'role' => $userRole,
            'expires_at' => $expiryDate,
            'user' => [
                'id' => $userId,
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $userRole,
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
                'avatar' => api_member_avatar_url($user['avatar'] ?? null, null)
            ],
            'tenant' => [
                'id' => $tenantId,
                'gym_code' => $tenant['gym_code'] ?: 'GYM-' . $tenantId,
                'gym_name' => $tenant['gym_name'],
                'slug' => $tenant['slug'],
                'logo' => $tenant['logo'] ? base_url('/img/' . $tenant['logo']) : null,
                'primary_color' => $tenant['primary_color'] ?: '#2563eb',
                'secondary_color' => $tenant['secondary_color'] ?: '#10b981',
                'currency' => $tenant['currency'] ?: '₹',
                'phone' => $tenant['phone'] ?: '',
                'email' => $tenant['email'] ?: '',
                'address' => $tenant['address'] ?: '',
                'upi_id' => $tenant['upi_id'] ?? ''
            ]
        ];
        ApiResponse::success($adminResponse, 'Welcome back, ' . $user['fullname'] . ' (Admin)!');
    }

    // Member authentication branch
    if (!$member) {
        $recordLoginFailure();
        ApiResponse::error('Member account record not found in this gym.', 404);
    }

    // Auto-create or sync users row for member
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    if (!$user) {
        $insertedUserId = DB::insert('users', [
            'tenant_id' => $tenantId,
            'branch_id' => $member['branch_id'] ?? 1,
            'role' => 'member',
            'username' => $member['username'],
            'password' => $newHash,
            'email' => !empty($member['email']) ? $member['email'] : null,
            'fullname' => $member['fullname'],
            'phone' => $member['contact'] ?? null,
            'status' => 'active',
            'member_id' => $member['user_id'],
            'avatar' => $member['avatar'] ?? $member['photo'] ?? null
        ]);
        $user = DB::fetchOne("SELECT * FROM users WHERE id = ?", [$insertedUserId]);
    } else {
        if (strtolower((string)$user['status']) !== 'active') {
            ApiResponse::forbidden('Your account is currently ' . $user['status'] . '. Please contact gym support.');
        }

        $syncUpdate = [];
        if (empty($user['member_id'])) {
            $syncUpdate['member_id'] = $member['user_id'];
        }
        if (empty($user['email']) && !empty($member['email'])) {
            $syncUpdate['email'] = $member['email'];
        }
        if ($needsRehash) {
            $syncUpdate['password'] = $newHash;
        }
        if (!empty($syncUpdate)) {
            DB::update('users', $syncUpdate, 'id = ? AND tenant_id = ?', [$user['id'], $tenantId]);
        }
        if ($needsRehash) {
            DB::update('members', ['password' => $newHash], 'user_id = ? AND tenant_id = ?', [$member['user_id'], $tenantId]);
        }
    }
}

if (!$isValid || !$member) {
    $recordLoginFailure();
    ApiResponse::error('Invalid username, email, or password for this gym.', 401);
}

// Inactive / suspended / deleted members must not obtain a session
if (api_member_is_blocked($member)) {
    ApiResponse::forbidden('Your member account has been deactivated by gym administration.');
}

api_rate_clear('login', $rateKeyUser);

$memberId = (int)$member['user_id'];
$userId = (int)($user['id'] ?? 0);

// 3. Generate Cryptographically Secure API Token
$rawToken = bin2hex(random_bytes(32)); // 64-char hex string
$tokenHash = hash('sha256', $rawToken);
$expiryDate = gmdate('Y-m-d H:i:s', time() + 90 * 86400); // 90 days validity, stored in UTC

// Purge expired tokens for this member (UTC comparison, matches middleware)
DB::query("DELETE FROM member_tokens WHERE tenant_id = ? AND member_id = ? AND expires_at < ?", [$tenantId, $memberId, gmdate('Y-m-d H:i:s')]);

// Invalidate previous device tokens for the same device if provided
if (!empty($deviceId)) {
    DB::query("DELETE FROM member_tokens WHERE tenant_id = ? AND member_id = ? AND device_id = ?", [$tenantId, $memberId, $deviceId]);
}

// Cap concurrent sessions per member: keep only the 4 most recent (+ the one created below = 5)
$keepTokens = DB::fetchAll(
    "SELECT id FROM member_tokens WHERE tenant_id = ? AND member_id = ? ORDER BY id DESC LIMIT 4",
    [$tenantId, $memberId]
);
$keepIds = array_map(fn($r) => (int)$r['id'], $keepTokens);
if (!empty($keepIds)) {
    DB::query(
        "DELETE FROM member_tokens WHERE tenant_id = ? AND member_id = ? AND id NOT IN (" . implode(',', $keepIds) . ")",
        [$tenantId, $memberId]
    );
}

// Insert new token
$tokenRow = [
    'tenant_id' => (int)$tenantId,
    'member_id' => (int)$memberId,
    'user_id' => (int)$userId,
    'token_hash' => (string)$tokenHash,
    'device_id' => mb_substr((string)$deviceId, 0, 100),
    'device_name' => mb_substr((string)($deviceName ?: 'Mobile Device'), 0, 100),
    'platform' => (string)$platform,
    'expires_at' => (string)$expiryDate
];
// ip_address / user_agent are absent from the migration-built member_tokens table; only write them if present
if (api_column_exists('member_tokens', 'ip_address')) {
    $tokenRow['ip_address'] = mb_substr($clientIp, 0, 45);
}
if (api_column_exists('member_tokens', 'user_agent')) {
    $tokenRow['user_agent'] = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'FlutterApp'), 0, 500);
}
$cols = array_keys($tokenRow);
$insertRes = DB::execute(
    "INSERT INTO `member_tokens` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")",
    array_values($tokenRow)
);
if (!is_array($insertRes) || empty($insertRes['insert_id'])) {
    // Never hand out a token that was not persisted (it would fail on every subsequent request)
    ApiResponse::error('Unable to start a session right now. Please try again later.', 500);
}

// 4. Compute Membership Dates & Status (gym calendar days, inclusive expiry; prefers member_subscriptions)
$window = api_membership_window($tenantId, $member);
$paidDate = $window['start_date'];
$planMonths = $window['plan_months'];
$membershipExpiry = $window['expiry_date'];
$daysLeft = $window['days_left'];

$features = [];
if (!empty($tenant['features'])) {
    $features = json_decode($tenant['features'], true) ?: [];
}

// 5. Response Payload
$response = [
    'token' => $rawToken,
    'role' => 'member',
    'expires_at' => $expiryDate,
    'tenant' => [
        'id' => $tenantId,
        'gym_code' => $tenant['gym_code'] ?: 'GYM-' . $tenantId,
        'gym_name' => $tenant['gym_name'],
        'slug' => $tenant['slug'],
        'logo' => $tenant['logo'] ? base_url('/img/' . $tenant['logo']) : null,
        'primary_color' => $tenant['primary_color'] ?: '#2563eb',
        'secondary_color' => $tenant['secondary_color'] ?: '#10b981',
        'currency' => $tenant['currency'] ?: '$',
        'phone' => $tenant['phone'] ?: '',
        'email' => $tenant['email'] ?: '',
        'address' => $tenant['address'] ?: ''
    ],
    'member' => [
        'member_id' => $memberId,
        'user_id' => $userId,
        'fullname' => $member['fullname'],
        'username' => $member['username'],
        'email' => $member['email'] ?? $user['email'] ?? '',
        'phone' => $member['contact'] ?? $user['phone'] ?? '',
        'gender' => $member['gender'] ?? '',
        'address' => $member['address'] ?? '',
        'avatar' => api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null),
        'services' => $member['services'] ?? 'General Fitness',
        'plan_months' => $planMonths,
        'membership_status' => ($daysLeft >= 0) ? 'Active' : 'Expired',
        'start_date' => $paidDate,
        'expiry_date' => $membershipExpiry,
        'days_remaining' => max(0, $daysLeft),
        'attendance_count' => (int)($member['attendance_count'] ?? 0),
        'current_weight' => (float)($member['current_weight'] ?? $member['curr_weight'] ?? 0),
        'initial_weight' => (float)($member['initial_weight'] ?? $member['ini_weight'] ?? 0),
        'body_type' => $member['body_type'] ?? $member['curr_bodytype'] ?? 'Normal',
        'branch_id' => (int)($member['branch_id'] ?? 1)
    ],
    'enabled_features' => [
        'workouts' => in_array('all_features', $features) || in_array('workouts', $features),
        'diet' => in_array('all_features', $features) || in_array('diet', $features),
        'classes' => in_array('all_features', $features) || in_array('classes', $features),
        'online_payments' => in_array('all_features', $features) || in_array('payments', $features),
    ]
];

ApiResponse::success($response, 'Welcome back, ' . $member['fullname'] . '!');
