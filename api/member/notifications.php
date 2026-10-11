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

    // Only this member's own rows: broadcast rows (no recipient) carry a single is_read flag shared by
    // every member who can see them, so one member must not flip it for the whole gym / platform.
    if ($action === 'mark_all_read') {
        DB::query(
            "UPDATE notifications SET is_read = 1 WHERE tenant_id = ? AND recipient_member_id = ? AND is_read = 0",
            [$tenantId, $memberId]
        );
        ApiResponse::success(null, 'All notifications marked as read.');
    } else {
        if ($notificationId > 0) {
            DB::query(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND tenant_id = ? AND recipient_member_id = ?",
                [$notificationId, $tenantId, $memberId]
            );
        }
        ApiResponse::success(null, 'Notification marked as read.');
    }
}

// GET: Retrieve member notifications list
$limit = (int)($_GET['limit'] ?? 30);
$notifications = NotificationEngine::getMemberNotifications($tenantId, $memberId, $limit);

// Rows "mark all as read" can clear (see above), so the badge always resets
$unreadCount = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM notifications WHERE tenant_id = ? AND recipient_member_id = ? AND is_read = 0",
    [$tenantId, $memberId]
);

ApiResponse::success([
    'notifications' => $notifications,
    'unread_count' => $unreadCount
], 'Notifications retrieved successfully.');
