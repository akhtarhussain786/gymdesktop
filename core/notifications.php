<?php
/**
 * 2-Tier Hierarchical Notification & Push System (Firebase FCM HTTP v1)
 * Tier 1: SuperAdmin -> Gym Owners / Admins / Members (Broadcast / Targeted)
 * Tier 2: Gym Owner / Admin -> Gym Members (All, Fee Due, or Specific)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class NotificationEngine {
    private static $schemaInitialized = false;

    /**
     * Auto-ensure database schema exists with all required columns & indexes
     */
    public static function ensureSchema() {
        if (self::$schemaInitialized) return;

        try {
            DB::query("CREATE TABLE IF NOT EXISTS `notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tenant_id` int(11) DEFAULT NULL,
              `sender_user_id` int(11) DEFAULT NULL,
              `sender_role` varchar(50) NOT NULL DEFAULT 'gym_admin',
              `target_type` varchar(50) NOT NULL DEFAULT 'all_members',
              `recipient_user_id` int(11) DEFAULT NULL,
              `recipient_member_id` int(11) DEFAULT NULL,
              `title` varchar(255) NOT NULL,
              `message` text NOT NULL,
              `type` varchar(50) NOT NULL DEFAULT 'announcement',
              `data_payload` text DEFAULT NULL,
              `is_read` tinyint(1) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_tenant_recipient` (`tenant_id`, `recipient_member_id`),
              KEY `idx_user_recipient` (`recipient_user_id`),
              KEY `idx_target_type` (`target_type`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            DB::query("CREATE TABLE IF NOT EXISTS `device_tokens` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `tenant_id` int(11) DEFAULT 0,
              `member_id` int(11) DEFAULT NULL,
              `user_id` int(11) DEFAULT NULL,
              `user_role` varchar(50) DEFAULT 'member',
              `device_token` text NOT NULL,
              `device_id` varchar(100) NOT NULL,
              `platform` enum('android','ios','web') NOT NULL DEFAULT 'android',
              `status` enum('active','inactive') NOT NULL DEFAULT 'active',
              `last_active_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_device_unique` (`device_id`),
              KEY `idx_tenant_member` (`tenant_id`, `member_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_user_role` (`user_role`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Safe column additions if migrating from older schema
            try {
                DB::query("ALTER TABLE `device_tokens` ADD COLUMN IF NOT EXISTS `user_role` varchar(50) DEFAULT 'member' AFTER `user_id`");
                DB::query("ALTER TABLE `device_tokens` ADD COLUMN IF NOT EXISTS `last_active_at` datetime DEFAULT CURRENT_TIMESTAMP AFTER `status`");
            } catch (Throwable $e) {}
        } catch (Throwable $e) {
            error_log("Notification schema auto-check error: " . $e->getMessage());
        }

        self::$schemaInitialized = true;
    }

    /**
     * TIER 1: SuperAdmin -> Broadcast to Gym Owners, Members, or Everyone
     * 
     * @param string $title
     * @param string $message
     * @param array $targetTenantIds Empty array means ALL gym tenants
     * @param string $type system_update|subscription_alert|offer|announcement
     * @param string $audience all_gym_owners|all_members|everyone|all|specific
     * @param array $data Additional JSON payload
     * @return array Result with exact accepted/rejected counts and status
     */
    public static function sendToGymOwners($title, $message, array $targetTenantIds = [], $type = 'system_update', $audience = 'all_gym_owners', array $data = []) {
        self::ensureSchema();

        $title = trim($title);
        $message = trim($message);
        if (empty($title) || empty($message)) {
            return ['success' => false, 'error' => 'Title and message are required.'];
        }

        $senderUserId = $_SESSION['user_id'] ?? null;
        $payloadJson = !empty($data) ? json_encode($data) : null;
        $targetType = !empty($targetTenantIds) ? 'specific_gyms' : $audience;

        // 1. Insert Global Broadcast Master Record into In-App Feed
        try {
            DB::insert('notifications', [
                'tenant_id' => !empty($targetTenantIds) && count($targetTenantIds) === 1 ? (int)$targetTenantIds[0] : null,
                'sender_user_id' => $senderUserId,
                'sender_role' => 'super_admin',
                'target_type' => $targetType,
                'recipient_user_id' => null,
                'recipient_member_id' => null,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data_payload' => $payloadJson,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log("Failed to insert broadcast notification record: " . $e->getMessage());
        }

        // 2. Determine target device tokens from database
        $tokens = [];
        if (!empty($targetTenantIds)) {
            $placeholders = implode(',', array_fill(0, count($targetTenantIds), '?'));
            $tokenRows = DB::fetchAll(
                "SELECT DISTINCT device_token FROM device_tokens WHERE (tenant_id IN ($placeholders) OR tenant_id = 0 OR tenant_id IS NULL) AND (status = 'active' OR status IS NULL OR status = '') AND device_token IS NOT NULL AND device_token != ''",
                array_map('intval', $targetTenantIds)
            );
            $tokens = array_column($tokenRows, 'device_token');
        } elseif ($audience === 'all_members') {
            $tokenRows = DB::fetchAll("SELECT DISTINCT device_token FROM device_tokens WHERE (status = 'active' OR status IS NULL OR status = '') AND (member_id > 0 OR user_role = 'member' OR (user_id IS NULL AND member_id IS NULL)) AND device_token IS NOT NULL AND device_token != ''");
            $tokens = array_column($tokenRows, 'device_token');
        } elseif ($audience === 'all_gym_owners') {
            $tokenRows = DB::fetchAll("SELECT DISTINCT device_token FROM device_tokens WHERE (status = 'active' OR status IS NULL OR status = '') AND (user_role IN ('gym_admin', 'staff', 'super_admin', 'admin', 'trainer') OR user_id IN (SELECT id FROM users WHERE role IN ('gym_admin', 'staff', 'super_admin', 'admin', 'trainer')) OR tenant_id > 0) AND device_token IS NOT NULL AND device_token != ''");
            $tokens = array_column($tokenRows, 'device_token');
        } else {
            // Everyone / All
            $tokenRows = DB::fetchAll("SELECT DISTINCT device_token FROM device_tokens WHERE (status = 'active' OR status IS NULL OR status = '') AND device_token IS NOT NULL AND device_token != ''");
            $tokens = array_column($tokenRows, 'device_token');
        }

        // FAILSAFE: If the filtered query returned 0 devices, fallback to all active registered devices
        if (empty($tokens)) {
            $fallbackRows = DB::fetchAll("SELECT DISTINCT device_token FROM device_tokens WHERE (status = 'active' OR status IS NULL OR status = '') AND device_token IS NOT NULL AND device_token != ''");
            $tokens = array_column($fallbackRows, 'device_token');
        }

        // Remove duplicates and empty values
        $tokens = array_values(array_filter(array_unique($tokens)));

        if (empty($tokens)) {
            return [
                'success' => true,
                'status' => 'no_devices_registered',
                'message' => 'Notification recorded in In-App feed, but 0 active mobile push devices were found for this audience.',
                'delivered_count' => 0,
                'token_count' => 0,
                'accepted_count' => 0,
                'rejected_count' => 0,
                'fcm_result' => [
                    'status' => 'no_devices',
                    'message' => 'No active device tokens found in database.'
                ]
            ];
        }

        // 3. Dispatch FCM Push Notification (High Priority Heads-up Popup)
        $fcmResult = self::dispatchFcm($tokens, $title, $message, array_merge($data, [
            'type' => $type,
            'sender' => 'SuperAdmin Headquarters',
            'action' => 'superadmin_broadcast',
            'audience' => $audience
        ]));

        $accepted = (int)($fcmResult['success_count'] ?? 0);
        $failed = (int)($fcmResult['fail_count'] ?? 0);
        $total = count($tokens);

        return [
            'success' => $accepted > 0 || ($total > 0 && $fcmResult['status'] === 'dispatched_v1'),
            'status' => $fcmResult['status'] ?? 'unknown',
            'delivered_count' => $accepted,
            'token_count' => $total,
            'accepted_count' => $accepted,
            'rejected_count' => $failed,
            'fcm_result' => $fcmResult
        ];
    }

    /**
     * TIER 2: Gym Owner / Admin -> Gym Members
     */
    public static function sendToMembers($tenantId, $title, $message, $target = 'all_members', array $memberIds = [], $type = 'announcement', array $data = []) {
        self::ensureSchema();

        $tenantId = (int)$tenantId;
        $title = trim($title);
        $message = trim($message);
        if ($tenantId <= 0 || empty($title) || empty($message)) {
            return ['success' => false, 'error' => 'Valid Gym Tenant, Title, and Message are required.'];
        }

        $senderUserId = $_SESSION['user_id'] ?? null;
        $payloadJson = !empty($data) ? json_encode($data) : null;

        // Fetch target members
        $targetMembers = [];
        if ($target === 'specific_member' && !empty($memberIds)) {
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $params = array_merge([$tenantId], array_map('intval', $memberIds));
            $targetMembers = DB::fetchAll("SELECT user_id, fullname, contact, email FROM members WHERE tenant_id = ? AND user_id IN ($placeholders)", $params);
        } elseif ($target === 'due_members') {
            $targetMembers = DB::fetchAll(
                "SELECT user_id, fullname, contact, email, amount, plan, paid_date,
                        DATEDIFF(DATE_ADD(COALESCE(paid_date, dor), INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH), CURDATE()) as days_left
                 FROM members 
                 WHERE tenant_id = ? 
                   AND (
                     (due_amount > 0)
                     OR (DATEDIFF(DATE_ADD(COALESCE(paid_date, dor), INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH), CURDATE()) <= 3)
                   )",
                [$tenantId]
            );
        } else {
            $targetMembers = DB::fetchAll("SELECT user_id, fullname, contact, email FROM members WHERE tenant_id = ? AND status = 'Active'", [$tenantId]);
        }

        // 1. Insert In-App Notification Feed records
        foreach ($targetMembers as $m) {
            try {
                DB::insert('notifications', [
                    'tenant_id' => $tenantId,
                    'sender_user_id' => $senderUserId,
                    'sender_role' => 'gym_admin',
                    'target_type' => $target,
                    'recipient_user_id' => null,
                    'recipient_member_id' => (int)$m['user_id'],
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'data_payload' => $payloadJson,
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {}
        }

        // 2. Fetch active device tokens strictly scoped to this tenant
        $tokens = [];
        if ($target === 'specific_member' && !empty($memberIds)) {
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $params = array_merge([$tenantId], array_map('intval', $memberIds));
            $tokenRows = DB::fetchAll(
                "SELECT DISTINCT device_token FROM device_tokens WHERE tenant_id = ? AND member_id IN ($placeholders) AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
                $params
            );
            $tokens = array_column($tokenRows, 'device_token');
        } elseif ($target === 'due_members' && !empty($targetMembers)) {
            $dueMemberIds = array_column($targetMembers, 'user_id');
            if (!empty($dueMemberIds)) {
                $placeholders = implode(',', array_fill(0, count($dueMemberIds), '?'));
                $params = array_merge([$tenantId], array_map('intval', $dueMemberIds));
                $tokenRows = DB::fetchAll(
                    "SELECT DISTINCT device_token FROM device_tokens WHERE tenant_id = ? AND member_id IN ($placeholders) AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
                    $params
                );
                $tokens = array_column($tokenRows, 'device_token');
            }
        } else {
            $tokenRows = DB::fetchAll(
                "SELECT DISTINCT device_token FROM device_tokens WHERE tenant_id = ? AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
                [$tenantId]
            );
            $tokens = array_column($tokenRows, 'device_token');
        }

        $tokens = array_values(array_filter(array_unique($tokens)));

        if (empty($tokens)) {
            return [
                'success' => true,
                'status' => 'no_devices_registered',
                'message' => 'In-app notification saved, but 0 active member push devices found.',
                'delivered_count' => count($targetMembers),
                'token_count' => 0,
                'accepted_count' => 0,
                'rejected_count' => 0
            ];
        }

        // 3. Dispatch FCM Push Notification
        $fcmResult = self::dispatchFcm($tokens, $title, $message, array_merge($data, [
            'type' => $type,
            'tenant_id' => (string)$tenantId,
            'sender' => 'Gym Management',
            'action' => 'member_broadcast'
        ]));

        $accepted = (int)($fcmResult['success_count'] ?? 0);
        $failed = (int)($fcmResult['fail_count'] ?? 0);

        return [
            'success' => $accepted > 0 || count($tokens) > 0,
            'status' => $fcmResult['status'] ?? 'unknown',
            'delivered_count' => count($targetMembers),
            'token_count' => count($tokens),
            'accepted_count' => $accepted,
            'rejected_count' => $failed,
            'fcm_result' => $fcmResult
        ];
    }

    /**
     * Get In-App Notifications for a Member
     */
    public static function getMemberNotifications($tenantId, $memberId, $limit = 30) {
        self::ensureSchema();
        $tenantId = (int)$tenantId;
        $memberId = (int)$memberId;
        $limit = max(1, min(100, (int)$limit));

        $sql = "SELECT id, title, message, type, data_payload, is_read, sender_role, created_at
                FROM notifications
                WHERE (
                    (tenant_id = ? AND (recipient_member_id = ? OR recipient_member_id IS NULL OR target_type IN ('all_members', 'all', 'everyone')))
                    OR (tenant_id IS NULL AND (recipient_member_id = ? OR recipient_member_id IS NULL OR target_type IN ('all_members', 'all', 'everyone', 'global')))
                )
                ORDER BY id DESC LIMIT ?";

        $rows = DB::fetchAll($sql, [$tenantId, $memberId, $memberId, $limit]);
        return array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'message' => $r['message'],
                'type' => $r['type'],
                'data' => !empty($r['data_payload']) ? json_decode($r['data_payload'], true) : null,
                'is_read' => (bool)$r['is_read'],
                'sender' => $r['sender_role'] === 'super_admin' ? 'SuperAdmin HQ' : 'Gym Management',
                'created_at' => $r['created_at'],
                'time_ago' => self::formatTimeAgo($r['created_at'])
            ];
        }, $rows);
    }

    /**
     * Get In-App Notifications for a Gym Owner / Staff
     */
    public static function getGymOwnerNotifications($tenantId, $userId, $limit = 30) {
        self::ensureSchema();
        $tenantId = (int)$tenantId;
        $userId = (int)$userId;
        $limit = max(1, min(100, (int)$limit));

        $sql = "SELECT id, title, message, type, data_payload, is_read, sender_role, created_at
                FROM notifications
                WHERE (
                    (tenant_id = ? AND (recipient_user_id = ? OR recipient_user_id IS NULL OR target_type IN ('all_gym_owners', 'broadcast', 'all', 'everyone')))
                    OR (tenant_id IS NULL AND (recipient_user_id = ? OR recipient_user_id IS NULL OR target_type IN ('all_gym_owners', 'broadcast', 'global', 'all', 'everyone')))
                )
                ORDER BY id DESC LIMIT ?";

        $rows = DB::fetchAll($sql, [$tenantId, $userId, $userId, $limit]);
        return array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'message' => $r['message'],
                'type' => $r['type'],
                'data' => !empty($r['data_payload']) ? json_decode($r['data_payload'], true) : null,
                'is_read' => (bool)$r['is_read'],
                'sender' => 'SuperAdmin SaaS',
                'created_at' => $r['created_at'],
                'time_ago' => self::formatTimeAgo($r['created_at'])
            ];
        }, $rows);
    }

    /**
     * Get Google OAuth2 Access Token using Firebase Service Account JSON (JWT RS256)
     */
    public static function getGoogleAccessToken() {
        static $cachedToken = null;
        static $tokenExpiry = 0;

        if ($cachedToken !== null && time() < ($tokenExpiry - 120)) {
            return $cachedToken;
        }

        $sa = self::getServiceAccountData();
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
            return null;
        }

        $now = time();
        $jwtHeader = self::base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]));

        $jwtClaims = self::base64UrlEncode(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]));

        $signatureInput = $jwtHeader . '.' . $jwtClaims;
        $signature = '';
        $privateKey = $sa['private_key'];

        $keyResource = openssl_pkey_get_private($privateKey);
        if (!$keyResource) {
            error_log("FCM v1: Invalid private key in firebase_service_account.json: " . openssl_error_string());
            return null;
        }

        if (!openssl_sign($signatureInput, $signature, $keyResource, OPENSSL_ALGO_SHA256)) {
            error_log("FCM v1: Failed to sign JWT with RSA key: " . openssl_error_string());
            return null;
        }

        $jwt = $signatureInput . '.' . self::base64UrlEncode($signature);

        // Exchange JWT for OAuth2 Access Token
        $ch = curl_init($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['access_token'])) {
                $cachedToken = $data['access_token'];
                $tokenExpiry = $now + (int)($data['expires_in'] ?? 3600);
                return $cachedToken;
            }
        }

        error_log("FCM v1: OAuth token exchange failed: HTTP $httpCode, Response: $response");
        return null;
    }

    /**
     * Retrieve and parse Firebase Service Account JSON credentials
     */
    public static function getServiceAccountData() {
        $sa = null;
        $serviceAccountPath = __DIR__ . '/firebase_service_account.json';
        if (file_exists($serviceAccountPath)) {
            $jsonContent = @file_get_contents($serviceAccountPath);
            $sa = @json_decode($jsonContent, true);
        }

        if (!$sa || empty($sa['private_key'])) {
            $envSa = getenv('FIREBASE_SERVICE_ACCOUNT_JSON');
            if ($envSa) {
                $sa = @json_decode($envSa, true);
            }
        }

        if (!$sa || empty($sa['private_key'])) {
            $envSaB64 = getenv('FIREBASE_SERVICE_ACCOUNT_BASE64');
            if ($envSaB64) {
                $sa = @json_decode(base64_decode($envSaB64), true);
            }
        }

        if (!$sa || empty($sa['private_key'])) {
            try {
                $dbSa = DB::fetchValue("SELECT setting_value FROM settings WHERE setting_key = 'firebase_service_account_json' LIMIT 1");
                if (!empty($dbSa)) {
                    $sa = @json_decode($dbSa, true);
                }
            } catch (Throwable $e) {}
        }

        return $sa;
    }

    /**
     * Save & Validate Firebase Service Account JSON credentials (File + DB fallback)
     */
    public static function saveServiceAccountData($jsonString) {
        $jsonString = trim((string)$jsonString);
        $sa = @json_decode($jsonString, true);
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
            return ['success' => false, 'error' => 'Invalid Firebase Service Account JSON. Ensure "client_email" and "private_key" are present.'];
        }

        // 1. Save to DB settings table (persists across Git deployments)
        try {
            DB::query("CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `setting_key` varchar(100) UNIQUE, `setting_value` longtext)");
            $exists = DB::fetchValue("SELECT COUNT(*) FROM settings WHERE setting_key = 'firebase_service_account_json'");
            if ($exists) {
                DB::update('settings', ['setting_value' => $jsonString], "setting_key = 'firebase_service_account_json'");
            } else {
                DB::insert('settings', ['setting_key' => 'firebase_service_account_json', 'setting_value' => $jsonString]);
            }
        } catch (Throwable $e) {
            error_log("Failed to save service account to DB: " . $e->getMessage());
        }

        // 2. Write to local file if writable
        try {
            $serviceAccountPath = __DIR__ . '/firebase_service_account.json';
            @file_put_contents($serviceAccountPath, $jsonString);
        } catch (Throwable $e) {}

        // 3. Test OAuth2 Token exchange immediately
        $token = self::getGoogleAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Saved, but Google rejected the RSA private key or OAuth2 exchange failed. Please verify credentials.'];
        }

        return [
            'success' => true,
            'project_id' => $sa['project_id'] ?? 'gymsaas-dc468',
            'client_email' => $sa['client_email']
        ];
    }

    /**
     * Health check for FCM HTTP v1 Configuration
     */
    public static function getFcmHealth() {
        $sa = self::getServiceAccountData();
        $hasKeyFile = !empty($sa['client_email']) && !empty($sa['private_key']);
        $projectId = $sa['project_id'] ?? 'gymsaas-dc468';
        $clientEmail = $sa['client_email'] ?? 'Not configured';

        $token = null;
        $authOk = false;
        if ($hasKeyFile) {
            $token = self::getGoogleAccessToken();
            $authOk = !empty($token);
        }

        $totalActiveTokens = (int)DB::fetchValue("SELECT COUNT(DISTINCT device_token) FROM device_tokens WHERE device_token IS NOT NULL AND device_token != ''");
        $adminTokens = (int)DB::fetchValue("SELECT COUNT(DISTINCT device_token) FROM device_tokens WHERE (user_role IN ('gym_admin', 'staff', 'super_admin', 'admin', 'trainer') OR user_id IN (SELECT id FROM users WHERE role IN ('gym_admin', 'staff', 'super_admin', 'admin', 'trainer')) OR tenant_id > 0) AND device_token IS NOT NULL AND device_token != ''");
        $memberTokens = (int)DB::fetchValue("SELECT COUNT(DISTINCT device_token) FROM device_tokens WHERE (member_id IS NOT NULL OR user_role = 'member' OR (user_id IS NULL AND member_id IS NULL)) AND device_token IS NOT NULL AND device_token != ''");

        return [
            'fcm_v1_configured' => $hasKeyFile,
            'oauth_authenticated' => $authOk,
            'project_id' => $projectId,
            'client_email' => $clientEmail,
            'active_devices_total' => $totalActiveTokens,
            'admin_devices' => $adminTokens,
            'member_devices' => $memberTokens
        ];
    }

    /**
     * Dispatch Google Firebase Cloud Messaging (FCM) Push (FCM HTTP v1 Standard)
     * 
     * Handles:
     * - OAuth2 Bearer Authentication
     * - Android 13+ High Importance Heads-up Channels
     * - Auto-pruning invalid / unregistered tokens (UNREGISTERED, NOT_FOUND)
     * - Per-device detailed reporting
     */
    public static function dispatchFcm(array $tokens, $title, $body, array $payload = []) {
        if (empty($tokens)) {
            return [
                'status' => 'skipped',
                'message' => 'No active device tokens found.',
                'success_count' => 0,
                'fail_count' => 0,
                'total_devices' => 0,
                'details' => []
            ];
        }

        $sa = self::getServiceAccountData();
        $projectId = $sa['project_id'] ?? 'gymsaas-dc468';
        $accessToken = self::getGoogleAccessToken();

        // 1. If Google FCM v1 Access Token is available (Required Modern Standard)
        if ($accessToken) {
            $totalSuccess = 0;
            $totalFail = 0;
            $prunedCount = 0;
            $deviceResults = [];

            // Ensure all data payload fields are strictly string key-value pairs
            $stringPayload = [];
            foreach ($payload as $k => $v) {
                $stringPayload[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
            }
            $stringPayload['title'] = (string)$title;
            $stringPayload['body'] = (string)$body;
            $stringPayload['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';

            foreach ($tokens as $token) {
                $token = trim((string)$token);
                if (empty($token) || strlen($token) < 20) {
                    continue;
                }

                $message = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => (string)$title,
                            'body' => (string)$body,
                        ],
                        'data' => $stringPayload,
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                                'channel_id' => 'gym_high_importance_channel',
                                'icon' => 'ic_launcher',
                                'color' => '#10B981',
                                'notification_priority' => 'PRIORITY_MAX',
                                'visibility' => 'PUBLIC',
                                'default_sound' => true,
                                'default_vibrate_timings' => true,
                                'default_light_settings' => true,
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ]
                        ],
                        'apns' => [
                            'headers' => [
                                'apns-priority' => '10'
                            ],
                            'payload' => [
                                'aps' => [
                                    'alert' => [
                                        'title' => (string)$title,
                                        'body' => (string)$body
                                    ],
                                    'sound' => 'default',
                                    'badge' => 1,
                                    'content-available' => 1
                                ]
                            ]
                        ]
                    ]
                ];

                $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $maskedToken = substr($token, 0, 8) . '...' . substr($token, -6);

                if ($httpCode === 200) {
                    $totalSuccess++;
                    $resJson = json_decode($response, true);
                    $messageName = $resJson['name'] ?? 'projects/' . $projectId . '/messages/accepted';
                    $deviceResults[] = [
                        'token' => $maskedToken,
                        'status' => 'delivered',
                        'http_code' => 200,
                        'message_id' => $messageName
                    ];
                } else {
                    $totalFail++;
                    $errData = json_decode($response, true);
                    $errStatus = $errData['error']['status'] ?? 'ERROR';
                    $errMsg = $errData['error']['message'] ?? 'FCM dispatch failed';

                    $deviceResults[] = [
                        'token' => $maskedToken,
                        'status' => 'failed',
                        'http_code' => $httpCode,
                        'error_status' => $errStatus,
                        'error_message' => $errMsg
                    ];

                    error_log("FCM v1 error for device {$maskedToken}: HTTP {$httpCode} [{$errStatus}] {$errMsg}");

                    // Auto-prune uninstalled or expired tokens
                    if (in_array($errStatus, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)
                        || strpos($errMsg, 'not a valid FCM registration token') !== false
                        || strpos($errMsg, 'Requested entity was not found') !== false) {
                        try {
                            DB::query("DELETE FROM device_tokens WHERE device_token = ?", [$token]);
                            $prunedCount++;
                            error_log("FCM v1: Auto-pruned invalid token from database: {$maskedToken}");
                        } catch (Throwable $e) {}
                    }
                }
            }

            return [
                'status' => 'dispatched_v1',
                'api' => 'fcm_v1',
                'project_id' => $projectId,
                'success_count' => $totalSuccess,
                'fail_count' => $totalFail,
                'pruned_count' => $prunedCount,
                'total_devices' => count($tokens),
                'details' => $deviceResults
            ];
        }

        // 2. Fallback if Service Account is missing or invalid
        error_log("FCM v1 Error: Service account private key is not configured or OAuth2 authentication failed.");
        return [
            'status' => 'auth_error',
            'api' => 'fcm_v1',
            'message' => 'Firebase Service Account JSON credentials not configured or failed to generate OAuth2 token.',
            'success_count' => 0,
            'fail_count' => count($tokens),
            'total_devices' => count($tokens),
            'details' => []
        ];
    }

    /**
     * Send Instant Test Push Notification to a Specific FCM Device Token
     * 
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @return array Diagnostic result with timing, HTTP status, and raw Firebase output
     */
    public static function sendTestPush($deviceToken, $title = '🔔 SuperAdmin Test Push Notification', $body = 'Test push received successfully with high-importance sound and banner alert.') {
        $deviceToken = trim((string)$deviceToken);
        if (empty($deviceToken)) {
            return ['success' => false, 'error' => 'Device FCM token is required.'];
        }

        $startTime = microtime(true);
        $res = self::dispatchFcm([$deviceToken], $title, $body, [
            'type' => 'test_alert',
            'source' => 'superadmin_live_tester',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $isSuccess = ($res['success_count'] ?? 0) > 0;
        return [
            'success' => $isSuccess,
            'duration_ms' => $durationMs,
            'fcm_response' => $res,
            'message' => $isSuccess
                ? 'Firebase accepted the push notification in ' . $durationMs . 'ms!'
                : 'Firebase rejected the push request. Check details below.'
        ];
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function formatTimeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return date('M d, Y', $time);
    }
}
