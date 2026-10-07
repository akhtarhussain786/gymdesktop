<?php
/**
 * Member Support, Gym Info & Help Tickets Endpoint
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

// Handle new help request ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');
    $category = trim($input['category'] ?? 'General Inquiry');

    if (empty($subject) || empty($message)) {
        ApiResponse::error('Subject and message are required.', 422);
    }

    // Bound field sizes (subject column is varchar(150), category varchar(50))
    $subject = mb_substr($subject, 0, 150);
    $message = mb_substr($message, 0, 5000);
    $category = mb_substr($category !== '' ? $category : 'General Inquiry', 0, 50);

    // Anti-spam: max 5 tickets per member per hour, and max 20 still-open tickets
    $recentCount = (int)DB::fetchValue(
        "SELECT COUNT(*) FROM member_inquiries WHERE tenant_id = ? AND member_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)",
        [$tenantId, $memberId]
    );
    $openCount = (int)DB::fetchValue(
        "SELECT COUNT(*) FROM member_inquiries WHERE tenant_id = ? AND member_id = ? AND status IN ('open','pending','in_progress')",
        [$tenantId, $memberId]
    );
    if ($recentCount >= 5 || $openCount >= 20) {
        ApiResponse::error('You have submitted too many requests recently. Please wait for the gym to respond.', 429);
    }

    $ticketId = DB::insert('member_inquiries', [
        'tenant_id' => $tenantId,
        'branch_id' => $member['branch_id'] ?? 1,
        'member_id' => $memberId,
        'category' => $category,
        'subject' => $subject,
        'message' => $message,
        'status' => 'open'
    ]);

    if (!$ticketId) {
        ApiResponse::error('Unable to submit your inquiry right now. Please try again.', 500);
    }

    ApiResponse::success(['ticket_id' => (int)$ticketId], 'Your inquiry has been submitted to gym management.');
}

// Fetch member's past inquiries
$inquiries = DB::fetchAll(
    "SELECT id, category, subject, message, reply, status, created_at, updated_at 
     FROM member_inquiries 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY id DESC LIMIT 100",
    [$tenantId, $memberId]
);

$branch = DB::fetchOne("SELECT branch_name, address, phone, email FROM branches WHERE id = ? AND tenant_id = ?", [$member['branch_id'] ?? 1, $tenantId]);

$response = [
    'gym_contact' => [
        'gym_name' => $tenant['gym_name'],
        'branch_name' => $branch['branch_name'] ?? 'Main Facility',
        'address' => $branch['address'] ?? $tenant['address'],
        'phone' => $branch['phone'] ?? $tenant['phone'],
        'email' => $branch['email'] ?? $tenant['email'],
        'map_query' => urlencode(($branch['address'] ?? $tenant['address']) . ' ' . $tenant['gym_name'])
    ],
    'timings' => [
        'weekdays' => '05:30 AM - 10:30 PM (Mon - Sat)',
        'sunday' => '07:00 AM - 01:00 PM (Sun)',
        'holidays' => 'Special holiday hours announced in advance'
    ],
    'policies' => [
        'Always bring a clean sweat towel to the gym floor.',
        'Wipe down equipment and re-rack weights after completion.',
        'Appropriate athletic footwear and gym apparel are strictly mandatory.',
        'Report any equipment malfunction or safety concern to reception immediately.'
    ],
    'tickets' => array_map(function($t) {
        return [
            'id' => (int)$t['id'],
            'category' => $t['category'],
            'subject' => $t['subject'],
            'message' => $t['message'],
            'reply' => $t['reply'],
            'status' => $t['status'],
            'created_at' => $t['created_at']
        ];
    }, $inquiries)
];

ApiResponse::success($response, 'Support details retrieved.');
