<?php
/**
 * Gym Admin Complete Transaction History & Collection Summary API
 * Multi-Tenant Scoped: Returns Real-Time Collections Breakdown, Search/Filtered Transactions & Receipt Links.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
}

$currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';
$gymName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Our Gym';

// 1. Compute Platform Summary Aggregations
$summary = [
    'total_collection' => 0.0,
    'today_collection' => 0.0,
    'this_month_collection' => 0.0,
    'cash_collection' => 0.0,
    'online_upi_collection' => 0.0,
    'pending_dues' => 0.0,
    'currency' => $currency
];

if (api_table_exists('invoices')) {
    // Total Collection
    $summary['total_collection'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(paid_amount), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Paid', 'Partial')",
        [$tenantId]
    );

    // Today Collection
    $summary['today_collection'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(paid_amount), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Paid', 'Partial') AND payment_date = CURDATE()",
        [$tenantId]
    );

    // This Month Collection
    $summary['this_month_collection'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(paid_amount), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Paid', 'Partial') AND payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
        [$tenantId]
    );

    // Cash Collection
    $summary['cash_collection'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(paid_amount), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Paid', 'Partial') AND LOWER(payment_method) = 'cash'",
        [$tenantId]
    );

    // Online / UPI / Card Collection
    $summary['online_upi_collection'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(paid_amount), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Paid', 'Partial') AND LOWER(payment_method) != 'cash'",
        [$tenantId]
    );

    // Total Pending Dues across Active/Open Invoices
    $summary['pending_dues'] = (float)DB::fetchValue(
        "SELECT COALESCE(SUM(GREATEST(0, amount - paid_amount)), 0) FROM invoices 
         WHERE tenant_id = ? AND status IN ('Partial', 'Unpaid', 'Pending')",
        [$tenantId]
    );
}

// 2. Parse Query Parameters for Transaction List
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$dateFilter = strtolower(trim($_GET['date_filter'] ?? 'all'));
$customFrom = trim($_GET['from_date'] ?? '');
$customTo = trim($_GET['to_date'] ?? '');
$methodFilter = strtolower(trim($_GET['payment_method'] ?? 'all'));
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$staffId = (int)($_GET['staff_id'] ?? 0);

$where = ["i.tenant_id = ?"];
$params = [$tenantId];

// Search filter
if (!empty($search)) {
    $where[] = "(m.fullname LIKE ? OR m.contact LIKE ? OR i.invoice_number LIKE ? OR i.transaction_ref LIKE ? OR CAST(i.member_id AS CHAR) = ? OR CAST(i.id AS CHAR) = ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $search;
    $params[] = $search;
}

// Date filters
if ($dateFilter === 'today') {
    $where[] = "i.payment_date = CURDATE()";
} elseif ($dateFilter === 'yesterday') {
    $where[] = "i.payment_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($dateFilter === 'last_7_days') {
    $where[] = "i.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($dateFilter === 'this_month') {
    $where[] = "i.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($dateFilter === 'last_month') {
    $where[] = "i.payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND i.payment_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($dateFilter === 'custom' && !empty($customFrom) && !empty($customTo)) {
    $where[] = "i.payment_date BETWEEN ? AND ?";
    $params[] = $customFrom;
    $params[] = $customTo;
}

// Payment method filter
if (!empty($methodFilter) && $methodFilter !== 'all') {
    if ($methodFilter === 'online_upi') {
        $where[] = "LOWER(i.payment_method) != 'cash'";
    } else {
        $where[] = "LOWER(i.payment_method) = ?";
        $params[] = $methodFilter;
    }
}

// Status filter
if (!empty($statusFilter) && $statusFilter !== 'all') {
    $where[] = "LOWER(i.status) = ?";
    $params[] = $statusFilter;
}

// Staff collector filter
if ($staffId > 0) {
    $where[] = "i.created_by = ?";
    $params[] = $staffId;
}

$whereSql = implode(' AND ', $where);

// Count Total
$totalCount = (int)DB::fetchValue(
    "SELECT COUNT(*) 
     FROM invoices i
     LEFT JOIN members m ON (i.member_id = m.user_id AND m.tenant_id = i.tenant_id)
     WHERE $whereSql",
    $params
);

$totalPages = ceil($totalCount / $limit);

// Fetch Paginated Rows
$query = "SELECT i.id, i.invoice_number, i.service_name, i.plan_months, i.amount, 
                 i.paid_amount, i.discount, (i.amount - i.paid_amount) as due_amount,
                 i.payment_method, i.payment_date, i.due_date, i.status, 
                 i.transaction_ref, i.notes, i.created_by, i.created_at,
                 i.member_id,
                 COALESCE(m.fullname, 'Unknown Member') as member_name,
                 COALESCE(m.contact, '') as member_phone,
                 m.avatar as member_avatar,
                 m.photo as member_photo,
                 COALESCE(u.fullname, u.username, 'Admin') as created_by_name
          FROM invoices i
          LEFT JOIN members m ON (i.member_id = m.user_id AND m.tenant_id = i.tenant_id)
          LEFT JOIN users u ON i.created_by = u.id
          WHERE $whereSql
          ORDER BY i.payment_date DESC, i.id DESC
          LIMIT $limit OFFSET $offset";

$rows = DB::fetchAll($query, $params);

$transactions = [];
foreach ($rows as $row) {
    $invAmount = (float)$row['amount'];
    $invPaid = (float)$row['paid_amount'];
    $invDiscount = (float)($row['discount'] ?? 0.0);
    $invDue = max(0.0, $invAmount - $invPaid - $invDiscount);

    $avatar = api_member_avatar_url($row['member_avatar'] ?? null, $row['member_photo'] ?? null);

    $transactions[] = [
        'id' => (int)$row['id'],
        'invoice_number' => $row['invoice_number'],
        'transaction_ref' => $row['transaction_ref'] ?: ('TXN-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
        'member_id' => (int)$row['member_id'],
        'member_name' => $row['member_name'],
        'member_phone' => $row['member_phone'],
        'member_avatar' => $avatar,
        'service_name' => $row['service_name'] ?: 'Gym Membership',
        'plan_months' => (int)($row['plan_months'] ?? 1),
        'amount' => $invAmount,
        'paid_amount' => $invPaid,
        'discount' => $invDiscount,
        'due_amount' => $invDue,
        'payment_method' => $row['payment_method'] ?: 'Cash',
        'status' => ucfirst(strtolower($row['status'] ?: 'Paid')),
        'payment_date' => $row['payment_date'],
        'due_date' => $row['due_date'],
        'collected_by' => $row['created_by_name'],
        'created_at' => $row['created_at'],
        'notes' => $row['notes'] ?? '',
        'receipt_url' => base_url('/api/member/receipt_html.php?id=' . $row['id'])
    ];
}

// Fetch Staff List for Filter Dropdown
$staffList = DB::fetchAll(
    "SELECT id, fullname, username, role 
     FROM users 
     WHERE tenant_id = ? AND role IN ('gym_admin', 'staff', 'trainer') AND status = 'active'
     ORDER BY fullname ASC",
    [$tenantId]
);

ApiResponse::success([
    'summary' => $summary,
    'pagination' => [
        'current_page' => $page,
        'limit' => $limit,
        'total_count' => $totalCount,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages
    ],
    'transactions' => $transactions,
    'staff_filter_options' => array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'name' => $s['fullname'] ?: $s['username'],
            'role' => ucfirst(str_replace('_', ' ', $s['role']))
        ];
    }, $staffList)
]);
