<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_attendance';
$pageTitle = 'My Gym Attendance Log';
$pageSubtitle = 'Personal check-in records, time logs, and consistency streak';
$memberId = $_SESSION['user_id'];
$tenantId = Tenant::getTenantId();

$attendances = DB::fetchAll("SELECT * FROM attendance WHERE user_id = ? AND tenant_id = ? ORDER BY curr_date DESC", [$memberId, $tenantId]);
$totalSessions = count($attendances);
$thisMonthSessions = count(array_filter($attendances, fn($a) => strpos($a['curr_date'], date('Y-m')) === 0));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>This Month Sessions</h3>
            <div class="stat-value"><?php echo $thisMonthSessions; ?></div>
            <div class="stat-meta"><?php echo date('F Y'); ?> workout check-ins</div>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Total Lifetime Sessions</h3>
            <div class="stat-value"><?php echo $totalSessions; ?></div>
            <div class="stat-meta">Total gym visits logged</div>
        </div>
        <div class="stat-icon"><i class="fas fa-fire"></i></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>Complete Attendance History</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Check-In Time</th>
                        <th>Check-Out Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendances)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No attendance records logged yet. Make sure to check in at the reception!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendances as $idx => $a): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><strong><?php echo format_date($a['curr_date']); ?></strong></td>
                                <td><?php echo e($a['curr_time']); ?></td>
                                <td><?php echo e($a['check_out_time'] ?: '—'); ?></td>
                                <td><span class="status-badge badge-success"><i class="fas fa-check"></i> Present</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
