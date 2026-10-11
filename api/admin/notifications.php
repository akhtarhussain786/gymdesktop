<?php
/**
 * Gym Admin Notifications API
 * GET: Retrieves sent notification history and incoming SuperAdmin broadcasts
 * POST: Dispatches push notification to members
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/notifications.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$userId = (int)$auth['user_id'];

NotificationEngine::ensureSchema();

$tenant = $auth['tenant'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'send_notification';

    // Marking a notice read is allowed to every gym role, also while the gym is read-only
    if ($action === 'mark_read') {
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId > 0) {
            // Only this gym's notices addressed to this user (or to all staff); never member or platform-wide rows
            DB::query(
                "UPDATE notifications SET is_read = 1
                 WHERE id = ? AND tenant_id = ? AND recipient_member_id IS NULL AND (recipient_user_id = ? OR recipient_user_id IS NULL)",
                [$notificationId, $tenantId, $userId]
            );
        }
        ApiResponse::success(null, 'Marked as read.');
    }

    // Sending pushes to members is a manager action and a write
    AdminAuthMiddleware::requireRole(AdminAuthMiddleware::ROLES_MANAGERS);
    AdminAuthMiddleware::requireWritable();

    if ($action === 'quick_due_reminder') {
        $dueTitle = "Membership Fee Due Reminder - " . ($tenant['gym_name'] ?? 'Gym');
        $dueMsg = "Dear athlete, your membership fee is due. Kindly renew your plan at the front desk or via the member app to continue uninterrupted gym access.";

        $res = NotificationEngine::sendToMembers($tenantId, $dueTitle, $dueMsg, 'due_members', [], 'fee_reminder', [
            'sent_via' => 'admin_mobile_app_1click'
        ]);

        if ($res['success']) {
            Auth::auditLog('ADMIN_API_BULK_DUE_REMINDER', "Dispatched 1-click due reminder to {$res['delivered_count']} pending members", $tenantId, $userId);
            ApiResponse::success([
                'delivered_count' => $res['delivered_count'],
                'token_count' => $res['token_count'],
            ], "Instant Fee Reminder sent to {$res['delivered_count']} pending members ({$res['token_count']} devices received popup)!");
        } else {
            ApiResponse::error($res['error'] ?? 'Failed to send fee reminders.', 500);
        }
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
        Auth::auditLog('ADMIN_API_PUSH_NOTIFICATION', "Dispatched push notification '$title' to {$res['delivered_count']} members", $tenantId, $userId);
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
// One row per broadcast (a send stores one row per recipient). Grouping inside a derived table keeps
// the query valid under ONLY_FULL_GROUP_BY.
$sentHistory = DB::fetchAll(
    "SELECT n.id, n.title, n.message, n.type, n.target_type, n.created_at
     FROM notifications n
     JOIN (
         SELECT MAX(id) AS id
         FROM notifications
         WHERE tenant_id = ? AND sender_role IN ('gym_admin', 'staff')
         GROUP BY title, created_at
     ) latest ON latest.id = n.id
     ORDER BY n.id DESC LIMIT 50",
    [$tenantId]
);

$incomingFromSuperAdmin = NotificationEngine::getGymOwnerNotifications($tenantId, $userId, 30);

// Fetch active members list for admin target dropdown
$membersList = DB::fetchAll(
    "SELECT m.user_id as id, m.fullname, m.username as member_code, m.status 
     FROM members m 
     WHERE m.tenant_id = ? AND m.status = 'active' 
     ORDER BY m.fullname ASC LIMIT 100",
    [$tenantId]
);

// Count members with fees due: an outstanding balance, or a membership (paid_date/dor + plan months)
// that has expired or ends within 5 days
$pendingDueCount = 0;
$soon = date('Y-m-d', strtotime('+5 days'));
$hasDueAmount = api_column_exists('members', 'due_amount');
$dueRows = DB::fetchAll(
    "SELECT paid_date, dor, plan" . ($hasDueAmount ? ", due_amount" : "") . " FROM members WHERE tenant_id = ? AND status = 'active'",
    [$tenantId]
);
foreach ($dueRows as $r) {
    if ($hasDueAmount && (float)($r['due_amount'] ?? 0) > 0) {
        $pendingDueCount++;
        continue;
    }
    $start = $r['paid_date'] ?? null;
    if (empty($start) || strpos((string)$start, '0000-00-00') === 0) {
        $start = (!empty($r['dor']) && strpos((string)$r['dor'], '0000-00-00') !== 0) ? $r['dor'] : null;
    }
    if ($start === null) {
        continue;
    }
    $expiry = SubscriptionEngine::addMonths($start, max(1, (int)($r['plan'] ?? 1)));
    if ($expiry <= $soon) {
        $pendingDueCount++;
    }
}

ApiResponse::success([
    'sent_history' => $sentHistory,
    'incoming_notices' => $incomingFromSuperAdmin,
    'members_list' => $membersList,
    'pending_due_count' => $pendingDueCount
], 'Admin notifications retrieved successfully.');
