<?php
/**
 * Member Change Password Endpoint
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];
$member = $auth['member'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = get_json_input();
$currentPassword = trim($input['current_password'] ?? '');
$newPassword = trim($input['new_password'] ?? '');
$confirmPassword = trim($input['confirm_password'] ?? '');

if (empty($currentPassword) || empty($newPassword)) {
    ApiResponse::error('Current and new password are required.', 422);
}

if (strlen($newPassword) < 6) {
    ApiResponse::error('New password must be at least 6 characters.', 422, ['new_password' => 'Minimum 6 characters.']);
}

if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
    ApiResponse::error('New passwords do not match.', 422, ['confirm_password' => 'Passwords do not match.']);
}

// Throttle current-password guessing with a stolen/borrowed session token
$pwRateKey = api_rate_key(['change_password', $tenantId, $memberId]);
if (api_rate_limited('change_password', $pwRateKey, 5, 900)) {
    ApiResponse::error('Too many incorrect attempts. Please wait 15 minutes and try again.', 429);
}

// Verify current password (members row, or the linked unified member login)
$isValid = api_verify_password($currentPassword, $member['password']) !== false;
if (!$isValid && !empty($auth['user']['password'])) {
    $isValid = api_verify_password($currentPassword, $auth['user']['password']) !== false;
}

if (!$isValid) {
    api_rate_record('change_password', $pwRateKey);
    ApiResponse::error('Current password is incorrect.', 400, ['current_password' => 'Incorrect current password.']);
}
api_rate_clear('change_password', $pwRateKey);

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update members table
DB::update('members', ['password' => $newHash], 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);

// Update ONLY this member's unified login row (never match on users.id, which could be a staff/admin row)
DB::update('users', ['password' => $newHash], "tenant_id = ? AND member_id = ? AND role = 'member'", [$tenantId, $memberId]);

// Revoke every other session of this member (keep the current one)
DB::query(
    "DELETE FROM member_tokens WHERE tenant_id = ? AND member_id = ? AND id <> ?",
    [$tenantId, $memberId, (int)$auth['token_id']]
);

ApiResponse::success(null, 'Password updated successfully. Other devices have been signed out.');
