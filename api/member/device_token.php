<?php
/**
 * Member Device Token Registration Endpoint (Push Notifications)
 * Scopes device tokens strictly to the authenticated tenant and member.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];
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
        // A member may only unregister their own device registration
        DB::query("DELETE FROM device_tokens WHERE tenant_id = ? AND member_id = ? AND device_id = ?", [$tenantId, $memberId, $deviceId]);
    }
    ApiResponse::success(null, 'Device token unregistered.');
}

if (empty($deviceToken) || empty($deviceId)) {
    ApiResponse::error('Device token and device ID are required.', 422);
}
if (strlen($deviceToken) > 4096 || strlen($deviceId) > 100) {
    ApiResponse::error('Invalid device token or device ID.', 422);
}

// The same push token must never stay bound to another member/device (would deliver this member's
// notifications to someone else, or another member's notifications to this device).
DB::query(
    "DELETE FROM device_tokens WHERE device_token = ? AND NOT (tenant_id = ? AND device_id = ?)",
    [$deviceToken, $tenantId, $deviceId]
);

// Upsert device token scoped by tenant_id and device_id (one physical device -> the member currently signed in)
$existing = DB::fetchOne("SELECT id FROM device_tokens WHERE tenant_id = ? AND device_id = ?", [$tenantId, $deviceId]);

if ($existing) {
    DB::update('device_tokens', [
        'member_id' => $memberId,
        'user_id' => (int)$userId,
        'device_token' => $deviceToken,
        'platform' => $platform,
        'status' => 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ? AND tenant_id = ?', [$existing['id'], $tenantId]);
} else {
    DB::insert('device_tokens', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'user_id' => (int)$userId,
        'device_token' => $deviceToken,
        'device_id' => $deviceId,
        'platform' => $platform,
        'status' => 'active'
    ]);
}

ApiResponse::success(null, 'Push notification token registered.');
