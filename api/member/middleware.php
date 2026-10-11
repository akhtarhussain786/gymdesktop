<?php
/**
 * Member API Security & Tenant Isolation Middleware
 * Enforces Token Authentication, Tenant Isolation, and Plan Restrictions
 */

require_once __DIR__ . '/common.php';

class MemberAuthMiddleware {
    private static $authenticatedMember = null;
    private static $authenticatedTenant = null;
    private static $authenticatedUser = null;

    private static $currentTokenId = null;

    /**
     * Extract the raw bearer token from headers or POST body.
     * The ?token= query string is accepted ONLY when $allowQueryToken is true (no endpoint enables it any
     * more), because query strings leak into server logs, proxies and browser history.
     */
    public static function extractToken($allowQueryToken = false) {
        $headers = get_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $headers['x-auth-token'] ?? $headers['X-Auth-Token'] ?? $headers['x-bearer-token'] ?? $headers['X-Bearer-Token'] ?? '';

        if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (empty($authHeader) && isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            $authHeader = $_SERVER['HTTP_X_AUTH_TOKEN'];
        }
        if (empty($authHeader) && isset($_SERVER['HTTP_X_BEARER_TOKEN'])) {
            $authHeader = $_SERVER['HTTP_X_BEARER_TOKEN'];
        }
        if (empty($authHeader) && !empty($_POST['token']) && is_string($_POST['token'])) {
            $authHeader = 'Bearer ' . $_POST['token'];
        }
        if (empty($authHeader) && $allowQueryToken && !empty($_GET['token']) && is_string($_GET['token'])) {
            $authHeader = 'Bearer ' . $_GET['token'];
        }

        $token = '';
        if (preg_match('/Bearer\s+(\S+)/i', (string)$authHeader, $matches)) {
            $token = $matches[1];
        } elseif (!empty($authHeader) && !str_contains($authHeader, ' ')) {
            $token = trim($authHeader);
        }
        return $token;
    }

    /**
     * Authenticate request and establish isolated tenant context
     */
    public static function authenticate($requiredFeature = null, $allowQueryToken = false) {
        $token = self::extractToken($allowQueryToken);

        if (empty($token)) {
            ApiResponse::unauthorized('Missing or invalid Authorization header. Please log in.');
        }

        $tokenHash = hash('sha256', $token);

        // Find valid token in database
        $tokenRecord = DB::fetchOne(
            "SELECT * FROM member_tokens WHERE token_hash = ? LIMIT 1",
            [$tokenHash]
        );

        if (!$tokenRecord) {
            ApiResponse::unauthorized('Your session has expired or is invalid. Please log in again.');
        }

        // Verify expiration strictly (no grace). New tokens store expires_at in UTC (see login.php).
        $expRaw = (string)($tokenRecord['expires_at'] ?? '');
        $expTime = ($expRaw !== '' && $expRaw !== '0000-00-00 00:00:00') ? strtotime($expRaw . ' UTC') : false;
        if ($expTime === false || $expTime < time()) {
            DB::query("DELETE FROM member_tokens WHERE id = ?", [$tokenRecord['id']]);
            ApiResponse::unauthorized('Your session has expired. Please log in again.');
        }

        $tenantId = (int)$tokenRecord['tenant_id'];
        $memberId = (int)$tokenRecord['member_id'];
        $userId = (int)$tokenRecord['user_id'];

        // Fetch verified tenant
        $tenant = DB::fetchOne(
            "SELECT t.*, p.name as plan_name, p.max_members, p.features 
             FROM tenants t 
             LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
             WHERE t.id = ?",
            [$tenantId]
        );

        if (!$tenant) {
            ApiResponse::forbidden('Gym account does not exist.');
        }

        // Shared helpers (format_currency etc.) read the gym from Tenant::getCurrent(); without this they
        // would fall back to the platform defaults (₹, Asia/Kolkata) and reset the timezone set below.
        Tenant::setTenantId($tenantId);
        Tenant::setCurrent($tenant);

        // Use the gym's local calendar for all date maths in this request
        api_apply_tenant_timezone($tenant);

        // Check gym account status & SaaS subscription (inactive/suspended, or expired past grace)
        $blockReason = api_tenant_block_reason($tenant);
        if ($blockReason !== null) {
            ApiResponse::forbidden($blockReason);
        }

        // Fetch verified member record strictly by (members.user_id AND tenant_id).
        // (No fallback on users.id: that is a different id space and could resolve to another member.)
        $member = DB::fetchOne(
            "SELECT * FROM members WHERE user_id = ? AND tenant_id = ?",
            [$memberId, $tenantId]
        );

        if (!$member) {
            ApiResponse::forbidden('Member profile not found for this gym.');
        }

        if (api_member_is_blocked($member)) {
            DB::query("DELETE FROM member_tokens WHERE id = ?", [$tokenRecord['id']]);
            ApiResponse::forbidden('Your member account has been deactivated by gym administration.');
        }

        // Fetch unified user (must be this member's own 'member' login row, never a staff/admin row)
        $user = null;
        if ($userId > 0) {
            $user = DB::fetchOne("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role = 'member'", [$userId, $tenantId]);
            if ($user && !empty($user['member_id']) && (int)$user['member_id'] !== $memberId) {
                $user = null;
            }
        }
        if ($user && strtolower((string)$user['status']) !== 'active') {
            DB::query("DELETE FROM member_tokens WHERE id = ?", [$tokenRecord['id']]);
            ApiResponse::forbidden('Your account is currently ' . $user['status'] . '. Please contact gym support.');
        }

        // Check feature permission from plan
        if ($requiredFeature !== null) {
            $features = [];
            if (!empty($tenant['features'])) {
                $features = json_decode($tenant['features'], true) ?: [];
            }
            if (!in_array('all_features', $features) && !in_array($requiredFeature, $features)) {
                ApiResponse::forbidden("This feature ({$requiredFeature}) is not available in your gym's current subscription plan.");
            }
        }

        // Update token last used timestamp
        DB::query("UPDATE member_tokens SET last_used_at = NOW() WHERE id = ?", [$tokenRecord['id']]);

        self::$currentTokenId = (int)$tokenRecord['id'];
        self::$authenticatedTenant = $tenant;
        self::$authenticatedMember = $member;
        self::$authenticatedUser = $user;

        return [
            'tenant' => $tenant,
            'member' => $member,
            'user' => $user,
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'user_id' => $user ? (int)$user['id'] : 0,
            'token_id' => (int)$tokenRecord['id']
        ];
    }

