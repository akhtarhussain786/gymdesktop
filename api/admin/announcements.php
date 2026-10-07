<?php
/**
 * Gym Admin Announcements & Broadcasts API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST actions
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ApiResponse::error('Invalid announcement ID', 400);
        }
        $deleted = DB::delete('announcements', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($deleted) {
            ApiResponse::success([], 'Announcement deleted successfully');
        } else {
            ApiResponse::error('Announcement not found or deletion failed', 404);
        }
    }

    // Default: Add Announcement
    $message = trim($input['message'] ?? '');
    $title = trim($input['title'] ?? '');
    $date = $input['date'] ?? date('Y-m-d');

    if (empty($message)) {
        ApiResponse::error('Announcement message is required', 400);
    }

    if (empty($title)) {
        $title = mb_substr($message, 0, 60);
    }

    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');

    $newId = DB::insert('announcements', [
        'tenant_id' => $tenantId,
        'title' => $title,
        'message' => $message,
        'date' => $date
    ]);

    Auth::auditLog('ADD_ANNOUNCEMENT', "Posted announcement: $title");
    ApiResponse::success(['id' => $newId], 'Announcement published successfully');
}

// GET: Retrieve all announcements
$announcements = DB::fetchAll("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC LIMIT 100", [$tenantId]);

ApiResponse::success(['announcements' => $announcements], 'Announcements retrieved successfully');
