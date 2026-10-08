<?php
/**
 * Universal Device Token Registration Endpoint (Push Notifications)
 * Handles Members, Admins, and Guest App Launches gracefully.
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/notifications.php';
require_once __DIR__ . '/middleware.php';

NotificationEngine::ensureSchema();

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

$tenantId = 0;
$memberId = null;
$userId = null;

// Try to resolve authentication if bearer token is present
try {
    $token = MemberAuthMiddleware::extractBearerToken();
    if ($token) {
        $jwt = MemberAuthMiddleware::validateJwt($token);
        if ($jwt) {
            $tenantId = (int)($jwt['tenant_id'] ?? 0);
            $memberId = !empty($jwt['member_id']) ? (int)$jwt['member_id'] : null;
            $userId = !empty($jwt['user_id']) ? (int)$jwt['user_id'] : null;
        }
    }
} catch (Throwable $e) {}

// Fallback tenant resolution via gym_code
if (empty($tenantId) && !empty($gymCode)) {
    try {
        $tenantId = (int)DB::fetchValue("SELECT id FROM tenants WHERE gym_code = ? OR slug = ? LIMIT 1", [$gymCode, $gymCode]);
    } catch (Throwable $e) {}
}

if ($action === 'unregister') {
    if (!empty($deviceId)) {
        DB::query("DELETE FROM device_tokens WHERE device_id = ?", [$deviceId]);
    }
    ApiResponse::success(null, 'Device token unregistered.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}

// Ensure table allows flexible upsert
try {
    DB::query("ALTER TABLE `device_tokens` MODIFY COLUMN `tenant_id` int(11) DEFAULT 0");
} catch (Throwable $e) {}

// Remove outdated duplicate token mappings
try {
    DB::query("DELETE FROM device_tokens WHERE device_token = ? AND device_id != ?", [$deviceToken, $deviceId]);
} catch (Throwable $e) {}

// Upsert by device_id
$existing = DB::fetchOne("SELECT id FROM device_tokens WHERE device_id = ?", [$deviceId]);

if ($existing) {
    DB::update('device_tokens', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'user_id' => $userId,
        'device_token' => $deviceToken,
        'platform' => $platform,
        'status' => 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$existing['id']]);
} else {
    DB::insert('device_tokens', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'user_id' => $userId,
        'device_token' => $deviceToken,
        'device_id' => $deviceId,
        'platform' => $platform,
        'status' => 'active'
    ]);
}

ApiResponse::success([
    'registered' => true,
    'tenant_id' => $tenantId,
    'member_id' => $memberId,
    'user_id' => $userId
], 'Push notification token registered.');
