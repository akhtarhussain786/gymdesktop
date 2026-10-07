<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']); // P&L / revenue is owner-only

$page = 'reports';
$pageTitle = 'Reports & Financial Analytics';
$pageSubtitle = 'Tenant-specific revenue, expenses, profit and loss, attendance patterns, and member growth';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

// Date range filters (validated Y-m-d; DATE columns so BETWEEN is inclusive of the end day)
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$isYmd = function ($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
if (!$isYmd($startDate)) $startDate = date('Y-01-01');
if (!$isYmd($endDate)) $endDate = date('Y-m-d');
if ($startDate > $endDate) { [$startDate, $endDate] = [$endDate, $startDate]; }

// Revenue = money actually collected on invoices (paid / partially paid), by payment date.
// members.amount is only the member's *latest* plan price (renewals overwrite it, and
// paid_date is not a reliable payment date), so it is used only for tenants that have no
// invoices at all (pure legacy data).
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
    // Anchor on the 1st of the month: strtotime("-N months") from e.g. the 31st skips short months
    $mStart = date('Y-m-01', strtotime("first day of -$i months"));
    $mEnd = date('Y-m-t', strtotime($mStart));
    $mLabel = date('M Y', strtotime($mStart));
    $rev = $revenueBetween($mStart, $mEnd);
    $exp = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?", [$tenantId, $mStart, $mEnd]);
    $monthlyTrend[] = ['month' => $mLabel, 'revenue' => $rev, 'expenses' => $exp];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Filter & Print Bar -->
<div class="card no-print">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);">Date Filter:</div>
                <div>
                    <input type="date" name="start_date" value="<?php echo e($startDate); ?>" class="form-control" style="width: 160px; height: 38px;" />
                </div>
                <div style="color: var(--text-muted);">to</div>
                <div>
                    <input type="date" name="end_date" value="<?php echo e($endDate); ?>" class="form-control" style="width: 160px; height: 38px;" />
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </div>
            <div>
                <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-print"></i> Print Statement
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Financial P&L Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Total Revenue</h3>
            <div class="stat-value"><?php echo format_currency($revenueInRange); ?></div>
            <div class="stat-meta">In selected date range</div>
        </div>
        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
    </div>

    <div class="stat-card stat-danger">
        <div class="stat-info">
            <h3>Total Expenses</h3>
            <div class="stat-value"><?php echo format_currency($expensesInRange); ?></div>
            <div class="stat-meta">In selected date range</div>
        </div>
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
    </div>

    <div class="stat-card <?php echo $netProfit >= 0 ? 'stat-success' : 'stat-danger'; ?>">
        <div class="stat-info">
            <h3>Net Profit / (Loss)</h3>
            <div class="stat-value" style="color: <?php echo $netProfit >= 0 ? 'var(--secondary)' : 'var(--danger)'; ?>;">
                <?php echo format_currency($netProfit); ?>
            </div>
            <div class="stat-meta">Net cash bottom line</div>
        </div>
        <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>New Registrations</h3>
            <div class="stat-value"><?php echo $newMembersJoined; ?></div>
            <div class="stat-meta">Total attendances: <?php echo $totalAttendanceInRange; ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
    </div>
</div>

<!-- Monthly Revenue vs Expense Chart -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-chart-line"></i>
            <span>6-Month Financial Performance Trend (Revenue vs Expenses)</span>
        </div>
    </div>
    <div class="card-body">
        <canvas id="financialTrendChart" style="max-height: 280px;"></canvas>
    </div>
</div>

<!-- Expense Category Breakdown Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-pie-chart"></i>
            <span>Expense Category Breakdown</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total Amount Spent</th>
                        <th>Share of Total Expenses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenseCats)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                No expenses logged in this date range.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenseCats as $ec): ?>
                            <?php $share = $expensesInRange > 0 ? round(($ec['total'] / $expensesInRange) * 100, 1) : 0; ?>
                            <tr>
                                <td><strong><?php echo e($ec['category']); ?></strong></td>
                                <td><strong style="color: var(--danger);"><?php echo format_currency($ec['total']); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="flex: 1; height: 8px; background: var(--bg-app); border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo $share; ?>%; height: 100%; background: var(--accent);"></div>
                                        </div>
                                        <span style="font-size: 0.82rem; font-weight: 700;"><?php echo $share; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trendCtx = document.getElementById('financialTrendChart').getContext('2d');
    const trendLabels = <?php echo json_encode(array_column($monthlyTrend, 'month')); ?>;
    const revData = <?php echo json_encode(array_column($monthlyTrend, 'revenue')); ?>;
    const expData = <?php echo json_encode(array_column($monthlyTrend, 'expenses')); ?>;

    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Revenue ($)',
                    data: revData,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderRadius: 6
                },
                {
                    label: 'Expenses ($)',
                    data: expData,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
