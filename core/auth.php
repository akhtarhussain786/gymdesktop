<?php
/**
 * Core Authentication & RBAC Engine
 * Supports Super Admin, Gym Admin, Staff, Trainer, and Member roles with CSRF protection
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/helpers.php';

class Auth {
    /**
     * Verify a password against a stored value.
     * Accepts bcrypt/argon hashes, plus legacy md5 / plaintext ONLY as a one-time upgrade path.
     * $needsRehash is set true when the stored value should be replaced with a modern hash.
     */
    public static function verifyPassword($password, $stored, &$needsRehash = false) {
        $needsRehash = false;
        $password = (string)$password;
        $stored = (string)($stored ?? '');
        if ($stored === '' || $password === '') {
            return false;
        }

        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            if (password_verify($password, $stored)) {
                $needsRehash = password_needs_rehash($stored, PASSWORD_DEFAULT);
                return true;
            }
            return false; // Never compare a modern hash as plaintext (prevents pass-the-hash)
        }

        // Legacy md5 hash
        if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
            if (hash_equals(strtolower($stored), md5($password))) {
                $needsRehash = true;
                return true;
            }
            return false;
        }

        // Legacy plaintext
        if (hash_equals($stored, $password)) {
            $needsRehash = true;
            return true;
        }
        return false;
    }

    /**
     * Upgrade a legacy/outdated password to bcrypt in the given table (only when the column can hold it)
     */
    private static function upgradePasswordHash($table, $pkColumn, $id, $password) {
        if (!in_array($table, ['users', 'admin', 'staffs', 'members'], true) || empty($id)) {
            return;
        }
        $col = DB::fetchOne("SHOW COLUMNS FROM `{$table}` LIKE 'password'");
        $type = strtolower($col['Type'] ?? '');
        $canHold = (strpos($type, 'text') !== false);
        if (!$canHold && preg_match('/char\((\d+)\)/', $type, $m)) {
            $canHold = ((int)$m[1] >= 60);
        }
        if (!$canHold) {
            error_log("Auth: cannot upgrade legacy password hash for {$table}.{$pkColumn}={$id} (column too short)");
            return;
        }
        DB::update($table, ['password' => password_hash($password, PASSWORD_DEFAULT)], "`{$pkColumn}` = ?", [$id]);
    }

    /**
     * Normalize role aliases stored in the users.role enum
     */
    private static function normalizeRole($role) {
        $role = strtolower(trim((string)$role));
        if ($role === 'superadmin') return 'super_admin';
        if ($role === 'admin') return 'gym_admin';
        return $role;
    }

    /**
     * Ensure a gym tenant exists and is allowed to sign in (fail closed)
     * Returns null when allowed, or an error message string.
     */
    private static function tenantLoginError($tenantId) {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) {
            return 'Your account is not linked to a gym. Please contact support.';
        }
        $tenant = DB::fetchOne("SELECT id, status FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            return 'Your gym account could not be found. Please contact support.';
        }
        if (in_array($tenant['status'], ['suspended', 'inactive'], true)) {
            return 'Gym account is ' . $tenant['status'] . '. Please contact gym administration.';
        }
        return null;
    }

    /**
     * Brute-force throttle for web logins (shares the login_attempts table with the member API).
     * Returns false (never throttles) if the table is unavailable.
     */
    private static function loginThrottleReady() {
        static $ready = null;
        if ($ready === null) {
            DB::query("CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `scope` varchar(32) NOT NULL,
                `identifier` char(64) NOT NULL,
                `ip_address` varchar(64) DEFAULT NULL,
                `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_scope_ident_time` (`scope`, `identifier`, `attempted_at`),
                KEY `idx_attempted_at` (`attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = DB::fetchValue("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_attempts'") > 0;
        }
        return $ready;
    }

    private static function loginFailures($scope, $identifier, $windowSeconds) {
        return (int)DB::fetchValue(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND identifier = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)",
            [$scope, hash('sha256', $identifier), (int)$windowSeconds]
        );
    }

    private static function recordLoginFailure($scope, $identifier) {
        DB::query(
            "INSERT INTO login_attempts (scope, identifier, ip_address) VALUES (?, ?, ?)",
            [$scope, hash('sha256', $identifier), $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }

    /**
     * Authenticate a user by username/password across all roles, with brute-force throttling:
     * 5 failures per username+IP and 30 per IP within 15 minutes.
     */
    public static function attempt($username, $password, $expectedRole = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $userKey = strtolower(trim((string)$username)) . '|' . $ip;
        $throttle = self::loginThrottleReady();

        if ($throttle && (self::loginFailures('web_login_user', $userKey, 900) >= 5 || self::loginFailures('web_login_ip', $ip, 900) >= 30)) {
            return ['success' => false, 'message' => 'Too many failed login attempts. Please wait 15 minutes and try again.'];
        }

        $res = self::attemptCredentials($username, $password, $expectedRole);

        if ($throttle && empty($res['success'])) {
            self::recordLoginFailure('web_login_user', $userKey);
            self::recordLoginFailure('web_login_ip', $ip);
        }
        return $res;
    }

    private static function attemptCredentials($username, $password, $expectedRole = null) {
        $username = trim((string)$username);
        $password = (string)$password;
        if ($username === '' || $password === '') {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // 1. Unified users table. Usernames are only unique per gym, so collect every candidate
        //    and require exactly one account to match the supplied password.
        $candidates = DB::fetchAll("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)", [$username, $username]);
        $matched = [];
        foreach ($candidates as $cand) {
            $needsRehash = false;
            if (self::verifyPassword($password, $cand['password'], $needsRehash)) {
                $cand['_needs_rehash'] = $needsRehash;
                $matched[] = $cand;
            }
        }
        if (count($matched) > 1) {
            return ['success' => false, 'message' => 'Multiple accounts match these credentials. Please contact your gym administrator to use a unique username.'];
        }
        if (count($matched) === 1) {
            $user = $matched[0];
            if (!empty($user['_needs_rehash'])) {
                self::upgradePasswordHash('users', 'id', $user['id'], $password);
            }
            unset($user['_needs_rehash']);

            if (($user['status'] ?? 'active') !== 'active') {
                return ['success' => false, 'message' => 'Your account is ' . $user['status'] . '. Please contact support.'];
            }

            $role = self::normalizeRole($user['role'] ?? '');
            if ($role === '') {
                return ['success' => false, 'message' => 'Your account has no role assigned. Please contact support.'];
            }
            $user['role'] = $role;

            if ($role !== 'super_admin') {
                $tenantErr = self::tenantLoginError($user['tenant_id'] ?? 0);
                if ($tenantErr) {
                    return ['success' => false, 'message' => $tenantErr];
                }
            } else {
                $user['tenant_id'] = null;
            }

            $user['auth_source'] = 'users';
            $user['account_id'] = $user['id'];
            // Member portal pages key off members.user_id, so map member logins to their member record
            if ($role === 'member') {
                if (!empty($user['member_id'])) {
                    $user['user_id'] = (int)$user['member_id'];
                } else {
                    $mRow = DB::fetchOne("SELECT user_id FROM members WHERE (LOWER(username) = LOWER(?) OR (email IS NOT NULL AND email != '' AND LOWER(email) = LOWER(?))) AND tenant_id = ?", [$user['username'], $user['email'] ?? $user['username'], (int)($user['tenant_id'] ?? 0)]);
                    if ($mRow && !empty($mRow['user_id'])) {
                        $user['user_id'] = (int)$mRow['user_id'];
                        @DB::update('users', ['member_id' => $user['user_id']], 'id = ?', [$user['id']]);
                    } else {
                        $user['user_id'] = $user['id'];
                    }
                }
            } else {
                $user['user_id'] = $user['id'];
            }

            self::loginUser($user);
            return ['success' => true, 'user' => $user];
        }

        // 2. Legacy check: admin table
        // (the legacy single-gym `admin` table only exists on databases upgraded from the old template)
        static $hasLegacyAdmin = null;
        if ($hasLegacyAdmin === null) {
            $hasLegacyAdmin = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin'") > 0;
        }
        $admins = $hasLegacyAdmin ? DB::fetchAll("SELECT * FROM admin WHERE username = ?", [$username]) : [];
        $adminMatches = [];
        foreach ($admins as $a) {
            $nr = false;
            if (self::verifyPassword($password, $a['password'] ?? '', $nr)) {
                $a['_needs_rehash'] = $nr;
                $adminMatches[] = $a;
            }
        }
        if (count($adminMatches) > 1) {
            return ['success' => false, 'message' => 'Multiple accounts match these credentials. Please contact support.'];
        }
        if (count($adminMatches) === 1) {
            $admin = $adminMatches[0];
            if (!empty($admin['_needs_rehash'])) {
                self::upgradePasswordHash('admin', 'user_id', $admin['user_id'] ?? 0, $password);
            }
            $role = ($admin['username'] === 'superadmin') ? 'super_admin' : 'gym_admin';
            $tenantId = ($role === 'super_admin') ? null : (int)($admin['tenant_id'] ?? 0);
            if ($role !== 'super_admin') {
                $tenantErr = self::tenantLoginError($tenantId);
                if ($tenantErr) {
                    return ['success' => false, 'message' => $tenantErr];
                }
            }
            $sessionUser = [
                'id' => $admin['user_id'],
                'user_id' => $admin['user_id'],
                'account_id' => $admin['user_id'],
                'auth_source' => 'admin',
                'username' => $admin['username'],
                'fullname' => $admin['name'] ?? 'Administrator',
                'role' => $role,
                'tenant_id' => $tenantId,
                'branch_id' => $admin['branch_id'] ?? null
            ];
            self::loginUser($sessionUser);
            return ['success' => true, 'user' => $sessionUser];
        }

        // 3. Legacy check: staffs table
        $staffRows = DB::fetchAll("SELECT * FROM staffs WHERE username = ?", [$username]);
        $staffMatches = [];
        foreach ($staffRows as $st) {
            $nr = false;
            if (self::verifyPassword($password, $st['password'] ?? '', $nr)) {
                $st['_needs_rehash'] = $nr;
                $staffMatches[] = $st;
            }
        }
        if (count($staffMatches) > 1) {
            return ['success' => false, 'message' => 'Multiple accounts match these credentials. Please contact your gym administrator to use a unique username.'];
        }
        if (count($staffMatches) === 1) {
            $staff = $staffMatches[0];
            if (!empty($staff['_needs_rehash'])) {
                self::upgradePasswordHash('staffs', 'user_id', $staff['user_id'], $password);
            }
            if (isset($staff['status']) && strtolower($staff['status']) !== 'active') {
                return ['success' => false, 'message' => 'Your account is ' . $staff['status'] . '. Please contact your gym administrator.'];
            }
            $tenantErr = self::tenantLoginError($staff['tenant_id'] ?? 0);
            if ($tenantErr) {
                return ['success' => false, 'message' => $tenantErr];
            }
            $designation = strtolower($staff['designation'] ?? '');
            $role = ($designation === 'trainer') ? 'trainer' : 'staff';
            $sessionUser = [
                'id' => $staff['user_id'],
                'user_id' => $staff['user_id'],
                'account_id' => $staff['user_id'],
                'auth_source' => 'staffs',
                'username' => $staff['username'],
                'fullname' => $staff['fullname'],
                'designation' => $staff['designation'],
                'role' => $role,
                'tenant_id' => (int)$staff['tenant_id'],
                'branch_id' => $staff['branch_id'] ?? null
            ];
            self::loginUser($sessionUser);
            return ['success' => true, 'user' => $sessionUser];
        }

        // 4. Legacy check: members table
        $memberRows = DB::fetchAll("SELECT * FROM members WHERE username = ?", [$username]);
        $memberMatches = [];
        foreach ($memberRows as $mr) {
            $nr = false;
            if (self::verifyPassword($password, $mr['password'] ?? '', $nr)) {
                $mr['_needs_rehash'] = $nr;
                $memberMatches[] = $mr;
            }
        }
        if (count($memberMatches) > 1) {
            return ['success' => false, 'message' => 'Multiple accounts match these credentials. Please contact your gym administrator to use a unique username.'];
        }
        if (count($memberMatches) === 1) {
            $member = $memberMatches[0];
            if (!empty($member['_needs_rehash'])) {
                self::upgradePasswordHash('members', 'user_id', $member['user_id'], $password);
            }
            $tenantErr = self::tenantLoginError($member['tenant_id'] ?? 0);
            if ($tenantErr) {
                return ['success' => false, 'message' => $tenantErr];
            }
            $sessionUser = [
                'id' => $member['user_id'],
                'user_id' => $member['user_id'],
                'account_id' => $member['user_id'],
                'auth_source' => 'members',
                'username' => $member['username'],
                'fullname' => $member['fullname'],
                'role' => 'member',
                'tenant_id' => (int)$member['tenant_id'],
                'branch_id' => $member['branch_id'] ?? null
            ];
            self::loginUser($sessionUser);
            return ['success' => true, 'user' => $sessionUser];
        }

        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    /**
     * Store user in session
     */
    public static function loginUser($user) {
        $role = self::normalizeRole($user['role'] ?? '');
        $tenantId = ($role === 'super_admin') ? null : (int)($user['tenant_id'] ?? 0);
        if ($role === '' || ($role !== 'super_admin' && $tenantId <= 0)) {
            // Fail closed: never create a session without a role / tenant context
            throw new RuntimeException('Refusing to create session without role or tenant context.');
        }

        // Drop any pre-login session state (prevents session fixation & stale impersonation data)
        $_SESSION = [];
        @session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'] ?? $user['id'];
        $_SESSION['id'] = $_SESSION['user_id'];
        $_SESSION['account_id'] = $user['account_id'] ?? ($user['id'] ?? $_SESSION['user_id']);
        $_SESSION['auth_source'] = $user['auth_source'] ?? 'users';
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['name'] ?? 'User';
        $_SESSION['role'] = $role;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['branch_id'] = $user['branch_id'] ?? null;
        $_SESSION['avatar'] = $user['avatar'] ?? null;
        $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);
        $_SESSION['logged_in_at'] = time();

        self::auditLog('LOGIN', 'User ' . $user['username'] . ' logged in as ' . $_SESSION['role']);
    }

    /**
     * Check if user is authenticated
     */
    public static function check() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Home page for a given role (used to avoid redirect loops on unauthorized access)
     */
    public static function homeFor($role) {
        switch ($role) {
            case 'super_admin': return base_url('/superadmin/index');
            case 'member':      return base_url('/customer/pages/index');
            case 'trainer':     return base_url('/trainer/index');
            default:            return base_url('/admin/index');
        }
    }

    /**
     * Destroy the session and send the user to the login page (fail closed)
     */
    private static function forceLogout($message = 'Your session has expired. Please sign in again.') {
        self::logout();
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
            set_flash('error', $message);
        }
        header('Location: ' . base_url('/index2'));
        exit();
    }

    /**
     * Re-validate the logged-in account against the database on every protected request
     */
    private static function revalidateSession() {
        $source = $_SESSION['auth_source'] ?? null;
        $accountId = (int)($_SESSION['account_id'] ?? 0);
        if (!$source || $accountId <= 0) {
            return false; // Session created before hardening or tampered: require fresh login
        }

        // While impersonating, the real identity is the super admin stored in the impersonator record
        if (!empty($_SESSION['impersonator'])) {
            $imp = $_SESSION['impersonator'];
            if (($imp['role'] ?? '') !== 'super_admin') return false;
            if (($imp['auth_source'] ?? '') === 'users') {
                $row = DB::fetchOne("SELECT status, role FROM users WHERE id = ?", [(int)($imp['account_id'] ?? 0)]);
                if (!$row || $row['status'] !== 'active' || self::normalizeRole($row['role']) !== 'super_admin') return false;
            }
            return true;
        }

        if ($source === 'users') {
            $row = DB::fetchOne("SELECT status, role, tenant_id FROM users WHERE id = ?", [$accountId]);
            if (!$row || ($row['status'] ?? '') !== 'active') return false;
            if (self::normalizeRole($row['role']) !== ($_SESSION['role'] ?? '')) return false;
            if (($_SESSION['role'] ?? '') !== 'super_admin' && (int)$row['tenant_id'] !== (int)($_SESSION['tenant_id'] ?? 0)) return false;
            return true;
        }
        if ($source === 'staffs') {
            $row = DB::fetchOne("SELECT status FROM staffs WHERE user_id = ? AND tenant_id = ?", [$accountId, (int)($_SESSION['tenant_id'] ?? 0)]);
            return $row && strtolower($row['status'] ?? 'active') === 'active';
        }
        if ($source === 'members') {
            $row = DB::fetchOne("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$accountId, (int)($_SESSION['tenant_id'] ?? 0)]);
            return (bool)$row;
        }
        if ($source === 'admin') {
            return true;
        }
        return false;
    }

    /**
     * Require authentication or redirect
     */
    public static function requireAuth($allowedRoles = []) {
        if (!self::check()) {
            header('Location: ' . base_url('/index2'));
            exit();
        }

        $currentRole = $_SESSION['role'] ?? '';
        if ($currentRole === '') {
            self::forceLogout();
        }

        if (!self::revalidateSession()) {
            self::forceLogout('Your account access has changed. Please sign in again.');
        }

        // Gym-scoped roles must belong to an existing, non-suspended tenant
        if ($currentRole !== 'super_admin') {
            if ((int)($_SESSION['tenant_id'] ?? 0) <= 0) {
                self::forceLogout();
            }
            $tenantStatus = DB::fetchValue("SELECT status FROM tenants WHERE id = ?", [(int)$_SESSION['tenant_id']]);
            if ($tenantStatus === null) {
                self::forceLogout('Your gym account could not be found. Please contact support.');
            }
            if (empty($_SESSION['impersonator']) && in_array($tenantStatus, ['suspended', 'inactive'], true)) {
                self::forceLogout('Gym account is ' . $tenantStatus . '. Please contact gym administration.');
            }
        }

        // Force password change on first login before accessing any protected page
        if (!empty($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($script !== 'change-password.php' && $script !== 'logout.php') {
                header('Location: ' . base_url('/change-password'));
                exit();
            }
        }

        if (!empty($allowedRoles)) {
            if (is_string($allowedRoles)) $allowedRoles = [$allowedRoles];
            if (!in_array($currentRole, $allowedRoles, true)) {
                // Super Admin must impersonate a gym to use gym portals; others go to their own home
                $target = self::homeFor($currentRole);
                $sep = (strpos($target, '?') === false) ? '?' : '&';
                header('Location: ' . $target . $sep . 'error=unauthorized');
                exit();
            }
        }
    }

    /**
     * Get currently logged in user info
     */
    public static function user() {
        if (!self::check()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'account_id' => $_SESSION['account_id'] ?? $_SESSION['user_id'],
            'auth_source' => $_SESSION['auth_source'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'fullname' => $_SESSION['fullname'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'branch_id' => $_SESSION['branch_id'] ?? null,
            'avatar' => $_SESSION['avatar'] ?? null
        ];
    }

    /**
     * Log user out
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            self::auditLog('LOGOUT', 'User ' . ($_SESSION['username'] ?? '') . ' logged out');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * CSRF token generator & validator
     */
    public static function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getCsrfToken() {
        return self::csrfToken();
    }

    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function verifyCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (empty($token) || !is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                if (!headers_sent()) {
                    http_response_code(403);
                }
                die("<div style='font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:8px;'>
                    <h3>Security Error</h3>
                    <p>Invalid or expired CSRF token. Please refresh and try again.</p>
                </div>");
            }
        }
    }

    /**
     * Audit logger
     */
    public static function auditLog($action, $description, $tenantId = null) {
        // Attribute to the explicit tenant, else the session tenant; platform (super admin) actions stay NULL
        if ($tenantId === null) {
            $tenantId = !empty($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
        }
        $userId = $_SESSION['account_id'] ?? $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!empty($_SESSION['impersonator']['username'])) {
            $description = '[impersonated by super admin ' . $_SESSION['impersonator']['username'] . '] ' . $description;
            $userId = $_SESSION['impersonator']['account_id'] ?? $userId;
        }

        @DB::insert('audit_logs', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
