<?php
/**
 * Member Logout Endpoint
 * Invalidates active bearer token and deactivates device push tokens for this session.
 */

require_once __DIR__ . '/middleware.php';

// Use the same token extraction as authenticated endpoints (Authorization / X-Auth-Token / X-Bearer-Token / POST body)
$token = MemberAuthMiddleware::extractToken(false);

if (!empty($token)) {
    $tokenHash = hash('sha256', $token);

    $record = DB::fetchOne("SELECT * FROM member_tokens WHERE token_hash = ?", [$tokenHash]);
    if ($record) {
        if (!empty($record['device_id'])) {
            // Only remove this member's own push registration for the device
            DB::query(
                "DELETE FROM device_tokens WHERE tenant_id = ? AND member_id = ? AND device_id = ?",
                [$record['tenant_id'], $record['member_id'], $record['device_id']]
            );
        }
        DB::query("DELETE FROM member_tokens WHERE id = ?", [$record['id']]);
    }
}

ApiResponse::success(null, 'Successfully logged out. Session terminated.');
