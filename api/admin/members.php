<?php
/**
 * Gym Admin Members Directory & Universal Search API
 * Multi-filter search by Name, Phone, Address, Dues, Expiry Status
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$search = trim($_GET['search'] ?? '');
$filter = strtolower(trim($_GET['filter'] ?? 'all')); // all, dues, expiring, expired, active
$sort = strtolower(trim($_GET['sort'] ?? 'recent'));   // recent, due_high, expiry_soon, name_asc
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

// Expiry SQL calculation
$planEndSql = "DATE_ADD(m.paid_date, INTERVAL GREATEST(1, CAST(m.plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN m.status = 'Active' AND (m.paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE m.status END";

// Build Query
$sql = "SELECT m.*, 
               $effStatusSql AS effective_status,
               $planEndSql AS computed_expiry,
               DATEDIFF($planEndSql, CURDATE()) AS days_left,
               COALESCE(inv_agg.total_invoiced, m.amount, 0) AS total_fee,
               COALESCE(inv_agg.total_paid, m.amount, 0) AS total_paid_amount,
               COALESCE(inv_agg.total_due, m.due_amount, 0) AS calculated_due,
               COALESCE(inv_agg.latest_due_date, m.due_date) AS active_due_date
        FROM members m
        LEFT JOIN (
            SELECT member_id, 
                   SUM(amount) AS total_invoiced,
                   SUM(paid_amount) AS total_paid,
                   SUM(GREATEST(0, amount - paid_amount)) AS total_due,
                   MAX(due_date) AS latest_due_date
            FROM invoices
            WHERE tenant_id = ?
            GROUP BY member_id
        ) inv_agg ON m.user_id = inv_agg.member_id
        WHERE m.tenant_id = ?";

$params = [$tenantId, $tenantId];

// 1. Search Query
if (!empty($search)) {
    $sql .= " AND (m.fullname LIKE ? OR m.username LIKE ? OR m.contact LIKE ? OR m.address LIKE ? OR m.user_id = ?)";
    $term = "%$search%";
    $numSearch = is_numeric($search) ? (int)$search : -1;
    $params = array_merge($params, [$term, $term, $term, $term, $numSearch]);
}

// 2. Filter Filter Type
if ($filter === 'dues') {
    $sql .= " AND (inv_agg.total_due > 0 OR m.due_amount > 0)";
} elseif ($filter === 'expiring') {
    $sql .= " AND $effStatusSql = 'Active' AND $planEndSql >= CURDATE() AND $planEndSql <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'expired') {
    $sql .= " AND $effStatusSql = 'Expired'";
} elseif ($filter === 'active') {
    $sql .= " AND $effStatusSql = 'Active'";
}

// 3. Sorting
if ($sort === 'due_high') {
    $sql .= " ORDER BY calculated_due DESC, m.dor DESC";
} elseif ($sort === 'expiry_soon') {
    $sql .= " ORDER BY computed_expiry ASC, m.dor DESC";
} elseif ($sort === 'name_asc') {
    $sql .= " ORDER BY m.fullname ASC";
} else {
    // Default: recent registrations / updates first
    $sql .= " ORDER BY m.dor DESC, m.user_id DESC";
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS count_table";
$totalCount = (int)DB::fetchValue($countSql, $params);

// Pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$members = DB::fetchAll($sql, $params);

$currency = $tenant['currency'] ?: '₹';
$gymName = $tenant['gym_name'] ?: 'Our Gym';

$formattedList = [];
foreach ($members as $m) {
    $avatarUrl = null;
    if (!empty($m['avatar'])) {
        if (str_starts_with($m['avatar'], 'http')) {
            $avatarUrl = $m['avatar'];
        } elseif (file_exists(__DIR__ . '/../../uploads/avatars/' . $m['avatar'])) {
            $avatarUrl = base_url('/uploads/avatars/' . $m['avatar']);
        } else {
            $avatarUrl = base_url('/img/' . $m['avatar']);
        }
    }

    $daysRemaining = (int)($m['days_left'] ?? 0);
    $status = $m['effective_status'] ?? 'Active';
    $dueAmount = (float)($m['calculated_due'] ?? 0.00);
    $paidAmount = (float)($m['total_paid_amount'] ?? 0.00);
    $totalFee = (float)($m['total_fee'] ?? 0.00);
    if ($totalFee <= 0) {
        $totalFee = (float)($m['amount'] ?? 0.00);
    }

    $dueDate = !empty($m['active_due_date']) ? $m['active_due_date'] : null;
    $phone = $m['contact'] ?? '';

    // Generate pre-formatted WhatsApp reminder text
    $waText = "";
    if ($dueAmount > 0) {
        $waText = "Hello " . $m['fullname'] . ", this is a gentle reminder from *" . $gymName . "*. You have a pending fee of *" . $currency . number_format($dueAmount, 2) . "*";
        if ($dueDate) {
            $waText .= " due by *" . date('d M Y', strtotime($dueDate)) . "*";
        }
        $waText .= ". Please clear your dues at the gym counter or via UPI. Thank you!";
    } elseif ($status === 'Expired' || $daysRemaining <= 7) {
        $waText = "Hello " . $m['fullname'] . ", your gym membership at *" . $gymName . "* is expiring on *" . date('d M Y', strtotime($m['computed_expiry'])) . "*. Please renew your membership to continue your workout uninterrupted. Thank you!";
    }

    $formattedList[] = [
        'member_id' => (int)$m['user_id'],
        'fullname' => $m['fullname'],
        'username' => $m['username'],
        'phone' => $phone,
        'email' => $m['email'] ?? '',
        'gender' => $m['gender'] ?? 'Not Specified',
        'address' => $m['address'] ?? '',
        'avatar' => $avatarUrl,
        'services' => $m['services'] ?? 'General Fitness',
        'plan_months' => (int)($m['plan'] ?? 1),
        'membership_status' => $status,
        'start_date' => $m['dor'] ?? $m['paid_date'] ?? date('Y-m-d'),
        'expiry_date' => $m['computed_expiry'] ?? date('Y-m-d'),
        'days_remaining' => $daysRemaining,
        'total_fee' => $totalFee,
        'paid_amount' => $paidAmount,
        'due_amount' => $dueAmount,
        'due_date' => $dueDate,
        'attendance_count' => (int)($m['attendance_count'] ?? 0),
        'whatsapp_reminder' => $waText
    ];
}

ApiResponse::success([
    'members' => $formattedList,
    'pagination' => [
        'total' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalCount / $limit)
    ],
    'active_filter' => $filter,
    'search_query' => $search
], 'Members retrieved successfully');
