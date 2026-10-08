<?php
/**
 * Member Forgot Password Endpoint
 * Always returns the same generic response so callers cannot learn whether a gym or account exists.
 */

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

$input = get_json_input();
$gymCode = trim($input['gym_code'] ?? '');
$email = trim($input['email'] ?? $input['username'] ?? '');

if (empty($gymCode) || empty($email)) {
    ApiResponse::error('Gym Code and Email are required.', 422);
}

$genericMessage = 'If an account matches these details, a password reset request has been sent to your gym. Please also contact gym reception for immediate assistance.';

// Throttle: per (gym + identifier) and per IP
$clientIp = api_client_ip();
$rateKeyIdent = api_rate_key(['forgot', $gymCode, $email]);
$rateKeyIp = api_rate_key(['forgot_ip', $clientIp]);
if (api_rate_limited('forgot', $rateKeyIdent, 3, 3600) || api_rate_limited('forgot_ip', $rateKeyIp, 10, 3600)) {
    ApiResponse::error('Too many password reset requests. Please try again later.', 429);
}
api_rate_record('forgot', $rateKeyIdent);
api_rate_record('forgot_ip', $rateKeyIp);

$tenant = DB::fetchOne("SELECT id, gym_name FROM tenants WHERE gym_code = ? OR slug = ? LIMIT 1", [$gymCode, strtolower($gymCode)]);
if (!$tenant) {
    ApiResponse::success(null, $genericMessage);
}

$cleanIdent = strtolower($email);
$member = DB::fetchOne(
    "SELECT * FROM members 
     WHERE tenant_id = ? 
       AND (
         (email IS NOT NULL AND email != '' AND LOWER(TRIM(email)) = ?) 
         OR LOWER(TRIM(username)) = ? 
         OR (contact IS NOT NULL AND contact != '' AND TRIM(contact) = ?)
       ) 
     LIMIT 1",
    [$tenantId, $cleanIdent, $cleanIdent, $email]
);

if ($member && !api_member_is_blocked($member)) {
    // Avoid duplicate open reset tickets for the same member
    $openReset = DB::fetchValue(
        "SELECT COUNT(*) FROM member_inquiries WHERE tenant_id = ? AND member_id = ? AND category = 'Account / Password Reset' AND status = 'open'",
        [$tenantId, $member['user_id']]
    );
    if ((int)$openReset === 0) {
        // Log inquiry/reset request
        DB::insert('member_inquiries', [
            'tenant_id' => $tenantId,
            'member_id' => $member['user_id'],
            'category' => 'Account / Password Reset',
            'subject' => 'Password Reset Request for ' . $member['fullname'],
            'message' => 'Member requested password reset from mobile app on ' . date('Y-m-d H:i:s'),
            'status' => 'open'
        ]);
    }
}

ApiResponse::success(null, $genericMessage);