    /**
     * Same checks as authenticate(), but never ends the request: returns null when no token is sent
     * or it is not a valid, unblocked member session. For endpoints that also serve logged-out callers
     * (a 401 here would make the app run its session-expiry logout).
     */
    public static function tryAuthenticate() {
        $token = self::extractToken();
        if ($token === '') {
            return null;
        }

        $tokenRecord = DB::fetchOne("SELECT * FROM member_tokens WHERE token_hash = ? LIMIT 1", [hash('sha256', $token)]);
        if (!$tokenRecord) {
            return null;
        }
        $expRaw = (string)($tokenRecord['expires_at'] ?? '');
        $expTime = ($expRaw !== '' && $expRaw !== '0000-00-00 00:00:00') ? strtotime($expRaw . ' UTC') : false;
        if ($expTime === false || $expTime < time()) {
            return null;
        }

        $tenantId = (int)$tokenRecord['tenant_id'];
        $memberId = (int)$tokenRecord['member_id'];
        $userId = (int)$tokenRecord['user_id'];
        if ($tenantId <= 0 || $memberId <= 0) {
            return null;
        }

        $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant || api_tenant_block_reason($tenant) !== null) {
            return null;
        }

        $member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        if (!$member || api_member_is_blocked($member)) {
            return null;
        }

        $user = null;
        if ($userId > 0) {
            $user = DB::fetchOne("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND role = 'member'", [$userId, $tenantId]);
            if ($user && !empty($user['member_id']) && (int)$user['member_id'] !== $memberId) {
                $user = null;
            }
        }
        if ($user && strtolower((string)$user['status']) !== 'active') {
            return null;
        }

        return [
            'tenant' => $tenant,
            'member' => $member,
            'user' => $user,
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'user_id' => $user ? (int)$user['id'] : 0,
            'token_id' => (int)$tokenRecord['id']
        ];
    }

    public static function getCurrentTokenId() {
        return self::$currentTokenId;
    }

    public static function getTenant() {
        return self::$authenticatedTenant;
    }

    public static function getMember() {
        return self::$authenticatedMember;
    }

    public static function getUser() {
        return self::$authenticatedUser;
    }

    public static function getTenantId() {
        return (int)self::$authenticatedTenant['id'];
    }

    public static function getMemberId() {
        return (int)self::$authenticatedMember['user_id'];
    }
}

/**
 * LEFT JOIN that resolves member_assigned_plans.trainer_id to the trainer's staffs row ($staffAlias).
 * trainer_id is written as users.id by the admin app and by web logins from the users table, but as
 * staffs.user_id by legacy staff logins: a staff-linked users row (users.staff_id) with that id wins,
 * otherwise the id is taken as staffs.user_id (the only reading older rows ever had).
 */
function api_trainer_join_sql($planAlias = 'ap', $staffAlias = 's') {
    if (api_column_exists('users', 'staff_id')) {
        $u = $staffAlias . '_link';
        return "LEFT JOIN users {$u} ON {$u}.id = {$planAlias}.trainer_id AND {$u}.tenant_id = {$planAlias}.tenant_id
                    AND {$u}.role <> 'member' AND {$u}.staff_id IS NOT NULL AND {$u}.staff_id > 0
                 LEFT JOIN staffs {$staffAlias} ON {$staffAlias}.tenant_id = {$planAlias}.tenant_id
                    AND {$staffAlias}.user_id = COALESCE({$u}.staff_id, {$planAlias}.trainer_id)";
    }
    return "LEFT JOIN staffs {$staffAlias} ON {$planAlias}.trainer_id = {$staffAlias}.user_id AND {$staffAlias}.tenant_id = {$planAlias}.tenant_id";
}

/**
 * The trainer's staffs row for a member_assigned_plans.trainer_id (same rules as api_trainer_join_sql)
 */
function api_find_plan_trainer($tenantId, $trainerId, $columns = '*') {
    $trainerId = (int)$trainerId;
    if ($trainerId <= 0) return null;
    if (api_column_exists('users', 'staff_id')) {
        $linked = (int)DB::fetchValue(
            "SELECT staff_id FROM users WHERE id = ? AND tenant_id = ? AND role <> 'member' AND staff_id IS NOT NULL AND staff_id > 0 LIMIT 1",
            [$trainerId, (int)$tenantId]
        );
        if ($linked > 0) {
            $trainerId = $linked;
        }
    }
    return DB::fetchOne("SELECT {$columns} FROM staffs WHERE user_id = ? AND tenant_id = ? AND status = 'active'", [$trainerId, (int)$tenantId]);
}
