<?php
/**
 * Universal Member Device Token Registration Endpoint (Push Notifications)
 * Handles Members and Guest App Launches gracefully.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/notifications.php';
require_once __DIR__ . '/middleware.php';

NotificationEngine::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $con = DB::connect();
    $tableInfo = [];
    $colRes = @mysqli_query($con, "SHOW COLUMNS FROM `device_tokens`");
    if ($colRes) {
        while ($c = mysqli_fetch_assoc($colRes)) {
            $tableInfo[] = $c['Field'] . ' (' . $c['Type'] . ')';
        }
    }

    $testErr = null;
    if (isset($_GET['test_insert'])) {
        $insertRes = DB::insert('device_tokens', [
            'tenant_id' => 27,
            'user_id' => 37,
            'user_role' => 'gym_admin',
            'device_token' => 'eFscZNN4Qgi-qmwFTfwjKU:APA91bF6McjFPsrOMYp_l7Bwhzb9i0D3XtrsB5vjI1emwQPkMXwvKQ4CS3530uf2erU0pDjDNtCP1_JS2eGz_pAhMvx8ZddHYHL88xoaURZA6VUVf9vdZQo',
            'device_id' => 'test_diag_' . time(),
            'platform' => 'android',
            'status' => 'active'
        ]);
        $testErr = [
            'insert_id' => $insertRes,
            'last_error' => DB::$lastError,
            'mysqli_error' => mysqli_error($con)
        ];
    }

    $testBroadcast = null;
    if (isset($_GET['test_broadcast'])) {
        $testBroadcast = NotificationEngine::sendToGymOwners(
            '🔔 Live SuperAdmin Broadcast Test',
            'SuperAdmin broadcast verified! High-importance notification delivered to your phone.',
            [],
            'system_update',
            'all_gym_owners'
        );
    }

    $count = (int)DB::fetchValue("SELECT COUNT(*) FROM device_tokens");
    $activeCount = (int)DB::fetchValue("SELECT COUNT(*) FROM device_tokens WHERE (status = 'active' OR status IS NULL OR status = '')");
    $rows = DB::fetchAll("SELECT id, tenant_id, user_id, member_id, user_role, platform, status, last_active_at, updated_at, SUBSTRING(device_token, 1, 16) as token_preview FROM device_tokens ORDER BY id DESC LIMIT 20");
    
    ApiResponse::success([
        'columns' => $tableInfo,
        'test_insert' => $testErr,
        'test_broadcast' => $testBroadcast,
        'total_tokens' => $count,
        'active_tokens' => $activeCount,
        'recent_devices' => $rows
    ], 'Device tokens diagnostic');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = get_json_input();
$action = $input['action'] ?? 'register';
$deviceToken = trim((string)($input['device_token'] ?? ''));
$deviceId = trim((string)($input['device_id'] ?? ''));
$platform = strtolower(trim((string)($input['platform'] ?? 'android')));
$platform = in_array($platform, ['android', 'ios', 'web']) ? $platform : 'android';
$gymCode = trim((string)($input['gym_code'] ?? ''));
$userRole = trim((string)($input['user_role'] ?? 'member'));

$tenantId = 0;
$memberId = null;
$userId = null;

// 1. Try to resolve authentication if bearer token is present
try {
    $token = MemberAuthMiddleware::extractToken();
    if (!empty($token)) {
        $tokenHash = hash('sha256', $token);
        $tokenRecord = DB::fetchOne("SELECT * FROM member_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1", [$tokenHash]);
        if ($tokenRecord) {
            $tenantId = (int)$tokenRecord['tenant_id'];
            $memberId = !empty($tokenRecord['member_id']) && (int)$tokenRecord['member_id'] > 0 ? (int)$tokenRecord['member_id'] : null;
            $userId = !empty($tokenRecord['user_id']) && (int)$tokenRecord['user_id'] > 0 ? (int)$tokenRecord['user_id'] : null;
            
            // Check user table to get exact role if user_id is set
            if ($userId) {
                $userRow = DB::fetchOne("SELECT role FROM users WHERE id = ? LIMIT 1", [$userId]);
                if ($userRow && !empty($userRow['role'])) {
                    $userRole = strtolower((string)$userRow['role']);
                }
            }
        }
    }
} catch (Throwable $e) {}

// 2. Fallback tenant resolution via gym_code or headers
if (empty($tenantId)) {
    $headers = get_request_headers();
    $headerGymCode = $headers['X-Gym-Code'] ?? $headers['x-gym-code'] ?? '';
    $lookupGym = !empty($gymCode) ? $gymCode : $headerGymCode;
    if (!empty($lookupGym)) {
        try {
            $tRow = DB::fetchOne("SELECT id FROM tenants WHERE LOWER(gym_code) = ? OR LOWER(slug) = ? LIMIT 1", [strtolower($lookupGym), strtolower($lookupGym)]);
            if ($tRow) {
                $tenantId = (int)$tRow['id'];
            }
        } catch (Throwable $e) {}
    }
}

// Preserve explicit role sent from client if valid
if (!empty($input['user_role'])) {
    $clientRole = strtolower(trim((string)$input['user_role']));
    if (in_array($clientRole, ['gym_admin', 'staff', 'super_admin', 'trainer', 'member'])) {
        $userRole = $clientRole;
    }
}

// Handle unregister / logout
if ($action === 'unregister') {
    if (!empty($deviceId)) {
        // Disassociate user from this device rather than breaking other devices
        DB::query("UPDATE device_tokens SET member_id = NULL, user_id = NULL, status = 'inactive', updated_at = NOW() WHERE device_id = ?", [$deviceId]);
    }
    ApiResponse::success(null, 'Device token session closed.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}

if (strlen($deviceToken) > 4096 || strlen($deviceId) > 100) {
    ApiResponse::error('Invalid device token or device ID length.', 422);
}

// Remove any other records with this exact device_token to prevent cross-device duplicates
try {
    DB::query("DELETE FROM device_tokens WHERE device_token = ? AND device_id != ?", [$deviceToken, $deviceId]);
} catch (Throwable $e) {}

// Upsert by unique device_id
$existing = DB::fetchOne("SELECT id FROM device_tokens WHERE device_id = ?", [$deviceId]);

if ($existing) {
    DB::update('device_tokens', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'user_id' => $userId,
        'user_role' => $userRole,
        'device_token' => $deviceToken,
        'platform' => $platform,
        'status' => 'active',
        'last_active_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$existing['id']]);
} else {
    DB::insert('device_tokens', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'user_id' => $userId,
        'user_role' => $userRole,
        'device_token' => $deviceToken,
        'device_id' => $deviceId,
        'platform' => $platform,
        'status' => 'active',
        'last_active_at' => date('Y-m-d H:i:s')
    ]);
}

ApiResponse::success([
    'registered' => true,
    'tenant_id' => $tenantId,
    'member_id' => $memberId,
    'user_id' => $userId,
    'user_role' => $userRole,
    'platform' => $platform
], 'Member push notification device token synced.');
