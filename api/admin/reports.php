<?php
/**
 * Gym Admin Financial Reports & Analytics API
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$isYmd = function ($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
if (!$isYmd($startDate)) $startDate = date('Y-01-01');
if (!$isYmd($endDate)) $endDate = date('Y-m-d');
if ($startDate > $endDate) { [$startDate, $endDate] = [$endDate, $startDate]; }

// Revenue calculation
$hasInvoices = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]) > 0;
$revenueBetween = function ($from, $to) use ($tenantId, $hasInvoices) {
    if ($hasInvoices) {
        return (float)DB::fetchValue("SELECT SUM(COALESCE(paid_amount, 0)) FROM invoices WHERE tenant_id = ? AND status IN ('paid', 'partial') AND payment_date BETWEEN ? AND ?", [$tenantId, $from, $to]);
    }
    return (float)DB::fetchValue("SELECT SUM(amount) FROM members WHERE tenant_id = ? AND paid_date BETWEEN ? AND ?", [$tenantId, $from, $to]);
};

// 1. Revenue within range
$revenueInRange = $revenueBetween($startDate, $endDate);

// 2. Expenses within range
$expensesInRange = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?", [$tenantId, $startDate, $endDate]);

// 3. Net Profit / Loss
$netProfit = $revenueInRange - $expensesInRange;

// 4. Attendance in range
$totalAttendanceInRange = (int)DB::fetchValue("SELECT COUNT(*) FROM attendance WHERE tenant_id = ? AND curr_date BETWEEN ? AND ?", [$tenantId, $startDate, $endDate]);

// 5. New members joined in range
$newMembersJoined = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND dor BETWEEN ? AND ?", [$tenantId, $startDate, $endDate]);

// 6. Expense Category Breakdown
$expenseCats = DB::fetchAll("SELECT category, SUM(amount) as total FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ? GROUP BY category", [$tenantId, $startDate, $endDate]);

// 7. Monthly Revenue trend (Last 6 months)
$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("first day of -$i months"));
    $mEnd = date('Y-m-t', strtotime($mStart));
    $mLabel = date('M Y', strtotime($mStart));
    $rev = $revenueBetween($mStart, $mEnd);
    $exp = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?", [$tenantId, $mStart, $mEnd]);
    $monthlyTrend[] = [
        'month' => $mLabel,
        'revenue' => $rev,
        'expenses' => $exp,
        'net' => $rev - $exp
    ];
}

// 8. Membership breakdown
$planEndSql = "DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH)";
$effStatusSql = "CASE WHEN status = 'Active' AND (paid_date IS NULL OR $planEndSql < CURDATE()) THEN 'Expired' ELSE status END";
$activeCount = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND ($effStatusSql = 'Active')", [$tenantId]);
$expiredCount = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND ($effStatusSql = 'Expired')", [$tenantId]);
$totalMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ?", [$tenantId]);

ApiResponse::success([
    'date_range' => ['start' => $startDate, 'end' => $endDate],
    'revenue' => $revenueInRange,
    'expenses' => $expensesInRange,
    'net_profit' => $netProfit,
    'attendance_count' => $totalAttendanceInRange,
    'new_members_count' => $newMembersJoined,
    'expense_categories' => $expenseCats,
    'monthly_trend' => $monthlyTrend,
    'members_summary' => [
        'total' => $totalMembers,
        'active' => $activeCount,
        'expired' => $expiredCount
    ]
], 'Reports retrieved successfully');
