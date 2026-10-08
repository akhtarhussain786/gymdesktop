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

// Remove duplicate records with same push token on different device IDs
try {
    DB::query(
        "DELETE FROM device_tokens WHERE device_token = ? AND device_id != ?",
        [$deviceToken, $deviceId]
    );
} catch (Throwable $e) {}

// Atomic Upsert by UNIQUE device_id for Admin
$upsertSql = "INSERT INTO device_tokens 
    (`tenant_id`, `member_id`, `user_id`, `user_role`, `device_token`, `device_id`, `platform`, `status`, `last_active_at`, `created_at`, `updated_at`)
VALUES 
    (?, 0, ?, ?, ?, ?, ?, 'active', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `tenant_id` = VALUES(`tenant_id`),
    `member_id` = 0,
    `user_id` = VALUES(`user_id`),
    `user_role` = VALUES(`user_role`),
    `device_token` = VALUES(`device_token`),
    `platform` = VALUES(`platform`),
    `status` = 'active',
    `last_active_at` = NOW(),
    `updated_at` = NOW()";

$upsertParams = [
    (int)$tenantId,
    (int)$userId,
    (string)$userRole,
    (string)$deviceToken,
    (string)$deviceId,
    (string)$platform
];

$upsertRes = DB::query($upsertSql, $upsertParams);

ApiResponse::success([
    'registered' => true,
    'tenant_id' => (int)$tenantId,
    'user_id' => (int)$userId,
    'user_role' => $userRole,
    'platform' => $platform,
    'db_result' => is_array($upsertRes) ? 'saved' : 'updated'
], 'Admin push notification token registered.');
