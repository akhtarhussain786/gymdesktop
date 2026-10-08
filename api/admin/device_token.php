<?php
/**
 * Admin / Staff Device Token Registration Endpoint (Push Notifications)
 * Scopes device tokens to the authenticated tenant and user_id.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/notifications.php';

NotificationEngine::ensureSchema();

$auth = AdminAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];
$userId = $auth['user_id'];

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
        DB::query("DELETE FROM device_tokens WHERE tenant_id = ? AND user_id = ? AND device_id = ?", [$tenantId, $userId, $deviceId]);
    }
    ApiResponse::success(null, 'Device token unregistered.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}
if (strlen($deviceToken) > 4096 || strlen($deviceId) > 100) {
    ApiResponse::error('Invalid device token or device ID.', 422);
}

// Remove any outdated registration with this push token
DB::query(
    "DELETE FROM device_tokens WHERE device_token = ? AND NOT (tenant_id = ? AND device_id = ?)",
    [$deviceToken, $tenantId, $deviceId]
);

// Upsert device token for admin user
$existing = DB::fetchOne("SELECT id FROM device_tokens WHERE tenant_id = ? AND device_id = ?", [$tenantId, $deviceId]);

if ($existing) {
    DB::update('device_tokens', [
        'user_id' => (int)$userId,
        'member_id' => null,
        'device_token' => $deviceToken,
        'platform' => $platform,
        'status' => 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ? AND tenant_id = ?', [$existing['id'], $tenantId]);
} else {
    DB::insert('device_tokens', [
        'tenant_id' => $tenantId,
        'user_id' => (int)$userId,
        'member_id' => null,
        'device_token' => $deviceToken,
        'device_id' => $deviceId,
        'platform' => $platform,
        'status' => 'active'
    ]);
}

ApiResponse::success(null, 'Admin push notification token registered.');
