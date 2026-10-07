<?php
/**
 * Member API Common Helper & Response Standardizer
 */

// Handle CORS for Flutter Web / Native / Dev
// NOTE: This API authenticates exclusively with bearer tokens (headers / POST body), never cookies.
// A wildcard origin is therefore safe as long as Access-Control-Allow-Credentials is NEVER sent
// (browsers refuse to combine '*' with credentials). Native mobile apps ignore CORS entirely.
if (!headers_sent()) {
    @header('Access-Control-Allow-Origin: *');
    @header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    @header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Gym-Code, X-Auth-Token, X-Bearer-Token');
    @header_remove('Access-Control-Allow-Credentials');
    @header('Content-Type: application/json; charset=UTF-8');
    @header('X-Content-Type-Options: nosniff');
    @header('Cache-Control: no-store, no-cache, must-revalidate');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/tenant.php';
require_once __DIR__ . '/../../core/helpers.php';

class ApiResponse {
    public static function json($success, $message, $data = null, $errors = null, $statusCode = 200) {
        http_response_code($statusCode);
        $response = [
            'success' => (bool)$success,
            'message' => (string)$message,
            'data' => $data,
            'errors' => $errors,
            'timestamp' => time()
        ];
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }

    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        self::json(true, $message, $data, null, $statusCode);
    }

    public static function error($message = 'An error occurred', $statusCode = 400, $errors = null, $data = null) {
        self::json(false, $message, $data, $errors, $statusCode);
    }

    public static function notFound($message = 'Resource not found') {
        self::json(false, $message, null, null, 404);
    }

    public static function unauthorized($message = 'Authentication required or session expired') {
        self::json(false, $message, null, null, 401);
    }

    public static function forbidden($message = 'Access denied') {
        self::json(false, $message, null, null, 403);
    }
}

/**
 * Cross-platform header extractor
 */
function get_request_headers() {
    $headers = [];

    if (function_exists('getallheaders')) {
        $all = getallheaders();
        if (is_array($all)) {
            foreach ($all as $k => $v) {
                $headers[$k] = $v;
                $headers[strtolower($k)] = $v;
            }
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
            $headers[strtolower($header)] = $value;
            $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
        } elseif ($key === 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
            $headers['content-type'] = $value;
        } elseif ($key === 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
            $headers['content-length'] = $value;
        }
    }

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        $headers['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    return $headers;
}

/**
 * Helper to parse JSON or form POST request body
 */
function get_json_input() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

/**
 * Best-effort client IP (REMOTE_ADDR only; proxy headers are client-controlled and not trusted)
 */
function api_client_ip() {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Cached schema introspection helpers (live DB was built from incremental migrations,
 * so optional tables/columns must be probed before use to avoid SQL errors).
 */
function api_table_exists($table) {
    static $cache = [];
    if (!array_key_exists($table, $cache)) {
        $cache[$table] = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        ) > 0;
    }
    return $cache[$table];
}

function api_column_exists($table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        ) > 0;
    }
    return $cache[$key];
}

/**
 * Throttling store (login_attempts). Created lazily if the migration has not been applied.
 * Fails open (no throttling) only if the table cannot be created at all.
 */
function api_rate_limit_ready() {
    static $ready = null;
    if ($ready !== null) return $ready;
    if (!api_table_exists('login_attempts')) {
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
        $ready = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_attempts'"
        ) > 0;
        return $ready;
    }
    $ready = true;
    return $ready;
}

function api_rate_key($parts) {
    return hash('sha256', strtolower(implode('|', (array)$parts)));
}

/**
 * Returns true when the number of recorded attempts for (scope, key) within the window reaches $max.
 */
function api_rate_limited($scope, $key, $max, $windowSeconds) {
    if (!api_rate_limit_ready()) return false;
    $count = (int)DB::fetchValue(
        "SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND identifier = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)",
        [(string)$scope, (string)$key, (int)$windowSeconds]
    );
    return $count >= (int)$max;
}

function api_rate_record($scope, $key) {
    if (!api_rate_limit_ready()) return;
    DB::query(
        "INSERT INTO login_attempts (scope, identifier, ip_address) VALUES (?, ?, ?)",
        [(string)$scope, (string)$key, api_client_ip()]
    );
    // Opportunistic garbage collection (~1% of writes)
    if (random_int(1, 100) === 1) {
        DB::query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function api_rate_clear($scope, $key) {
    if (!api_rate_limit_ready()) return;
    DB::query("DELETE FROM login_attempts WHERE scope = ? AND identifier = ?", [(string)$scope, (string)$key]);
}

/**
 * Verify a password against a stored hash.
 * Returns 'ok' for a modern hash, 'rehash' when it matched a legacy md5/plaintext value
 * (or an outdated bcrypt cost) and must be upgraded immediately, or false.
 */
function api_verify_password($plain, $stored) {
    $stored = (string)$stored;
    $plain = (string)$plain;
    if ($stored === '' || $plain === '') return false;

    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        if (!password_verify($plain, $stored)) return false;
        return password_needs_rehash($stored, PASSWORD_DEFAULT) ? 'rehash' : 'ok';
    }

    // Legacy upgrade path only: unsalted md5 hex digest
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
        return hash_equals(strtolower($stored), md5($plain)) ? 'rehash' : false;
    }

    // Legacy upgrade path only: plaintext (never a crypt-style string)
    if ($stored[0] !== '$') {
        return hash_equals($stored, $plain) ? 'rehash' : false;
    }

    return false;
}

