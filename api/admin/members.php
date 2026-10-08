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

// Plan End & Expiry Calculation matching web admin
$planEndSql = "DATE_ADD(m.paid_date, INTERVAL GREATEST(1, CAST(m.plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN m.status = 'Active' AND (m.paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE m.status END";

// Base Query on members table
$whereClauses = ["m.tenant_id = ?"];
$params = [$tenantId];

// 1. Search Query
if (!empty($search)) {
    $whereClauses[] = "(m.fullname LIKE ? OR m.username LIKE ? OR m.contact LIKE ? OR m.address LIKE ? OR m.email LIKE ? OR m.user_id = ?)";
    $term = "%$search%";
    $numSearch = is_numeric($search) ? (int)$search : -1;
    $params = array_merge($params, [$term, $term, $term, $term, $term, $numSearch]);
}

// 2. Filter Type
if ($filter === 'dues') {
    if (api_column_exists('members', 'due_amount')) {
        $whereClauses[] = "m.due_amount > 0";
    }
} elseif ($filter === 'expiring') {
    $whereClauses[] = "m.status = 'Active' AND $planEndSql >= CURDATE() AND $planEndSql <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'expired') {
    $whereClauses[] = "$effStatusSql = 'Expired'";
} elseif ($filter === 'active') {
    $whereClauses[] = "$effStatusSql = 'Active'";
}

$whereSql = implode(' AND ', $whereClauses);

// Count total
$totalCount = (int)DB::fetchValue("SELECT COUNT(*) FROM members m WHERE $whereSql", $params);

// 3. Sorting
$orderBy = "m.dor DESC, m.user_id DESC";
if ($sort === 'due_high' && api_column_exists('members', 'due_amount')) {
    $orderBy = "m.due_amount DESC, m.dor DESC";
} elseif ($sort === 'expiry_soon') {
    $orderBy = "$planEndSql ASC, m.dor DESC";
} elseif ($sort === 'name_asc') {
    $orderBy = "m.fullname ASC";
}

$dataSql = "SELECT m.*, 
                   $effStatusSql AS effective_status,
                   $planEndSql AS computed_expiry,
                   DATEDIFF($planEndSql, CURDATE()) AS days_left
            FROM members m
            WHERE $whereSql
            ORDER BY $orderBy
            LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$limit, $offset]);
$members = DB::fetchAll($dataSql, $dataParams);

$currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';
$gymName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Our Gym';

$formattedList = [];
foreach ($members as $m) {
    $avatarUrl = api_member_avatar_url($m['avatar'] ?? null, $m['photo'] ?? null);

    $daysRemaining = (int)($m['days_left'] ?? 0);
    $status = $m['effective_status'] ?? $m['status'] ?? 'Active';
    $dueAmount = isset($m['due_amount']) ? (float)$m['due_amount'] : 0.00;
    $totalFee = (float)($m['amount'] ?? 0.00);
    $paidAmount = max(0.0, $totalFee - $dueAmount);
    $dueDate = !empty($m['due_date']) ? $m['due_date'] : null;
    $phone = $m['contact'] ?? '';

    // Generate WhatsApp reminder text
    $waText = "";
    if ($dueAmount > 0) {
        $waText = "Hello " . $m['fullname'] . ", this is a reminder from *" . $gymName . "*. You have a pending fee of *" . $currency . number_format($dueAmount, 2) . "*";
        if ($dueDate) {
            $waText .= " due by *" . date('d M Y', strtotime($dueDate)) . "*";
        }
        $waText .= ". Please clear your dues at the gym counter. Thank you!";
    } elseif ($status === 'Expired' || $daysRemaining <= 7) {
        $waText = "Hello " . $m['fullname'] . ", your gym membership at *" . $gymName . "* is expiring on *" . date('d M Y', strtotime($m['computed_expiry'])) . "*. Please renew your membership to continue your workout uninterrupted. Thank you!";
    }

    $formattedList[] = [
        'member_id' => (int)$m['user_id'],
        'fullname' => $m['fullname'],
        'username' => $m['username'],
        'phone' => $phone,
        'email' => $m['email'] ?? '',
        'gender' => $m['gender'] ?? 'Male',
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
        'total_pages' => max(1, (int)ceil($totalCount / $limit))
    ],
    'active_filter' => $filter,
    'search_query' => $search
], 'Members retrieved successfully');
