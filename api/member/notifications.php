<?php
/**
 * Member In-App & Push Notifications Endpoint
 * GET: Retrieves notifications for the authenticated member + unread count
 * POST: Mark single or all notifications as read
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/notifications.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$memberId = (int)$auth['member_id'];
$userId = (int)$auth['user_id'];

NotificationEngine::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'mark_read';
    $notificationId = (int)($input['notification_id'] ?? 0);

    if ($action === 'mark_all_read') {
        DB::query(
            "UPDATE notifications SET is_read = 1 WHERE (tenant_id = ? OR tenant_id IS NULL) AND (recipient_member_id = ? OR target_type IN ('all_members', 'broadcast'))",
            [$tenantId, $memberId]
        );
        ApiResponse::success(null, 'All notifications marked as read.');
    } else {
        if ($notificationId > 0) {
            DB::query("UPDATE notifications SET is_read = 1 WHERE id = ?", [$notificationId]);
        }
        ApiResponse::success(null, 'Notification marked as read.');
    }
}

// GET: Retrieve member notifications list
$limit = (int)($_GET['limit'] ?? 30);
$notifications = NotificationEngine::getMemberNotifications($tenantId, $memberId, $limit);

$unreadCount = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM notifications 
     WHERE (tenant_id = ? OR tenant_id IS NULL) 
       AND (recipient_member_id = ? OR (target_type IN ('all_members', 'broadcast') AND recipient_member_id IS NULL))
       AND is_read = 0",
    [$tenantId, $memberId]
);

ApiResponse::success([
    'notifications' => $notifications,
    'unread_count' => $unreadCount
], 'Notifications retrieved successfully.');
