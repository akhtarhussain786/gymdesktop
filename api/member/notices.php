<?php
/**
 * Member Notices & Announcements Endpoint
 * Strictly filters announcements by authenticated tenant.
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenantId = $auth['tenant_id'];

// Strictly this gym's announcements, newest first, bounded
$hasTitle = api_column_exists('announcements', 'title');
$notices = DB::fetchAll(
    "SELECT id, " . ($hasTitle ? "title, " : "") . "message, date FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC LIMIT 100",
    [(int)$tenantId]
);

$response = [
    'total' => count($notices),
    'notices' => array_map(function($n) {
        return [
            'id' => (int)$n['id'],
            'title' => !empty($n['title']) ? $n['title'] : 'Gym Announcement',
            'message' => $n['message'],
            'date' => $n['date'],
            'formatted_date' => strtotime((string)$n['date']) ? date('M d, Y', strtotime((string)$n['date'])) : '',
            'type' => 'general'
        ];
    }, $notices)
];

ApiResponse::success($response, 'Announcements retrieved.');