/**
 * Apply the gym's configured timezone so that "today", check-in times and
 * membership day counts follow the gym's local calendar.
 */
function api_apply_tenant_timezone($tenant) {
    $tz = trim((string)($tenant['timezone'] ?? ''));
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}

/**
 * Tenant (gym) access gate for the member app.
 * Mirrors Tenant::getSubscriptionStatus() rules (7-day grace after subscription_expiry) but works
 * on an explicit tenant row (Tenant::getCurrent() is session-bound and unusable for token auth).
 * Returns null when access is allowed, otherwise a user-facing message.
 */
function api_tenant_block_reason($tenant) {
    $status = strtolower((string)($tenant['status'] ?? 'active'));
    if (in_array($status, ['inactive', 'suspended', 'cancelled'], true)) {
        return 'This gym account is currently ' . ($status === 'inactive' ? 'inactive' : 'suspended') . '. Please contact gym administration.';
    }

    $expiryRaw = $tenant['subscription_expiry'] ?? null;
    if (!empty($expiryRaw) && strpos((string)$expiryRaw, '0000-00-00') !== 0) {
        $expiryTs = strtotime(substr((string)$expiryRaw, 0, 10));
        if ($expiryTs !== false) {
            $graceDays = 7;
            $graceEnd = date('Y-m-d', strtotime("+{$graceDays} days", $expiryTs));
            if (date('Y-m-d') > $graceEnd) {
                return "This gym's service subscription has expired. Please contact gym administration.";
            }
        }
    }
    return null;
}

/**
 * Member account gate: inactive / suspended / deleted members must not authenticate.
 * (An 'Expired' membership may still log in to view history and renew.)
 */
function api_member_is_blocked($member) {
    $status = strtolower(trim((string)($member['status'] ?? 'active')));
    return in_array($status, ['inactive', 'suspended', 'deleted', 'blocked', 'banned', 'disabled'], true);
}

/**
 * Compute the member's current membership window.
 * Source of truth: an active/started row in member_subscriptions (renewal engine).
 * Fallback: members.paid_date (= period start, see SubscriptionEngine::calculateNextStartDate) + plan months.
 * The expiry date is the LAST valid day (inclusive); days_remaining counts whole calendar days in gym time.
 */
function api_membership_window($tenantId, $member) {
    $today = date('Y-m-d');
    $start = null;
    $expiry = null;
    $planMonths = max(1, (int)($member['plan'] ?? 1));

    if (api_table_exists('member_subscriptions')) {
        $sub = DB::fetchOne(
            "SELECT start_date, expiry_date, plan_duration_snapshot FROM member_subscriptions
             WHERE tenant_id = ? AND member_id = ? AND status IN ('active','upcoming') AND start_date <= ?
             ORDER BY expiry_date DESC LIMIT 1",
            [(int)$tenantId, (int)$member['user_id'], $today]
        );
        if ($sub && !empty($sub['expiry_date'])) {
            $start = $sub['start_date'];
            $expiry = $sub['expiry_date'];
            $planMonths = max(1, (int)($sub['plan_duration_snapshot'] ?? $planMonths));
        }
    }

    if ($expiry === null) {
        $paid = $member['paid_date'] ?? null;
        if (empty($paid) || strpos((string)$paid, '0000-00-00') === 0) {
            $paid = (!empty($member['dor']) && strpos((string)$member['dor'], '0000-00-00') !== 0) ? $member['dor'] : null;
        }
        if ($paid && strtotime($paid) !== false) {
            $start = date('Y-m-d', strtotime($paid));
            $expiry = date('Y-m-d', strtotime("+{$planMonths} months", strtotime($start)));
        } else {
            // No payment on record: treat as not active rather than inventing a fresh period
            $start = $today;
            $expiry = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        }
    }

    $daysLeft = (int)(new DateTime($today))->diff(new DateTime($expiry))->format('%r%a');

    return [
        'start_date' => $start,
        'expiry_date' => $expiry,
        'plan_months' => $planMonths,
        'days_left' => $daysLeft,
        'is_active' => $daysLeft >= 0
    ];
}
