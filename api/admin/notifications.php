<?php
/**
 * Gym Admin Notifications API
 * GET: Retrieves sent notification history and incoming SuperAdmin broadcasts
 * POST: Dispatches push notification to members
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/notifications.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$userId = (int)$auth['user_id'];

NotificationEngine::ensureSchema();

$tenant = Tenant::getById($tenantId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'send_notification';

    if ($action === 'quick_due_reminder') {
        $dueTitle = "Membership Fee Due Reminder - " . ($tenant['gym_name'] ?? 'Gym');
        $dueMsg = "Dear athlete, your membership fee is due. Kindly renew your plan at the front desk or via the member app to continue uninterrupted gym access.";

        $res = NotificationEngine::sendToMembers($tenantId, $dueTitle, $dueMsg, 'due_members', [], 'fee_reminder', [
            'sent_via' => 'admin_mobile_app_1click'
        ]);

        if ($res['success']) {
            Auth::auditLog('ADMIN_API_BULK_DUE_REMINDER', "Dispatched 1-click due reminder to {$res['delivered_count']} pending members");
            ApiResponse::success([
                'delivered_count' => $res['delivered_count'],
                'token_count' => $res['token_count'],
            ], "Instant Fee Reminder sent to {$res['delivered_count']} pending members ({$res['token_count']} devices received popup)!");
        } else {
            ApiResponse::error($res['error'] ?? 'Failed to send fee reminders.', 500);
        }
    }

    if ($action === 'mark_read') {
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId > 0) {
            DB::query("UPDATE notifications SET is_read = 1 WHERE id = ?", [$notificationId]);
        }
        ApiResponse::success(null, 'Marked as read.');
    }

    $title = trim((string)($input['title'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    $type = (string)($input['type'] ?? 'announcement');
    $target = (string)($input['target'] ?? 'all_members');
    $memberIds = !empty($input['member_id']) ? [(int)$input['member_id']] : (!empty($input['member_ids']) ? array_map('intval', (array)$input['member_ids']) : []);

    if (empty($title) || empty($message)) {
        ApiResponse::error('Title and message are required.', 422);
    }

    $res = NotificationEngine::sendToMembers($tenantId, $title, $message, $target, $memberIds, $type, [
        'sent_via' => 'admin_mobile_app'
    ]);

    if ($res['success']) {
        Auth::auditLog('ADMIN_API_PUSH_NOTIFICATION', "Dispatched push notification '$title' to {$res['delivered_count']} members");
        ApiResponse::success([
            'delivered_count' => $res['delivered_count'],
            'token_count' => $res['token_count'],
            'fcm_status' => $res['fcm_result']['status'] ?? 'sent'
        ], "Notification sent to {$res['delivered_count']} members ({$res['token_count']} push devices).");
    } else {
        ApiResponse::error($res['error'] ?? 'Failed to dispatch notification.', 500);
    }
}

// GET: Retrieve sent history, incoming SuperAdmin broadcasts & member list
$sentHistory = DB::fetchAll(
    "SELECT id, title, message, type, target_type, created_at 
     FROM notifications 
     WHERE tenant_id = ? AND sender_role IN ('gym_admin', 'staff')
     GROUP BY title, created_at 
     ORDER BY id DESC LIMIT 50",
    [$tenantId]
);

$incomingFromSuperAdmin = NotificationEngine::getGymOwnerNotifications($tenantId, $userId, 30);

// Fetch active members list for admin target dropdown
$membersList = DB::fetchAll(
    "SELECT m.user_id as id, m.fullname, m.member_id as member_code, m.status 
     FROM members m 
     WHERE m.tenant_id = ? AND m.status = 'active' 
     ORDER BY m.fullname ASC LIMIT 100",
    [$tenantId]
);

// Count pending fee members
$pendingDueCount = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'active' AND (expiry_date < CURDATE() OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY))",
    [$tenantId]
);

ApiResponse::success([
    'sent_history' => $sentHistory,
    'incoming_notices' => $incomingFromSuperAdmin,
    'members_list' => $membersList,
    'pending_due_count' => $pendingDueCount
], 'Admin notifications retrieved successfully.');
