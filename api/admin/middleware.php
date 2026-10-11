<?php
/**
 * Gym Admin Mobile API Security & Tenant Isolation Middleware
 * Enforces Token Authentication for Gym Admin and Staff Roles.
 */

require_once __DIR__ . '/../member/common.php';
require_once __DIR__ . '/../../core/auth.php';

class AdminAuthMiddleware {
    private static $authenticatedUser = null;
    private static $authenticatedTenant = null;
    private static $currentTokenId = null;

    /**
     * Extract the raw bearer token from headers or POST body.
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
     * Authenticate request and establish isolated gym admin context
     */
    public static function authenticate($allowQueryToken = false) {
        $token = self::extractToken($allowQueryToken);

        if (empty($token)) {
            ApiResponse::unauthorized('Missing Authorization header. Please log in as Admin.');
        }

        $tokenHash = hash('sha256', $token);

        // Find valid token in database
        $tokenRecord = DB::fetchOne(
            "SELECT * FROM member_tokens WHERE token_hash = ? LIMIT 1",
            [$tokenHash]
        );

        if (!$tokenRecord) {
            ApiResponse::unauthorized('Your admin session has expired or is invalid. Please log in again.');
        }

        // Verify expiration
        $expRaw = (string)($tokenRecord['expires_at'] ?? '');
        $expTime = ($expRaw !== '' && $expRaw !== '0000-00-00 00:00:00') ? strtotime($expRaw . ' UTC') : false;
        if ($expTime === false || $expTime < time()) {
            DB::query("DELETE FROM member_tokens WHERE id = ?", [$tokenRecord['id']]);
            ApiResponse::unauthorized('Your session has expired. Please log in again.');
        }

        $tenantId = (int)$tokenRecord['tenant_id'];
        $userId = (int)$tokenRecord['user_id'];

        // Verify user exists and has admin / staff role
        $user = DB::fetchOne("SELECT * FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$user) {
            ApiResponse::unauthorized('Admin user account not found.');
        }

        $role = strtolower((string)$user['role']);
        if (!in_array($role, ['gym_admin', 'staff', 'super_admin', 'trainer'], true)) {
            ApiResponse::forbidden('Access denied. This feature is for Gym Admins and Staff only.');
        }

        if (strtolower((string)$user['status']) !== 'active') {
            ApiResponse::forbidden('Your account has been deactivated.');
        }

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

        // Suspended / inactive gyms cannot use the app at all (same rule as the web login)
        $tenantStatus = strtolower((string)($tenant['status'] ?? 'active'));
        if (in_array($tenantStatus, ['suspended', 'inactive'], true)) {
            ApiResponse::forbidden('This gym account is currently ' . $tenantStatus . '. Please contact support.');
        }

        // Set global tenant context for helpers
        Tenant::setTenantId($tenantId);
        Tenant::setCurrent($tenant);

        // Apply Gym local time
        api_apply_tenant_timezone($tenant);

        // Per-endpoint role rules (mirror the web panel) and read-only mode for expired gyms
        self::enforceEndpointPolicy($role);

        // Update token last used
        DB::query("UPDATE member_tokens SET last_used_at = NOW() WHERE id = ?", [$tokenRecord['id']]);

        self::$authenticatedUser = $user;
        self::$authenticatedTenant = $tenant;
        self::$currentTokenId = $tokenRecord['id'];

        return [
            'tenant' => $tenant,
            'user' => $user,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role' => $role,
            'token_id' => (int)$tokenRecord['id']
        ];
    }

    const ROLES_OWNER = ['gym_admin', 'super_admin'];
    const ROLES_MANAGERS = ['gym_admin', 'staff', 'super_admin'];

    /**
     * Which roles may read (GET) / change (POST, PUT, DELETE) each endpoint. Endpoints not listed are open to
     * every gym role. Endpoints with several actions behind one method (subscription, notifications,
     * edit_member delete) additionally check inside the endpoint with requireRole().
     */
    private static function endpointPolicy() {
        return [
            'staffs.php'          => ['read' => null,                  'write' => self::ROLES_OWNER],
            'settings.php'        => ['read' => null,                  'write' => self::ROLES_OWNER],
            'rates.php'           => ['read' => null,                  'write' => self::ROLES_OWNER],
            'expenses.php'        => ['read' => self::ROLES_OWNER,     'write' => self::ROLES_OWNER],
            'reports.php'         => ['read' => self::ROLES_OWNER,     'write' => self::ROLES_OWNER],
            'transactions.php'    => ['read' => self::ROLES_MANAGERS,  'write' => self::ROLES_MANAGERS],
            'add_member.php'      => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'edit_member.php'     => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'collect_payment.php' => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'classes.php'         => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'equipment.php'       => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'announcements.php'   => ['read' => null,                  'write' => self::ROLES_MANAGERS],
            'inquiries.php'       => ['read' => null,                  'write' => self::ROLES_MANAGERS],
        ];
    }

    private static function enforceEndpointPolicy($role) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $isWrite = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        $policy = self::endpointPolicy()[$script] ?? null;
        if ($policy) {
            $allowed = $isWrite ? $policy['write'] : $policy['read'];
            if ($allowed !== null && !in_array($role, $allowed, true)) {
                ApiResponse::forbidden('This action is only available to the gym owner' . ($allowed === self::ROLES_MANAGERS ? ' or staff' : '') . '.');
            }
        }

        // Expired (past grace) / cancelled gyms are read-only. Renewal (api/admin/subscription.php and the
        // api/subscription/* endpoints), push-token and notification endpoints handle this themselves
        // (renewing and marking notifications read must keep working).
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $isRenewalApi = (strpos($scriptPath, '/api/subscription/') !== false);
        if ($isWrite && !$isRenewalApi && !in_array($script, ['subscription.php', 'device_token.php', 'notifications.php'], true)) {
            self::requireWritable();
        }
    }

    /**
     * 403 unless the authenticated user has one of $roles
     */
    public static function requireRole(array $roles) {
        $user = self::$authenticatedUser;
        $role = strtolower((string)($user['role'] ?? ''));
        if (!in_array($role, $roles, true)) {
            ApiResponse::forbidden('This action is only available to the gym owner' . ($roles === self::ROLES_MANAGERS ? ' or staff' : '') . '.');
        }
    }

    /**
     * 403 when the gym is in read-only mode (platform subscription expired past grace / cancelled)
     */
    public static function requireWritable() {
        if (Tenant::isReadOnly()) {
            $sub = Tenant::getSubscriptionStatus();
            ApiResponse::error($sub['message'] ?: 'Your gym subscription has expired. Please renew to make changes.', 403, null, ['read_only' => true]);
        }
    }
}
