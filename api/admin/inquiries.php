<?php
/**
 * Gym Admin Member Support Inquiries & Requests API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

// Handle POST actions
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'reply';

    if ($action === 'reply' || $action === 'update_status') {
        $inquiryId = (int)($input['inquiry_id'] ?? $input['id'] ?? 0);
        $reply = trim($input['reply'] ?? '');
        $status = in_array($input['status'] ?? '', $allowedStatuses, true) ? $input['status'] : 'resolved';

        $inquiry = DB::fetchOne("SELECT id FROM member_inquiries WHERE id = ? AND tenant_id = ?", [$inquiryId, $tenantId]);
        if (!$inquiry) {
            ApiResponse::error('Request not found', 404);
        }

        $data = ['status' => $status];
        if ($reply !== '') {
            $data['reply'] = mb_substr($reply, 0, 5000);
        }
        DB::update('member_inquiries', $data, 'id = ? AND tenant_id = ?', [$inquiryId, $tenantId]);
        Auth::auditLog('REPLY_INQUIRY', "Updated member request #{$inquiryId} to {$status}");
        ApiResponse::success([], 'Inquiry updated successfully');
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        DB::delete('member_inquiries', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
        ApiResponse::success([], 'Inquiry deleted successfully');
    }
}

// GET: Retrieve inquiries
$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT i.*, m.fullname, m.username, m.contact
        FROM member_inquiries i
        LEFT JOIN members m ON m.user_id = i.member_id AND m.tenant_id = i.tenant_id
        WHERE i.tenant_id = ?";
$params = [$tenantId];

if (!empty($statusFilter) && in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND i.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY FIELD(i.status, 'open', 'pending', 'in_progress', 'resolved', 'closed'), i.created_at DESC, i.id DESC LIMIT 150";
$inquiries = DB::fetchAll($sql, $params);
$openCount = (int)DB::fetchValue("SELECT COUNT(*) FROM member_inquiries WHERE tenant_id = ? AND status IN ('open','pending','in_progress')", [$tenantId]);

ApiResponse::success([
    'inquiries' => $inquiries,
    'open_count' => $openCount
], 'Inquiries retrieved successfully');
