<?php
/**
 * Core Database Engine
 * Centralized, Secure, PDO/MySQLi Hybrid with Prepared Statements & Transaction Support
 */

require_once __DIR__ . '/env.php';

/**
 * Start the PHP session with hardened cookie parameters (httponly, SameSite=Lax, secure on HTTPS)
 */
if (!function_exists('secure_session_start')) {
    function secure_session_start() {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }
        $isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookieParams['path'] ?: '/',
            'domain'   => $cookieParams['domain'] ?? '',
            'secure'   => $isHttpsRequest,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Stateless API endpoints (token / webhook based) never need a PHP session
$isApiRequest = (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false);
if (!$isApiRequest && PHP_SAPI !== 'cli') {
    secure_session_start();
}

class DB {
    public static $lastError = '';
    private static $connection = null;
    private static $columnCache = [];
    private static $config = [
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'name' => ''
    ];

    /**
     * Bump this whenever ensureSchema() gains new DDL so it re-runs once on every deployment
     */
    const SCHEMA_VERSION = '2026-10-11.2';

    /**
     * Get or initialize the MySQLi database connection
     */
    public static function connect() {
        if (self::$connection !== null) {
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        // Credentials come exclusively from the environment (.env / server env vars)
        self::$config['host'] = (string)env('DB_HOST', 'localhost');
        self::$config['user'] = (string)env('DB_USER', '');
        self::$config['pass'] = (string)env('DB_PASS', '');
        self::$config['name'] = (string)env('DB_NAME', '');

        $con = false;
        if (self::$config['user'] !== '' && self::$config['name'] !== '') {
            $hosts = array_unique([self::$config['host'], 'localhost', '127.0.0.1']);
            $dbNames = array_unique([self::$config['name']]);
            // If DB name has repeated cPanel prefix like ujvxfxta_ujvxfxta_gymsaas, add single prefix fallback ujvxfxta_gymsaas
            if (preg_match('/^([a-z0-9]+)_\1_(.+)$/i', self::$config['name'], $m)) {
                $dbNames[] = $m[1] . '_' . $m[2];
            }

            foreach ($hosts as $h) {
                foreach ($dbNames as $dbn) {
                    $con = @mysqli_connect($h, self::$config['user'], self::$config['pass'], $dbn);
                    if ($con) {
                        self::$config['host'] = $h;
                        self::$config['name'] = $dbn;
                        break 2;
                    }
                }
            }
        } else {
            error_log('DB Connection Error: DB_USER / DB_NAME are not configured in the environment.');
        }

        if (!$con) {
            $lastErr = mysqli_connect_error() ?: 'Unable to establish MySQL connection';
            $lastErrNo = mysqli_connect_errno();
            error_log("DB Connection Error [{$lastErrNo}]: {$lastErr}");
            if (!headers_sent()) {
                http_response_code(503);
            }
            // Connection details are only ever shown on the command line, never to web visitors
            $showDebug = (PHP_SAPI === 'cli');
            $debugInfo = '';
            if ($showDebug) {
                $debugInfo = "<div style='margin-top:16px;padding:14px;background:#fff;border:1px solid #fca5a5;border-radius:6px;font-family:monospace;font-size:13px;color:#7f1d1d;line-height:1.6;'>
                    <strong>MySQL Error:</strong> (" . htmlspecialchars((string)$lastErrNo) . ") " . htmlspecialchars($lastErr) . "<br>
                    <strong>Host:</strong> " . htmlspecialchars(self::$config['host']) . "<br>
                    <strong>User:</strong> " . htmlspecialchars(self::$config['user']) . "<br>
                    <strong>Database:</strong> " . htmlspecialchars(self::$config['name']) . "
                </div>";
            }
            die("<div style='font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;max-width:650px;'>
                <h2 style='margin-top:0;'>Service Temporarily Unavailable</h2>
                <p>We could not connect to the database. Please try again shortly.</p>
                {$debugInfo}
            </div>");
        }

        mysqli_set_charset($con, 'utf8mb4');
        self::$connection = $con;

        // Auto-run schema migrations only when the cached schema version marker is stale
        $markerFile = __DIR__ . '/../logs/.schema_version';
        $markerValue = self::SCHEMA_VERSION . '|' . md5(self::$config['host'] . '/' . self::$config['name']);
        $currentMarker = @file_get_contents($markerFile);
        if ($currentMarker === false || trim($currentMarker) !== $markerValue) {
            // Only one request runs the DDL; concurrent requests skip it instead of piling up on table locks
            $lockRes = mysqli_query($con, "SELECT GET_LOCK('gymsaas_ensure_schema', 0)");
            $gotLock = $lockRes && (int)(mysqli_fetch_row($lockRes)[0] ?? 0) === 1;
            if ($gotLock) {
                self::ensureSchema($con);
                $verify = mysqli_query($con, "SHOW TABLES LIKE 'tenants'");
                if ($verify && mysqli_num_rows($verify) > 0) {
                    @file_put_contents($markerFile, $markerValue, LOCK_EX);
                }
                mysqli_query($con, "SELECT RELEASE_LOCK('gymsaas_ensure_schema')");
            }
        }

        return self::$connection;
    }

    /**
     * Automatically ensure SaaS schema & email tracking is created if not present
     */
    private static function ensureSchema($con) {
        $freshInstall = false;
        $check = mysqli_query($con, "SHOW TABLES LIKE 'tenants'");
        if ($check && mysqli_num_rows($check) === 0) {
            $migration_path = __DIR__ . '/../database/migration_saas.sql';
            if (file_exists($migration_path)) {
                // The migration file changes sql_mode / time_zone for the session; restore them afterwards
                $modeRes = mysqli_query($con, "SELECT @@SESSION.sql_mode, @@SESSION.time_zone");
                $sessionVars = $modeRes ? mysqli_fetch_row($modeRes) : null;
                $sql = file_get_contents($migration_path);
                mysqli_multi_query($con, $sql);
                while (mysqli_more_results($con) && mysqli_next_result($con)) {
                    // flush multi_query buffers
                }
                if ($sessionVars) {
                    mysqli_query($con, "SET SESSION sql_mode = '" . mysqli_real_escape_string($con, (string)$sessionVars[0]) . "', time_zone = '" . mysqli_real_escape_string($con, (string)$sessionVars[1]) . "'");
                }
                $freshInstall = true;
            }
        }
        $checkSub = mysqli_query($con, "SHOW TABLES LIKE 'member_subscriptions'");
        if ($checkSub && mysqli_num_rows($checkSub) === 0) {
            $mig = __DIR__ . '/../database/migration_advance_renewals.sql';
            if (file_exists($mig)) {
                $sql = file_get_contents($mig);
                mysqli_multi_query($con, $sql);
                while (mysqli_more_results($con) && mysqli_next_result($con)) {}
            }
        }

        $checkEmail = mysqli_query($con, "SHOW TABLES LIKE 'email_delivery_logs'");
        if ($checkEmail && mysqli_num_rows($checkEmail) === 0) {
            $sql = "CREATE TABLE IF NOT EXISTS `email_delivery_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `recipient_email` varchar(150) NOT NULL,
              `email_type` varchar(50) NOT NULL,
              `order_ref` varchar(100) DEFAULT NULL,
              `tenant_id` int(11) DEFAULT NULL,
              `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
              `attempts` int(11) NOT NULL DEFAULT 1,
              `sent_at` datetime DEFAULT NULL,
              `last_attempt_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `failure_reason` text DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_order_ref` (`order_ref`),
              KEY `idx_recipient_email` (`recipient_email`),
              KEY `idx_email_type` (`email_type`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            mysqli_query($con, $sql);
        }

        $checkTokens = mysqli_query($con, "SHOW TABLES LIKE 'member_tokens'");
        if ($checkTokens && mysqli_num_rows($checkTokens) === 0) {
            $sqlTokens = "CREATE TABLE IF NOT EXISTS `member_tokens` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tenant_id` int(11) NOT NULL,
              `member_id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `token_hash` varchar(64) NOT NULL,
              `device_id` varchar(100) DEFAULT NULL,
              `device_name` varchar(100) DEFAULT NULL,
              `platform` varchar(20) DEFAULT 'android',
              `ip_address` varchar(45) DEFAULT NULL,
              `user_agent` text DEFAULT NULL,
              `last_used_at` datetime DEFAULT NULL,
              `expires_at` datetime NOT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_token_hash` (`token_hash`),
              KEY `idx_tenant_member` (`tenant_id`, `member_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            mysqli_query($con, $sqlTokens);
        }

        // Auto-create and sync invoices table
        $checkInvoices = mysqli_query($con, "SHOW TABLES LIKE 'invoices'");
        if ($checkInvoices && mysqli_num_rows($checkInvoices) === 0) {
            $sqlInvoices = "CREATE TABLE IF NOT EXISTS `invoices` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tenant_id` int(11) NOT NULL DEFAULT 1,
              `branch_id` int(11) DEFAULT 1,
              `invoice_number` varchar(50) NOT NULL,
              `member_id` int(11) NOT NULL,
              `payment_id` int(11) DEFAULT NULL,
              `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
              `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
              `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
              `plan_months` int(11) NOT NULL DEFAULT 1,
              `service_name` varchar(100) DEFAULT 'Gym Membership',
              `payment_method` varchar(50) DEFAULT 'Cash',
              `payment_date` date NOT NULL,
              `status` enum('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Paid',
              `transaction_ref` varchar(100) DEFAULT NULL,
              `notes` text DEFAULT NULL,
              `created_by` int(11) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `tenant_id` (`tenant_id`),
              KEY `member_id` (`member_id`),
              KEY `payment_id` (`payment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            mysqli_query($con, $sqlInvoices);
        } else {
            // Ensure essential columns exist in invoices table if table was created with older schema
            $colRes = mysqli_query($con, "SHOW COLUMNS FROM `invoices`");
            $existingCols = [];
            if ($colRes) {
                while ($c = mysqli_fetch_assoc($colRes)) {
                    $existingCols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('amount', $existingCols) && in_array('total_amount', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `amount` decimal(10,2) NOT NULL DEFAULT 0.00"); // no AFTER: payment_id may not exist yet
                self::ddl($con, "UPDATE `invoices` SET `amount` = `total_amount` WHERE `amount` = 0 AND `total_amount` > 0");
            }
            if (!in_array('total_amount', $existingCols) && in_array('amount', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
                self::ddl($con, "UPDATE `invoices` SET `total_amount` = `amount` WHERE `total_amount` = 0 AND `amount` > 0");
            }
            if (!in_array('branch_id', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `branch_id` int(11) DEFAULT 1 AFTER `tenant_id`");
            }
            if (!in_array('payment_id', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `payment_id` int(11) DEFAULT NULL AFTER `member_id`");
            }
            if (!in_array('paid_amount', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
            }
            if (!in_array('discount', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `discount` decimal(10,2) NOT NULL DEFAULT 0.00");
            }
            if (!in_array('plan_months', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `plan_months` int(11) NOT NULL DEFAULT 1");
            }
            if (!in_array('service_name', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `service_name` varchar(100) DEFAULT 'Gym Membership'");
            }
            if (!in_array('transaction_ref', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `transaction_ref` varchar(100) DEFAULT NULL");
            }
            if (!in_array('notes', $existingCols)) {
                self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `notes` text DEFAULT NULL");
            }
        }

        // Ensure essential columns exist in tenants table if table was created with older schema
        $tColRes = mysqli_query($con, "SHOW COLUMNS FROM `tenants`");
        $existingTCols = [];
        if ($tColRes) {
            while ($tc = mysqli_fetch_assoc($tColRes)) {
                $existingTCols[] = strtolower($tc['Field']);
            }
        }
        if (!in_array('gym_code', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `gym_code` varchar(50) DEFAULT NULL AFTER `id`");
            self::ddl($con, "ALTER TABLE `tenants` ADD INDEX `idx_gym_code` (`gym_code`)");
        }
        if (!in_array('name', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `name` varchar(150) DEFAULT NULL AFTER `gym_name`");
        }
        if (!in_array('gym_name', $existingTCols) && in_array('name', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `gym_name` varchar(150) DEFAULT NULL AFTER `id`");
            self::ddl($con, "UPDATE `tenants` SET `gym_name` = `name` WHERE `gym_name` IS NULL OR `gym_name` = ''");
        }
        if (!in_array('upi_id', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `upi_id` varchar(100) DEFAULT NULL AFTER `phone`");
        }
        if (!in_array('upi_qr', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `upi_qr` varchar(255) DEFAULT NULL AFTER `upi_id`");
        }
        if (!in_array('invoice_header', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `invoice_header` text DEFAULT NULL AFTER `upi_qr`");
        }
        if (!in_array('invoice_footer', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `invoice_footer` text DEFAULT NULL AFTER `invoice_header`");
        }
        if (!in_array('primary_color', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `primary_color` varchar(16) DEFAULT '#3b82f6'");
        }
        if (!in_array('secondary_color', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `secondary_color` varchar(16) DEFAULT '#10b981'");
        }
        if (!in_array('currency', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `currency` varchar(10) DEFAULT '₹'");
        }
        if (!in_array('timezone', $existingTCols)) {
            self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `timezone` varchar(64) DEFAULT 'Asia/Kolkata'");
        }

        // Ensure users.must_change_password exists (forced first-login password change)
        $uColRes = mysqli_query($con, "SHOW COLUMNS FROM `users` LIKE 'must_change_password'");
        if ($uColRes && mysqli_num_rows($uColRes) === 0) {
            self::ddl($con, "ALTER TABLE `users` ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`");
        }

        // Password reset tokens + request log (used for rate limiting)
        $checkResets = mysqli_query($con, "SHOW TABLES LIKE 'password_resets'");
        if (!$checkResets || mysqli_num_rows($checkResets) === 0) {
            $sqlResets = "CREATE TABLE IF NOT EXISTS `password_resets` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `identifier_hash` char(64) NOT NULL,
              `token_hash` char(64) DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `expires_at` datetime DEFAULT NULL,
              `used_at` datetime DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_token_hash` (`token_hash`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_identifier_created` (`identifier_hash`, `created_at`),
              KEY `idx_ip_created` (`ip_address`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            mysqli_query($con, $sqlResets);
        }

        // Ensure superadmin has updated email
        mysqli_query($con, "UPDATE `users` SET `email` = 'akhtarhussain132003@gmail.com' WHERE (`role` = 'super_admin' OR `username` = 'superadmin') AND (`email` IS NULL OR `email` = '' OR `email` = 'superadmin@fitisify.com')");

        // Ensure members columns for mobile dues & avatar
        $mColRes = mysqli_query($con, "SHOW COLUMNS FROM `members`");
        $existingMCols = [];
        if ($mColRes) {
            while ($mc = mysqli_fetch_assoc($mColRes)) {
                $existingMCols[] = strtolower($mc['Field']);
            }
        }
        if (!in_array('avatar', $existingMCols)) {
            self::ddl($con, "ALTER TABLE `members` ADD COLUMN `avatar` varchar(255) DEFAULT NULL");
        }
        if (!in_array('due_amount', $existingMCols)) {
            self::ddl($con, "ALTER TABLE `members` ADD COLUMN `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('due_date', $existingMCols)) {
            self::ddl($con, "ALTER TABLE `members` ADD COLUMN `due_date` date DEFAULT NULL");
        }
        if (!in_array('notes', $existingMCols)) {
            self::ddl($con, "ALTER TABLE `members` ADD COLUMN `notes` text DEFAULT NULL");
        }

        // Ensure users.avatar exists
        $uAvatarRes = mysqli_query($con, "SHOW COLUMNS FROM `users` LIKE 'avatar'");
        if ($uAvatarRes && mysqli_num_rows($uAvatarRes) === 0) {
            self::ddl($con, "ALTER TABLE `users` ADD COLUMN `avatar` varchar(255) DEFAULT NULL AFTER `phone`");
        }

        // Ensure invoices.due_date exists
        $iDueDateRes = mysqli_query($con, "SHOW COLUMNS FROM `invoices` LIKE 'due_date'");
        if ($iDueDateRes && mysqli_num_rows($iDueDateRes) === 0) {
            self::ddl($con, "ALTER TABLE `invoices` ADD COLUMN `due_date` date DEFAULT NULL AFTER `payment_date`");
        }

        // Status values the code writes must exist in the enum (installs differ: Paid/Partial/Unpaid vs paid/pending/partial/cancelled).
        // Only appends missing values, so existing rows and their spelling are untouched.
        self::ensureEnumValues($con, 'invoices', 'status', ['paid', 'partial', 'unpaid', 'pending', 'cancelled']);

        // Support tickets table (used by the member app; missing on installs upgraded from the legacy template)
        $checkInq = mysqli_query($con, "SHOW TABLES LIKE 'member_inquiries'");
        if ($checkInq && mysqli_num_rows($checkInq) === 0) {
            self::ddl($con, "CREATE TABLE IF NOT EXISTS `member_inquiries` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tenant_id` int(11) NOT NULL,
              `branch_id` int(11) DEFAULT 1,
              `member_id` int(11) NOT NULL,
              `category` varchar(50) NOT NULL DEFAULT 'General',
              `subject` varchar(150) NOT NULL,
              `message` text NOT NULL,
              `reply` text DEFAULT NULL,
              `status` enum('open','pending','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
              `replied_by` int(11) DEFAULT NULL,
              `replied_at` datetime DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_tenant_member` (`tenant_id`, `member_id`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            self::ensureEnumValues($con, 'member_inquiries', 'status', ['open', 'pending', 'in_progress', 'resolved', 'closed']);
        }

        // Reusable workout / diet plan templates (older installs store per-member plans without these columns)
        foreach (['workout_plans', 'diet_plans'] as $planTable) {
            $pCols = self::columnsOf($con, $planTable);
            if ($pCols === null) {
                continue;
            }
            if (!isset($pCols['name'])) {
                self::ddl($con, "ALTER TABLE `{$planTable}` ADD COLUMN `name` varchar(150) DEFAULT NULL");
                if (isset($pCols['plan_name'])) {
                    self::ddl($con, "UPDATE `{$planTable}` SET `name` = `plan_name` WHERE `name` IS NULL");
                }
            }
            if (!isset($pCols['description'])) {
                self::ddl($con, "ALTER TABLE `{$planTable}` ADD COLUMN `description` text DEFAULT NULL");
            }
            if (!isset($pCols['created_by'])) {
                self::ddl($con, "ALTER TABLE `{$planTable}` ADD COLUMN `created_by` int(11) DEFAULT NULL");
            }
            // Template rows have no member / legacy plan_name: allow NULL instead of failing the insert
            $jsonCol = ($planTable === 'workout_plans') ? 'schedule_json' : 'meals_json';
            foreach (['member_id', 'plan_name', $jsonCol] as $relaxCol) {
                if (isset($pCols[$relaxCol]) && $pCols[$relaxCol]['Null'] === 'NO' && $pCols[$relaxCol]['Default'] === null) {
                    self::ddl($con, "ALTER TABLE `{$planTable}` MODIFY `{$relaxCol}` {$pCols[$relaxCol]['Type']} NULL");
                }
            }
        }

        // Push device registry columns used by the notification engine (portable: no "ADD COLUMN IF NOT EXISTS")
        $dCols = self::columnsOf($con, 'device_tokens');
        if ($dCols !== null) {
            if (!isset($dCols['user_id'])) {
                self::ddl($con, "ALTER TABLE `device_tokens` ADD COLUMN `user_id` int(11) DEFAULT NULL");
            }
            if (!isset($dCols['user_role'])) {
                self::ddl($con, "ALTER TABLE `device_tokens` ADD COLUMN `user_role` varchar(30) DEFAULT 'member'");
            }
            if (!isset($dCols['status'])) {
                self::ddl($con, "ALTER TABLE `device_tokens` ADD COLUMN `status` varchar(20) DEFAULT 'active'");
            }
            if (!isset($dCols['last_active_at'])) {
                self::ddl($con, "ALTER TABLE `device_tokens` ADD COLUMN `last_active_at` datetime DEFAULT NULL");
            }
        }

        // Paid gym sign-up (Cashfree onboarding) tables & columns from database/migration_onboarding.sql,
        // which was never applied automatically: without them paid registrations and webhooks fail silently.
        self::ddl($con, "CREATE TABLE IF NOT EXISTS `pending_onboardings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `reg_ref` varchar(60) NOT NULL,
          `gym_name` varchar(150) NOT NULL,
          `slug` varchar(150) NOT NULL,
          `owner_name` varchar(100) NOT NULL,
          `email` varchar(100) NOT NULL,
          `phone` varchar(30) NOT NULL,
          `address` varchar(255) DEFAULT NULL,
          `country` varchar(100) DEFAULT 'India',
          `state` varchar(100) DEFAULT NULL,
          `city` varchar(100) DEFAULT NULL,
          `currency` varchar(10) DEFAULT '₹',
          `timezone` varchar(50) DEFAULT 'Asia/Kolkata',
          `plan_id` int(11) NOT NULL,
          `billing_cycle` varchar(20) DEFAULT 'monthly',
          `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
          `order_id` varchar(100) DEFAULT NULL,
          `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_reg_ref` (`reg_ref`),
          KEY `idx_order_id` (`order_id`),
          KEY `idx_email` (`email`),
          KEY `idx_phone` (`phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::ddl($con, "CREATE TABLE IF NOT EXISTS `webhook_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `event_type` varchar(100) DEFAULT NULL,
          `order_id` varchar(100) DEFAULT NULL,
          `signature` varchar(255) DEFAULT NULL,
          `payload` longtext DEFAULT NULL,
          `status` varchar(50) DEFAULT 'received',
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $spCols = self::columnsOf($con, 'saas_payments');
        if ($spCols !== null) {
            $spAdd = [
                'reg_ref' => "varchar(60) DEFAULT NULL",
                'cf_order_id' => "varchar(100) DEFAULT NULL",
                'cf_payment_id' => "varchar(100) DEFAULT NULL",
                'email_status' => "enum('pending','sent','failed') NOT NULL DEFAULT 'pending'",
                'email_sent_at' => "datetime DEFAULT NULL",
                'failure_reason' => "text DEFAULT NULL",
                'webhook_payload' => "longtext DEFAULT NULL",
                'webhook_status' => "varchar(50) DEFAULT NULL",
                'approved_by' => "int(11) DEFAULT NULL",
            ];
            foreach ($spAdd as $col => $def) {
                if (!isset($spCols[$col])) {
                    self::ddl($con, "ALTER TABLE `saas_payments` ADD COLUMN `{$col}` {$def}");
                }
            }
        }
        $tCols = self::columnsOf($con, 'tenants');
        if ($tCols !== null) {
            foreach (['country' => "varchar(100) DEFAULT 'India'", 'state' => "varchar(100) DEFAULT NULL", 'city' => "varchar(100) DEFAULT NULL"] as $col => $def) {
                if (!isset($tCols[$col])) {
                    self::ddl($con, "ALTER TABLE `tenants` ADD COLUMN `{$col}` {$def}");
                }
            }
        }
        // Member renewal orders store their duration (otherwise it is inferred from amount / rate)
        $mpCols = self::columnsOf($con, 'membership_payments');
        if ($mpCols !== null && !isset($mpCols['plan_months'])) {
            self::ddl($con, "ALTER TABLE `membership_payments` ADD COLUMN `plan_months` int(11) DEFAULT NULL");
        }

        // Platform key/value settings (created here so set_platform_setting() never needs DDL mid-transaction)
        self::ddl($con, "CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `setting_key` varchar(100) UNIQUE, `setting_value` longtext)");

        // Staff login link (users.staff_id) so password changes / deletions reach both account rows
        $uCols = self::columnsOf($con, 'users');
        if ($uCols !== null && !isset($uCols['staff_id'])) {
            self::ddl($con, "ALTER TABLE `users` ADD COLUMN `staff_id` int(11) DEFAULT NULL");
        }

        // A brand-new install seeds `superadmin` / `admin` from migration_saas.sql with a publicly known password.
        // Replace it with a random one that must be changed at first login, and leave it in a non-public file.
        if ($freshInstall) {
            self::rotateSeededPasswords($con);
        }
    }

    /**
     * Run a DDL / maintenance statement, logging (not hiding) failures
     */
    private static function ddl($con, $sql) {
        $ok = mysqli_query($con, $sql);
        if (!$ok) {
            error_log('DB schema update failed: ' . mysqli_error($con) . ' | SQL: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 300));
        }
        return $ok;
    }

    /**
     * SHOW COLUMNS as [lowercase name => row], or null if the table does not exist
     */
    private static function columnsOf($con, $table) {
        $res = mysqli_query($con, "SHOW COLUMNS FROM `{$table}`");
        if (!$res) {
            return null;
        }
        $cols = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cols[strtolower($row['Field'])] = $row;
        }
        return $cols;
    }

    /**
     * Append any missing values (case-insensitive) to an ENUM column, keeping existing values, default and nullability
     */
    private static function ensureEnumValues($con, $table, $column, array $required) {
        $cols = self::columnsOf($con, $table);
        if ($cols === null || !isset($cols[strtolower($column)])) {
            return;
        }
        $col = $cols[strtolower($column)];
        if (!preg_match("/^enum\((.*)\)$/i", (string)$col['Type'], $m)) {
            return; // not an enum (e.g. varchar) — nothing to do
        }
        preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $m[1], $vm);
        $existing = $vm[1];
        $existingLower = array_map('strtolower', $existing);
        $missing = [];
        foreach ($required as $val) {
            if (!in_array(strtolower($val), $existingLower, true)) {
                $missing[] = $val;
            }
        }
        if (empty($missing)) {
            return;
        }
        $all = array_merge($existing, $missing);
        $enumSql = implode(',', array_map(function ($v) use ($con) {
            return "'" . mysqli_real_escape_string($con, str_replace("''", "'", $v)) . "'";
        }, $all));
        $nullSql = ($col['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
        $defaultSql = '';
        if ($col['Default'] !== null) {
            $defaultSql = " DEFAULT '" . mysqli_real_escape_string($con, $col['Default']) . "'";
        } elseif ($col['Null'] === 'YES') {
            $defaultSql = ' DEFAULT NULL';
        }
        self::ddl($con, "ALTER TABLE `{$table}` MODIFY `{$column}` enum({$enumSql}) {$nullSql}{$defaultSql}");
    }

    /**
     * Fresh installs only: replace the publicly known seed password of `superadmin` / `admin`
     */
    private static function rotateSeededPasswords($con) {
        $knownSeedHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $res = mysqli_query($con, "SELECT id, username FROM `users` WHERE `password` = '" . mysqli_real_escape_string($con, $knownSeedHash) . "'");
        if (!$res || mysqli_num_rows($res) === 0) {
            return;
        }
        $lines = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $newPass = bin2hex(random_bytes(6)) . 'Aa1';
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($con, "UPDATE `users` SET `password` = ?, `must_change_password` = 1 WHERE `id` = ?");
            if ($stmt) {
                $id = (int)$row['id'];
                mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $lines[] = $row['username'] . ' : ' . $newPass;
            }
        }
        if (!empty($lines)) {
            // logs/ is blocked from the web by .htaccess
            @file_put_contents(
                __DIR__ . '/../logs/initial_admin_credentials.txt',
                "Initial passwords generated " . date('Y-m-d H:i:s') . " (change required at first login; delete this file afterwards)\n" . implode("\n", $lines) . "\n",
                LOCK_EX
            );
            error_log('Fresh install: seeded admin passwords rotated; see logs/initial_admin_credentials.txt');
        }
    }

    /**
     * Execute parameterized query securely
     */
    public static function query($sql, $params = [], $types = "") {
        $con = self::connect();

        // Schema changes made during this request invalidate the cached column lists
        if (preg_match('/^\s*(ALTER|CREATE|DROP|RENAME)\s/i', (string)$sql)) {
            self::$columnCache = [];
        }

        if (empty($params)) {
            $result = mysqli_query($con, $sql);
            if (!$result) {
                error_log("DB Query Error: " . mysqli_error($con) . " | SQL: " . $sql);
            }
            return $result;
        }

        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            error_log("DB Prepare Error: " . mysqli_error($con) . " | SQL: " . $sql);
            return false;
        }

        try {
            $executed = false;
            if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
                try {
                    $executed = @mysqli_stmt_execute($stmt, $params);
                } catch (Throwable $e) {
                    $executed = false;
                }
            }

            // Only fall back to typed binding when the statement never reached the server.
            // Re-running a statement the server rejected (e.g. after a deadlock rolled the
            // transaction back) would execute it a second time outside the transaction.
            if (!$executed && mysqli_stmt_errno($stmt) === 0) {
                if (empty($types)) {
                    $types = "";
                    foreach ($params as $p) {
                        if (is_int($p)) $types .= "i";
                        elseif (is_double($p) || is_float($p)) $types .= "d";
                        else $types .= "s";
                    }
                }
                @mysqli_stmt_bind_param($stmt, $types, ...$params);
                @mysqli_stmt_execute($stmt);
            }
        } catch (Throwable $e) {
            error_log("DB Stmt Execute Error: " . $e->getMessage() . " | SQL: " . $sql);
            @mysqli_stmt_close($stmt);
            return false;
        }

        if (mysqli_stmt_errno($stmt) !== 0) {
            self::$lastError = mysqli_stmt_error($stmt);
            error_log("DB Execute Error: " . self::$lastError . " | SQL: " . $sql);
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);

        if ($result === false && mysqli_stmt_errno($stmt) === 0) {
            // INSERT/UPDATE/DELETE query
            $affected = mysqli_stmt_affected_rows($stmt);
            $insert_id = mysqli_stmt_insert_id($stmt);
            mysqli_stmt_close($stmt);
            return ['affected' => $affected, 'insert_id' => $insert_id];
        }

        mysqli_stmt_close($stmt);
        return $result;
    }

    /**
     * Execute helper alias for query
     */
    public static function execute($sql, $params = []) {
        return self::query($sql, $params);
    }

    /**
     * Fetch all rows as associative array
     */
    public static function fetchAll($sql, $params = [], $types = "") {
        $res = self::query($sql, $params, $types);
        if (!$res || !is_object($res)) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch single row as associative array
     */
    public static function fetchOne($sql, $params = [], $types = "") {
        $res = self::query($sql, $params, $types);
        if (!$res || !is_object($res)) return null;
        $row = mysqli_fetch_assoc($res);
        return $row ?: null;
    }

    /**
     * Get single value from first column of first row
     */
    public static function fetchValue($sql, $params = [], $types = "") {
        $row = self::fetchOne($sql, $params, $types);
        if ($row && !empty($row)) {
            return reset($row);
        }
        return null;
    }

    /**
     * Start Database Transaction
     */
    public static function beginTransaction() {
        $con = self::connect();
        return mysqli_begin_transaction($con);
    }

    /**
     * Commit Database Transaction
     */
    public static function commit() {
        $con = self::connect();
        return mysqli_commit($con);
    }

    /**
     * Rollback Database Transaction
     */
    public static function rollback() {
        $con = self::connect();
        return mysqli_rollback($con);
    }

    /**
     * Get Last Inserted Auto-Increment ID
     */
    public static function lastInsertId() {
        $con = self::connect();
        return mysqli_insert_id($con);
    }

    /**
     * Insert row into table securely
     */
    public static function insert($table, $data) {
        $con = self::connect();

        // Normalize column aliases for tenants table
        if ($table === 'tenants') {
            if (isset($data['name']) && !isset($data['gym_name'])) {
                $data['gym_name'] = $data['name'];
            }
            if (isset($data['gym_name']) && !isset($data['name'])) {
                $data['name'] = $data['gym_name'];
            }
        } elseif ($table === 'invoices') {
            if (isset($data['total_amount']) && !isset($data['amount'])) {
                $data['amount'] = $data['total_amount'];
            }
            if (isset($data['amount']) && !isset($data['total_amount'])) {
                $data['total_amount'] = $data['amount'];
            }
            if (!isset($data['paid_amount'])) {
                $data['paid_amount'] = $data['amount'] ?? $data['total_amount'] ?? 0;
            }
            if (isset($data['status'])) {
                $data['status'] = strtolower($data['status']);
            }
        }

        // Auto-filter columns to match existing table schema
        $data = self::filterColumns($table, $data);
        if (empty($data)) {
            error_log("DB: insert into `{$table}` skipped, none of the supplied columns exist");
            return 0;
        }

        $cols = array_keys($data);
        $escaped_cols = array_map(function($c) { return "`$c`"; }, $cols);
        $placeholders = array_fill(0, count($cols), '?');

        $sql = "INSERT INTO `$table` (" . implode(', ', $escaped_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $params = array_values($data);

        $res = self::query($sql, $params);
        return is_array($res) ? $res['insert_id'] : 0;
    }

    /**
     * Update table row securely
     */
    public static function update($table, $data, $whereSql, $whereParams = []) {
        // Skip columns this database does not have (schemas differ between installs) instead of
        // letting one unknown column make the whole UPDATE fail silently
        $data = self::filterColumns($table, $data);
        if (empty($data)) {
            return 0;
        }

        $setClauses = [];
        $params = [];
        foreach ($data as $col => $val) {
            $setClauses[] = "`$col` = ?";
            $params[] = $val;
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses) . " WHERE " . $whereSql;
        $allParams = array_merge($params, $whereParams);

        $res = self::query($sql, $allParams);
        return is_array($res) ? $res['affected'] : 0;
    }

    /**
     * Delete from table securely
     */
    public static function delete($table, $whereSql, $whereParams = []) {
        $sql = "DELETE FROM `$table` WHERE " . $whereSql;
        $res = self::query($sql, $whereParams);
        return is_array($res) ? $res['affected'] : 0;
    }

    /**
     * Lower-cased column names of a table (cached per request). Returns null if the table can't be read.
     */
    public static function tableColumns($table) {
        $key = strtolower((string)$table);
        if (!array_key_exists($key, self::$columnCache)) {
            $cols = null;
            $colRes = @mysqli_query(self::connect(), "SHOW COLUMNS FROM `" . str_replace('`', '', (string)$table) . "`");
            if ($colRes) {
                $cols = [];
                while ($c = mysqli_fetch_assoc($colRes)) {
                    $cols[] = strtolower($c['Field']);
                }
            }
            self::$columnCache[$key] = $cols;
        }
        return self::$columnCache[$key];
    }

    /**
     * Drop keys that are not columns of $table (logged, so schema drift stays visible).
     * If the column list can't be read the data is returned unchanged.
     */
    private static function filterColumns($table, $data) {
        $validCols = self::tableColumns($table);
        if ($validCols === null) {
            return $data;
        }
        $filtered = [];
        $dropped = [];
        foreach ($data as $key => $val) {
            if (in_array(strtolower((string)$key), $validCols, true)) {
                $filtered[$key] = $val;
            } else {
                $dropped[] = $key;
            }
        }
        if (!empty($dropped)) {
            error_log("DB: skipped unknown column(s) for `{$table}`: " . implode(', ', $dropped));
        }
        return $filtered;
    }

    /**
     * Escape string helper for legacy compatibility
     */
    public static function escape($str) {
        $con = self::connect();
        return mysqli_real_escape_string($con, $str);
    }
}

// Instantiate connection variables for backwards compatibility
$con = DB::connect();
$conn = $con;
