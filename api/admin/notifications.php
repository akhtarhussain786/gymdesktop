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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
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

// GET: Retrieve sent history & incoming SuperAdmin broadcasts
$sentHistory = DB::fetchAll(
    "SELECT id, title, message, type, target_type, created_at 
     FROM notifications 
     WHERE tenant_id = ? AND sender_role IN ('gym_admin', 'staff')
     GROUP BY title, created_at 
     ORDER BY id DESC LIMIT 30",
    [$tenantId]
);

$incomingFromSuperAdmin = NotificationEngine::getGymOwnerNotifications($tenantId, $userId, 20);

ApiResponse::success([
    'sent_history' => $sentHistory,
    'incoming_notices' => $incomingFromSuperAdmin
], 'Admin notifications retrieved successfully.');
