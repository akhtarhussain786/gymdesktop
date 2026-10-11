<?php
/**
 * Universal Member Device Token Registration Endpoint (Push Notifications)
 * Handles Members and Guest App Launches gracefully.
 *
 * Who a device belongs to is decided ONLY by a valid member session (bearer token). Without one the
 * device is stored as a 'guest' row, which receives no member / gym-owner broadcasts. Client-sent
 * user_role / member_id are ignored.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/notifications.php';
require_once __DIR__ . '/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

NotificationEngine::ensureSchema();

$input = get_json_input();
$action = $input['action'] ?? 'register';
$deviceToken = trim((string)($input['device_token'] ?? ''));
$deviceId = trim((string)($input['device_id'] ?? ''));
$platform = strtolower(trim((string)($input['platform'] ?? 'android')));
$platform = in_array($platform, ['android', 'ios', 'web']) ? $platform : 'android';
$gymCode = trim((string)($input['gym_code'] ?? ''));

// Optional auth: an invalid / expired / non-member (e.g. admin app fallback) token simply means "guest".
// Never 401 here: the app treats a 401 as session expiry and logs the user out.
$tokenSent = MemberAuthMiddleware::extractToken() !== '';
$auth = MemberAuthMiddleware::tryAuthenticate();

$tenantId = 0;
$memberId = null;
$userId = null;
$userRole = 'guest';

if ($auth) {
    $tenantId = (int)$auth['tenant_id'];
    $memberId = (int)$auth['member_id'];
    $userId = (int)$auth['user_id'] > 0 ? (int)$auth['user_id'] : null;
    $userRole = 'member';
} else {
    // Guest: remember which gym the app is pointed at (informational only)
    $headers = get_request_headers();
    $lookupGym = $gymCode !== '' ? $gymCode : trim((string)($headers['X-Gym-Code'] ?? $headers['x-gym-code'] ?? ''));
    if ($lookupGym !== '') {
        try {
            $tRow = DB::fetchOne("SELECT id FROM tenants WHERE LOWER(gym_code) = ? OR LOWER(slug) = ? LIMIT 1", [strtolower($lookupGym), strtolower($lookupGym)]);
            if ($tRow) {
                $tenantId = (int)$tRow['id'];
            }
        } catch (Throwable $e) {}
    }
}

// "No member" is NULL where the column allows it, 0 on installs where device_tokens.member_id is NOT NULL
$memberNullable = strtoupper((string)DB::fetchValue(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_tokens' AND COLUMN_NAME = 'member_id'"
)) !== 'NO';
$noMember = $memberNullable ? null : 0;
$hasIsActive = api_column_exists('device_tokens', 'is_active');

/** Row is not linked to any member or staff login (a guest / logged-out device) */
$isUnowned = function ($row) {
    return (int)($row['member_id'] ?? 0) <= 0 && (int)($row['user_id'] ?? 0) <= 0;
};

// Handle unregister / logout: only the caller's own rows (valid session), or the exact device_id + device_token pair
if ($action === 'unregister') {
    if ($deviceId !== '') {
        $where = null;
        $params = [];
        if ($auth) {
            $where = "device_id = ? AND tenant_id = ? AND member_id = ?";
            $params = [$deviceId, $tenantId, $memberId];
        } elseif ($deviceToken !== '') {
            $where = "device_id = ? AND device_token = ?";
            $params = [$deviceId, $deviceToken];
        }
        if ($where !== null) {
            $res = DB::query(
                "UPDATE device_tokens SET member_id = ?, user_id = NULL, user_role = 'guest', status = 'inactive'"
                . ($hasIsActive ? ", is_active = 0" : "") . ", updated_at = NOW() WHERE {$where}",
                array_merge([$noMember], $params)
            );
            if ($res === false) {
                // e.g. a guest row for this device already exists under a per-member unique key: just drop this one
                DB::query("DELETE FROM device_tokens WHERE {$where}", $params);
            }
        }
    }
    ApiResponse::success(null, 'Device token session closed.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}

if (strlen($deviceToken) > 4096 || strlen($deviceId) > 100) {
    ApiResponse::error('Invalid device token or device ID length.', 422);
}

// Pick the existing row for this device that the caller may take over:
// - with a member session: its own row, a guest / logged-out row, or a row carrying this exact push token
//   (same app install, e.g. a previous user whose logout never reached the server);
// - without one: only a guest / logged-out row, or (app fully logged out, no token sent) a row carrying
//   this exact push token. Another account's live registration is never overwritten.
$rows = DB::fetchAll(
    "SELECT id, tenant_id, member_id, user_id, user_role, device_token FROM device_tokens WHERE device_id = ? ORDER BY id DESC",
    [$deviceId]
);
$target = null;
$claimableIds = [];
foreach ($rows as $r) {
    $sameInstall = hash_equals((string)$r['device_token'], $deviceToken);
    if ($auth) {
        $mine = (int)$r['tenant_id'] === $tenantId && (int)$r['member_id'] === $memberId;
        $claimable = $mine || $isUnowned($r) || $sameInstall;
        if ($mine) {
            $target = $r;
        }
    } else {
        $claimable = $isUnowned($r) || (!$tokenSent && $sameInstall);
    }
    if ($claimable) {
        $claimableIds[] = (int)$r['id'];
        if ($target === null) {
            $target = $r;
        }
    }
}

if ($target === null && !empty($rows)) {
    ApiResponse::success([
        'registered' => false,
        'tenant_id' => (int)$tenantId,
        'member_id' => $memberId,
        'user_id' => $userId,
        'user_role' => $userRole,
        'platform' => $platform,
        'db_result' => 'skipped'
    ], 'This device is registered to another account. Log in to receive your notifications on it.');
}

// Remove duplicate records with same push token on different device IDs (stale installs of this same app)
try {
    DB::query("DELETE FROM device_tokens WHERE device_token = ? AND device_id != ?", [$deviceToken, $deviceId]);
} catch (Throwable $e) {}

$rowMemberId = $memberId !== null ? (int)$memberId : $noMember;

if ($target !== null) {
    // Other rows of this same device we were allowed to take over are superseded by the target row
    $extraIds = array_values(array_diff($claimableIds, [(int)$target['id']]));
    if (!empty($extraIds)) {
        DB::query("DELETE FROM device_tokens WHERE id IN (" . implode(',', array_fill(0, count($extraIds), '?')) . ")", $extraIds);
    }
    $writeRes = DB::query(
        "UPDATE device_tokens SET tenant_id = ?, member_id = ?, user_id = ?, user_role = ?, device_token = ?, platform = ?,
                status = 'active'" . ($hasIsActive ? ", is_active = 1" : "") . ", last_active_at = NOW(), updated_at = NOW()
         WHERE id = ?",
        [(int)$tenantId, $rowMemberId, $userId, $userRole, $deviceToken, $platform, (int)$target['id']]
    );
} else {
    $writeRes = DB::query(
        "INSERT INTO device_tokens
            (`tenant_id`, `member_id`, `user_id`, `user_role`, `device_token`, `device_id`, `platform`, `status`, `last_active_at`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW(), NOW())",
        [(int)$tenantId, $rowMemberId, $userId, $userRole, $deviceToken, $deviceId, $platform]
    );
}

$saved = is_array($writeRes);
ApiResponse::success([
    'registered' => $saved,
    'tenant_id' => (int)$tenantId,
    'member_id' => $memberId,
    'user_id' => $userId,
    'user_role' => $userRole,
    'platform' => $platform,
    'db_result' => $saved ? 'saved' : 'failed'
], $saved ? 'Member push notification device token synced.' : 'Device token could not be saved right now.');
