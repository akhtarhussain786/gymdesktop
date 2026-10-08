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
    $token = MemberAuthMiddleware::extractBearerToken();
    if ($token) {
        $jwt = MemberAuthMiddleware::validateJwt($token);
        if ($jwt) {
            $tenantId = (int)($jwt['tenant_id'] ?? 0);
            $memberId = !empty($jwt['member_id']) ? (int)$jwt['member_id'] : null;
            $userId = !empty($jwt['user_id']) ? (int)$jwt['user_id'] : null;
            $userRole = !empty($jwt['role']) ? $jwt['role'] : 'member';
        }
    }
} catch (Throwable $e) {}

// 2. Fallback tenant resolution via gym_code
if (empty($tenantId) && !empty($gymCode)) {
    try {
        $tenantId = (int)DB::fetchValue("SELECT id FROM tenants WHERE gym_code = ? OR slug = ? LIMIT 1", [$gymCode, $gymCode]);
    } catch (Throwable $e) {}
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
