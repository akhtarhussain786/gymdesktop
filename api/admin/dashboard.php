<?php
/**
 * Gym Admin Dashboard API
 * Returns live operational KPIs matching web admin index.php:
 * Active Members, Expired, Expiring Soon, Today's Check-ins, Revenue, Expenses, Net Profit, Dues, Staff & Equipment counts.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

// 1. Total & Active Members
$totalMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ?", [$tenantId]);

// Plan End & Expiry Calculation
$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$activeMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'Active' AND $planEndSql >= CURDATE()", [$tenantId]);
$expiredMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND (status = 'Expired' OR (status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE())))", [$tenantId]);

// 2. Members Expiring in next 7 days
$expiring7Days = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM members 
     WHERE tenant_id = ? AND status = 'Active' 
     AND $planEndSql BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
    [$tenantId]
);

// 3. Attendance Check-ins Today
$todayDate = date('Y-m-d');
$todayCheckins = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM attendance 
     WHERE tenant_id = ? AND curr_date = ?",
    [$tenantId, $todayDate]
);

// 4. Monthly Revenue, Expenses & Net Profit
$thisMonth = date('Y-m');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$monthlyRevenue = 0.00;
if (api_table_exists('invoices') && (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]) > 0) {
    $monthlyRevenue = (float)DB::fetchValue(
        "SELECT SUM(COALESCE(paid_amount, amount, 0)) FROM invoices WHERE tenant_id = ? AND status IN ('paid', 'Paid', 'partial', 'Partial') AND payment_date BETWEEN ? AND ?",
        [$tenantId, $monthStart, $monthEnd]
    );
} else {
    $monthlyRevenue = (float)DB::fetchValue(
        "SELECT SUM(amount) FROM members WHERE tenant_id = ? AND paid_date BETWEEN ? AND ?",
        [$tenantId, $monthStart, $monthEnd]
    );
}

$monthlyExpenses = (float)DB::fetchValue(
    "SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date LIKE ?",
    [$tenantId, "$thisMonth%"]
);

$netProfit = $monthlyRevenue - $monthlyExpenses;

// 5. Staff & Equipment counts
$totalStaff = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ?", [$tenantId]);
$totalTrainers = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);
$totalEquipment = (int)DB::fetchValue("SELECT COUNT(*) FROM equipment WHERE tenant_id = ?", [$tenantId]);

// 6. Dues & Unpaid Balances Calculation
$duesCount = 0;
$totalDuesAmount = 0.00;
if (api_column_exists('members', 'due_amount')) {
    $memberDues = DB::fetchOne(
        "SELECT COUNT(*) as member_due_count, COALESCE(SUM(due_amount), 0) as member_total_due 
         FROM members WHERE tenant_id = ? AND due_amount > 0",
        [$tenantId]
    );
    if ($memberDues) {
        $duesCount = (int)($memberDues['member_due_count'] ?? 0);
        $totalDuesAmount = (float)($memberDues['member_total_due'] ?? 0.00);
    }
}

// 7. Recent Dues List
$recentDues = [];
if (api_column_exists('members', 'due_amount')) {
    $recentDuesRows = DB::fetchAll(
        "SELECT user_id, fullname, contact as phone, avatar, services, dor, due_amount, due_date
         FROM members 
         WHERE tenant_id = ? AND due_amount > 0
         ORDER BY due_amount DESC, dor DESC
         LIMIT 5",
        [$tenantId]
    );
    foreach ($recentDuesRows as $rd) {
        $avatarUrl = null;
        if (!empty($rd['avatar'])) {
            if (str_starts_with($rd['avatar'], 'http')) {
                $avatarUrl = $rd['avatar'];
            } elseif (file_exists(__DIR__ . '/../../uploads/avatars/' . $rd['avatar'])) {
                $avatarUrl = base_url('/uploads/avatars/' . $rd['avatar']);
            } else {
                $avatarUrl = base_url('/img/' . $rd['avatar']);
            }
        }
        $recentDues[] = [
            'member_id' => (int)$rd['user_id'],
            'fullname' => $rd['fullname'],
            'phone' => $rd['phone'] ?? '',
            'avatar' => $avatarUrl,
            'services' => $rd['services'] ?? 'General Fitness',
            'due_amount' => (float)$rd['due_amount'],
            'due_date' => !empty($rd['due_date']) ? $rd['due_date'] : null
        ];
    }
}

// 8. Recent 5 Registered Members
$recentMembersRows = DB::fetchAll(
    "SELECT user_id, fullname, contact as phone, avatar, services, amount, status, dor, plan
     FROM members 
     WHERE tenant_id = ? 
     ORDER BY dor DESC, user_id DESC 
     LIMIT 5",
    [$tenantId]
);
$recentMembers = [];
foreach ($recentMembersRows as $rm) {
    $avatarUrl = null;
    if (!empty($rm['avatar'])) {
        if (str_starts_with($rm['avatar'], 'http')) {
            $avatarUrl = $rm['avatar'];
        } elseif (file_exists(__DIR__ . '/../../uploads/avatars/' . $rm['avatar'])) {
            $avatarUrl = base_url('/uploads/avatars/' . $rm['avatar']);
        } else {
            $avatarUrl = base_url('/img/' . $rm['avatar']);
        }
    }
    $recentMembers[] = [
        'member_id' => (int)$rm['user_id'],
        'fullname' => $rm['fullname'],
        'phone' => $rm['phone'] ?? '',
        'avatar' => $avatarUrl,
        'services' => $rm['services'] ?? 'General Fitness',
        'amount' => (float)($rm['amount'] ?? 0.00),
        'status' => $rm['status'] ?? 'Active',
        'dor' => $rm['dor'] ?? date('Y-m-d')
    ];
}

// 9. Service Distribution
$serviceStatsRows = DB::fetchAll("SELECT services, COUNT(*) as count FROM members WHERE tenant_id = ? GROUP BY services", [$tenantId]);
$serviceStats = [];
foreach ($serviceStatsRows as $sr) {
    $serviceStats[] = [
        'service' => $sr['services'] ?: 'General Fitness',
        'count' => (int)$sr['count']
    ];
}

// 10. Gym Profile & UPI Details
$logoUrl = null;
if (!empty($tenant['logo'])) {
    if (str_starts_with($tenant['logo'], 'http')) {
        $logoUrl = $tenant['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . $tenant['logo'])) {
        $logoUrl = base_url('/uploads/logos/' . $tenant['logo']);
    } else {
        $logoUrl = base_url('/img/' . $tenant['logo']);
    }
}

ApiResponse::success([
    'gym' => [
        'id' => $tenantId,
        'name' => $tenant['gym_name'],
        'code' => $tenant['gym_code'] ?: 'GYM-' . $tenantId,
        'logo' => $logoUrl,
        'currency' => !empty($tenant['currency']) ? $tenant['currency'] : '₹',
        'upi_id' => $tenant['upi_id'] ?? '',
        'phone' => $tenant['phone'] ?? '',
        'address' => $tenant['address'] ?? ''
    ],
    'kpis' => [
        'total_members' => $totalMembers,
        'active_members' => $activeMembers,
        'expired_members' => $expiredMembers,
        'expiring_7days' => $expiring7Days,
        'today_checkins' => $todayCheckins,
        'monthly_revenue' => $monthlyRevenue,
        'monthly_expenses' => $monthlyExpenses,
        'net_profit' => $netProfit,
        'total_staff' => $totalStaff,
        'total_trainers' => $totalTrainers,
        'total_equipment' => $totalEquipment,
        'dues_pending_count' => $duesCount,
        'total_dues_amount' => $totalDuesAmount,
    ],
    'recent_dues' => $recentDues,
    'recent_members' => $recentMembers,
    'service_stats' => $serviceStats
], 'Dashboard statistics fetched successfully');
