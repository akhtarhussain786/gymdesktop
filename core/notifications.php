<?php
/**
 * 2-Tier Hierarchical Notification & Push System
 * Tier 1: SuperAdmin -> Gym Owners / Admins (Broadcast / Targeted)
 * Tier 2: Gym Owner / Admin -> Gym Members (All, Fee Due, or Specific)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class NotificationEngine {
    private static $schemaInitialized = false;

    /**
     * Auto-ensure database schema exists
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
              `tenant_id` int(11) NOT NULL,
              `member_id` int(11) DEFAULT NULL,
              `user_id` int(11) DEFAULT NULL,
              `device_token` text NOT NULL,
              `device_id` varchar(100) NOT NULL,
              `platform` enum('android','ios','web') NOT NULL DEFAULT 'android',
              `status` enum('active','inactive') NOT NULL DEFAULT 'active',
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `idx_tenant_device` (`tenant_id`, `device_id`),
              KEY `idx_tenant_member` (`tenant_id`, `member_id`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log("Notification schema auto-check error: " . $e->getMessage());
        }

        self::$schemaInitialized = true;
    }

    /**
     * TIER 1: SuperAdmin -> Gym Owners / Admins
     * 
     * @param string $title
     * @param string $message
     * @param array $targetTenantIds Empty array means ALL gym owners
     * @param string $type system_update|subscription_alert|offer|announcement
     * @param array $data Additional JSON payload
     * @return array ['success' => bool, 'delivered_count' => int, 'token_count' => int]
     */
    public static function sendToGymOwners($title, $message, array $targetTenantIds = [], $type = 'system_update', array $data = []) {
        self::ensureSchema();

        $title = trim($title);
        $message = trim($message);
        if (empty($title) || empty($message)) {
            return ['success' => false, 'error' => 'Title and message are required.'];
        }

        $senderUserId = $_SESSION['user_id'] ?? null;
        $targetType = empty($targetTenantIds) ? 'all_gym_owners' : 'specific_gym_owners';
        $payloadJson = !empty($data) ? json_encode($data) : null;

        // Determine matching gym owners
        $whereSql = "role IN ('gym_admin', 'staff') AND status = 'active'";
        $params = [];
        if (!empty($targetTenantIds)) {
            $placeholders = implode(',', array_fill(0, count($targetTenantIds), '?'));
            $whereSql .= " AND tenant_id IN ($placeholders)";
            $params = array_map('intval', $targetTenantIds);
        }

        $gymOwners = DB::fetchAll("SELECT id, tenant_id, fullname, email, phone FROM users WHERE $whereSql", $params);
        if (empty($gymOwners)) {
            // Even if no specific admin found in users table, insert broadcast notification
            $insertedId = DB::insert('notifications', [
                'tenant_id' => null,
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
            return ['success' => true, 'delivered_count' => 0, 'token_count' => 0, 'notification_id' => $insertedId];
        }

        // Insert notification record for each owner for personalized in-app tracking
        $deliveredCount = 0;
        foreach ($gymOwners as $owner) {
            DB::insert('notifications', [
                'tenant_id' => (int)$owner['tenant_id'],
                'sender_user_id' => $senderUserId,
                'sender_role' => 'super_admin',
                'target_type' => $targetType,
                'recipient_user_id' => (int)$owner['id'],
                'recipient_member_id' => null,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data_payload' => $payloadJson,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $deliveredCount++;
        }

        // Fetch active FCM device tokens for these gym owners
        $userIds = array_map(fn($o) => (int)$o['id'], $gymOwners);
        $userPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
        $tokenRows = DB::fetchAll(
            "SELECT DISTINCT device_token FROM device_tokens WHERE user_id IN ($userPlaceholders) AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
            $userIds
        );

        $tokens = array_column($tokenRows, 'device_token');
        $fcmResult = self::dispatchFcm($tokens, $title, $message, array_merge($data, [
            'type' => $type,
            'sender' => 'SuperAdmin',
            'action' => 'superadmin_notice'
        ]));

        return [
            'success' => true,
            'delivered_count' => $deliveredCount,
            'token_count' => count($tokens),
            'fcm_result' => $fcmResult
        ];
    }

    /**
     * TIER 2: Gym Owner / Admin -> Gym Members
     * 
     * @param int $tenantId
     * @param string $title
     * @param string $message
     * @param string $target 'all_members' | 'due_members' | 'specific_member'
     * @param array $memberIds Specific member user_ids (if target is specific_member)
     * @param string $type announcement|fee_reminder|timing_change|offer|general
     * @param array $data Additional JSON payload
     * @return array ['success' => bool, 'delivered_count' => int, 'token_count' => int]
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
            // Members with due_amount > 0 OR expiring within 3 days or already expired
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
            // All active members
            $targetMembers = DB::fetchAll("SELECT user_id, fullname, contact, email FROM members WHERE tenant_id = ? AND status = 'Active'", [$tenantId]);
        }

        // Broadcast row in notifications for quick dashboard/list lookup
        DB::insert('notifications', [
            'tenant_id' => $tenantId,
            'sender_user_id' => $senderUserId,
            'sender_role' => 'gym_admin',
            'target_type' => $target,
            'recipient_user_id' => null,
            'recipient_member_id' => ($target === 'specific_member' && count($memberIds) === 1) ? (int)$memberIds[0] : null,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data_payload' => $payloadJson,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Insert individually for specific member targeting if few recipients
        if (!empty($targetMembers) && count($targetMembers) <= 100) {
            foreach ($targetMembers as $m) {
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
            }
        }

        // Also add to legacy announcements table if exists so older screens display it seamlessly
        try {
            DB::insert('announcements', [
                'tenant_id' => $tenantId,
                'branch_id' => 1,
                'message' => $title . ': ' . $message,
                'date' => date('Y-m-d')
            ]);
        } catch (Throwable $e) {}

        // Fetch active FCM device tokens for target members
        $tokens = [];
        if ($target === 'all_members') {
            $tokenRows = DB::fetchAll(
                "SELECT DISTINCT device_token FROM device_tokens WHERE tenant_id = ? AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
                [$tenantId]
            );
            $tokens = array_column($tokenRows, 'device_token');
        } elseif (!empty($targetMembers)) {
            $mIds = array_map(fn($m) => (int)$m['user_id'], $targetMembers);
            $mPlaceholders = implode(',', array_fill(0, count($mIds), '?'));
            $tokenRows = DB::fetchAll(
                "SELECT DISTINCT device_token FROM device_tokens WHERE tenant_id = ? AND member_id IN ($mPlaceholders) AND status = 'active' AND device_token IS NOT NULL AND device_token != ''",
                array_merge([$tenantId], $mIds)
            );
            $tokens = array_column($tokenRows, 'device_token');
        }

        $fcmResult = self::dispatchFcm($tokens, $title, $message, array_merge($data, [
            'type' => $type,
            'tenant_id' => $tenantId,
            'action' => 'member_notice'
        ]));

        return [
            'success' => true,
            'delivered_count' => count($targetMembers),
            'token_count' => count($tokens),
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
                WHERE (tenant_id = ? OR tenant_id IS NULL)
                  AND (
                    recipient_member_id = ? 
                    OR (target_type IN ('all_members', 'broadcast') AND recipient_member_id IS NULL)
                  )
                ORDER BY id DESC LIMIT ?";

        $rows = DB::fetchAll($sql, [$tenantId, $memberId, $limit]);
        return array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'message' => $r['message'],
                'type' => $r['type'],
                'data' => !empty($r['data_payload']) ? json_decode($r['data_payload'], true) : null,
                'is_read' => (bool)$r['is_read'],
                'sender' => $r['sender_role'] === 'super_admin' ? 'Headquarters' : 'Gym Management',
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
                WHERE (tenant_id = ? OR tenant_id IS NULL)
                  AND (
                    recipient_user_id = ? 
                    OR (target_type IN ('all_gym_owners', 'broadcast') AND recipient_user_id IS NULL)
                  )
                ORDER BY id DESC LIMIT ?";

        $rows = DB::fetchAll($sql, [$tenantId, $userId, $limit]);
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
     * Dispatch Google Firebase Cloud Messaging (FCM) Push
     */
    public static function dispatchFcm(array $tokens, $title, $body, array $payload = []) {
        if (empty($tokens)) {
            return ['status' => 'skipped', 'message' => 'No active device tokens found.'];
        }

        $fcmServerKey = getenv('FCM_SERVER_KEY') ?: '';
        if (empty($fcmServerKey)) {
            // Check global settings table
            try {
                $fcmServerKey = DB::fetchValue("SELECT setting_value FROM settings WHERE setting_key = 'fcm_server_key' LIMIT 1") ?: '';
            } catch (Throwable $e) {}
        }

        // If no server key configured yet, log in database and return ready status
        if (empty($fcmServerKey)) {
            error_log("FCM Notice: FCM_SERVER_KEY is not configured yet in .env or settings. Push queued for " . count($tokens) . " devices.");
            return [
                'status' => 'queued',
                'message' => 'FCM Server Key not configured. Push recorded in In-App notification feed.',
                'device_count' => count($tokens)
            ];
        }

        // Batch tokens in chunks of 500 (FCM limit)
        $chunks = array_chunk($tokens, 500);
        $totalSuccess = 0;
        $totalFail = 0;

        foreach ($chunks as $chunk) {
            $fcmData = [
                'registration_ids' => array_values($chunk),
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => '1',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ],
                'data' => array_merge($payload, [
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ]),
                'priority' => 'high'
            ];

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: key=' . $fcmServerKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmData));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $res = json_decode($response, true);
                $totalSuccess += (int)($res['success'] ?? 0);
                $totalFail += (int)($res['failure'] ?? 0);
            } else {
                $totalFail += count($chunk);
            }
        }

        return [
            'status' => 'dispatched',
            'success_count' => $totalSuccess,
            'fail_count' => $totalFail,
            'total_devices' => count($tokens)
        ];
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
