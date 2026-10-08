<?php
/**
 * Admin / Staff Device Token Registration Endpoint (Push Notifications)
 * Scopes device tokens to the authenticated tenant, user_id, and role.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/notifications.php';

NotificationEngine::ensureSchema();

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$userId = (int)$auth['user_id'];
$userRole = !empty($auth['role']) ? strtolower((string)$auth['role']) : 'gym_admin';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = get_json_input();
$action = $input['action'] ?? 'register';
$deviceToken = trim((string)($input['device_token'] ?? ''));
$deviceId = trim((string)($input['device_id'] ?? ''));
$platform = strtolower(trim((string)($input['platform'] ?? 'android')));
$platform = in_array($platform, ['android', 'ios', 'web']) ? $platform : 'android';

if ($action === 'unregister') {
    if (!empty($deviceId)) {
        DB::query("UPDATE device_tokens SET user_id = NULL, status = 'inactive', updated_at = NOW() WHERE device_id = ? AND tenant_id = ?", [$deviceId, $tenantId]);
    }
    ApiResponse::success(null, 'Admin device token unregistered.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}
if (strlen($deviceToken) > 4096 || strlen($deviceId) > 100) {
    ApiResponse::error('Invalid device token or device ID.', 422);
}

// Remove any outdated registration with this push token elsewhere
try {
    DB::query(
        "DELETE FROM device_tokens WHERE device_token = ? AND NOT (tenant_id = ? AND device_id = ?)",
        [$deviceToken, $tenantId, $deviceId]
    );
} catch (Throwable $e) {}

// Upsert device token for admin user
$existing = DB::fetchOne("SELECT id FROM device_tokens WHERE device_id = ?", [$deviceId]);

if ($existing) {
    DB::update('device_tokens', [
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'member_id' => null,
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
        'user_id' => $userId,
        'member_id' => null,
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
    'user_id' => $userId,
    'user_role' => $userRole,
    'platform' => $platform
], 'Admin push notification token registered.');
