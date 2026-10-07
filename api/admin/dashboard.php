<?php
/**
 * Gym Admin Dashboard API
 * Returns live operational KPIs: Active Members, Total Dues Pending, Expiring Soon, Today's Check-ins
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

// 1. Total & Active Members
$totalMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ?", [$tenantId]);

// Plan End & Expiry Calculation
$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE status END";

$activeMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND $effStatusSql = 'Active'", [$tenantId]);
$expiredMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND $effStatusSql = 'Expired'", [$tenantId]);

// 2. Members Expiring in next 7 days
$expiring7Days = (int)DB::fetchValue(
    "SELECT COUNT(*) FROM members 
     WHERE tenant_id = ? AND status = 'Active' 
     AND $planEndSql >= CURDATE() AND $planEndSql <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
    [$tenantId]
);

// 3. Attendance Check-ins Today
$todayCheckins = (int)DB::fetchValue(
    "SELECT COUNT(DISTINCT user_id) FROM attendance 
     WHERE tenant_id = ? AND curr_date = CURDATE()",
    [$tenantId]
);

// 4. Dues & Unpaid Balances Calculation
// Check invoices with status 'Partial' or 'Unpaid', or where amount > paid_amount
$duesStats = DB::fetchOne(
    "SELECT 
        COUNT(DISTINCT member_id) as dues_count, 
        COALESCE(SUM(GREATEST(0, amount - paid_amount)), 0) as total_dues 
     FROM invoices 
     WHERE tenant_id = ? AND status IN ('Partial', 'Unpaid', 'Pending') AND (amount - paid_amount) > 0",
    [$tenantId]
);

$duesCount = (int)($duesStats['dues_count'] ?? 0);
$totalDuesAmount = (float)($duesStats['total_dues'] ?? 0.00);

// Fallback: Also check members table if any members have due_amount column with balance
if (api_column_exists('members', 'due_amount')) {
    $memberDues = DB::fetchOne(
        "SELECT COUNT(*) as member_due_count, COALESCE(SUM(due_amount), 0) as member_total_due 
         FROM members WHERE tenant_id = ? AND due_amount > 0",
        [$tenantId]
    );
    if ((int)($memberDues['member_due_count'] ?? 0) > $duesCount) {
        $duesCount = (int)$memberDues['member_due_count'];
        $totalDuesAmount = max($totalDuesAmount, (float)$memberDues['member_total_due']);
    }
}

// 5. Recent Dues List (top 5 members with highest dues)
$recentDues = DB::fetchAll(
    "SELECT m.user_id, m.fullname, m.contact as phone, m.avatar, m.services, m.dor,
            COALESCE(inv.due_balance, m.due_amount, 0) as due_amount,
            COALESCE(inv.due_date, m.due_date) as due_date
     FROM members m
     LEFT JOIN (
         SELECT member_id, SUM(amount - paid_amount) as due_balance, MAX(due_date) as due_date
         FROM invoices 
         WHERE tenant_id = ? AND status IN ('Partial', 'Unpaid', 'Pending') AND (amount - paid_amount) > 0
         GROUP BY member_id
     ) inv ON m.user_id = inv.member_id
     WHERE m.tenant_id = ? AND (inv.due_balance > 0 OR m.due_amount > 0)
     ORDER BY due_amount DESC
     LIMIT 5",
    [$tenantId, $tenantId]
);

$formattedRecentDues = [];
foreach ($recentDues as $rd) {
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

    $formattedRecentDues[] = [
        'member_id' => (int)$rd['user_id'],
        'fullname' => $rd['fullname'],
        'phone' => $rd['phone'] ?? '',
        'avatar' => $avatarUrl,
        'services' => $rd['services'] ?? 'General Fitness',
        'due_amount' => (float)$rd['due_amount'],
        'due_date' => !empty($rd['due_date']) ? $rd['due_date'] : null
    ];
}

// 6. Gym Profile & UPI Details
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
        'currency' => $tenant['currency'] ?: '₹',
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
        'dues_pending_count' => $duesCount,
        'total_dues_amount' => $totalDuesAmount,
    ],
    'recent_dues' => $formattedRecentDues
], 'Dashboard statistics fetched successfully');
