<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$page = 'attendance';
$pageTitle = 'Attendance Tracking';
$pageSubtitle = 'Log member check-ins and check-outs in real time';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent(); // Guarantees local gym timezone

$todayDate = date('Y-m-d');
$rawDate = $_GET['date'] ?? $todayDate;
$selectedDate = $todayDate;
if (!empty($rawDate)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
        $selectedDate = $rawDate;
    } elseif (preg_match('/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/', $rawDate, $m)) {
        $selectedDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    } else {
        $ts = strtotime($rawDate);
        if ($ts !== false) {
            $selectedDate = date('Y-m-d', $ts);
        }
    }
}

$prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
$isToday = ($selectedDate === $todayDate);

$search = trim($_GET['search'] ?? '');

// Handle Check-In / Check-Out Actions (POST + CSRF; were GET links)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['member_id'])) {
    Auth::verifyCsrf();
    $action = $_POST['action'];
    $memberId = (int)$_POST['member_id'];
    // TIME column: store 24h time ('h:i A' was truncated by MySQL, losing AM/PM)
    $currentTime = date('H:i:s');
    $displayTime = date('h:i A');

    // Member must belong to this tenant; attendance can't be logged for future dates
    $member = DB::fetchOne("SELECT user_id, status, paid_date, plan FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
    if (!$member) {
        redirect("attendance.php?date=" . urlencode($selectedDate), 'error', 'Member not found.');
    }
    if ($selectedDate > date('Y-m-d')) {
        redirect("attendance.php?date=" . urlencode($selectedDate), 'error', 'Attendance cannot be recorded for a future date.');
    }

    if ($action === 'checkin') {
        $planMonths = max(1, (int)$member['plan']);
        $expiry = !empty($member['paid_date']) ? date('Y-m-d', strtotime("+{$planMonths} months", strtotime($member['paid_date']))) : null;
        if ($member['status'] !== 'Active' || $expiry === null || $expiry < $selectedDate) {
            redirect("attendance.php?date=" . urlencode($selectedDate), 'error', 'Membership is not active/has expired. Please renew before check-in.');
        }
        $existing = DB::fetchOne("SELECT id FROM attendance WHERE user_id = ? AND curr_date = ? AND tenant_id = ?", [$memberId, $selectedDate, $tenantId]);
        if (!$existing) {
            DB::insert('attendance', [
                'tenant_id' => $tenantId,
                'user_id' => $memberId,
                'curr_date' => $selectedDate,
                'curr_time' => $currentTime,
                'present' => 1
            ]);
            DB::query("UPDATE members SET attendance_count = attendance_count + 1 WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
            set_flash('success', 'Member checked in successfully at ' . $displayTime);
        } else {
            set_flash('info', 'Member is already checked in for this date.');
        }
    } elseif ($action === 'checkout') {
        DB::update('attendance', ['check_out_time' => $currentTime], 'user_id = ? AND curr_date = ? AND tenant_id = ? AND check_out_time IS NULL', [$memberId, $selectedDate, $tenantId]);
        set_flash('info', 'Member checked out at ' . $displayTime);
    } elseif ($action === 'remove' && in_array($_SESSION['role'] ?? '', ['gym_admin', 'staff', 'super_admin'], true)) {
        // Safe delete: Only remove this date's entry and decrement attendance_count by 1
        $deleted = DB::delete('attendance', 'user_id = ? AND curr_date = ? AND tenant_id = ?', [$memberId, $selectedDate, $tenantId]);
        if ($deleted) {
            DB::query("UPDATE members SET attendance_count = GREATEST(0, attendance_count - 1) WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
            set_flash('warning', "Today's check-in record removed.");
        }
    }
    redirect("attendance.php?date=" . urlencode($selectedDate));
}

// Fetch Active Members with Attendance status on selectedDate
$sql = "SELECT m.*, a.id as attend_id, a.curr_time as check_in_time, a.check_out_time, a.present 
        FROM members m 
        LEFT JOIN attendance a ON m.user_id = a.user_id AND a.curr_date = ? AND a.tenant_id = ?
        WHERE m.tenant_id = ?
          AND (a.id IS NOT NULL OR (m.status = 'Active' AND DATE_ADD(m.paid_date, INTERVAL GREATEST(1, CAST(m.plan AS UNSIGNED)) MONTH) >= ?))";
$params = [$selectedDate, $tenantId, $tenantId, $selectedDate];

if (!empty($search)) {
    $sql .= " AND (m.fullname LIKE ? OR m.contact LIKE ? OR m.services LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}

$sql .= " ORDER BY m.fullname ASC";
$members = DB::fetchAll($sql, $params);

// Daily stats
$presentCount = (int)DB::fetchValue("SELECT COUNT(*) FROM attendance WHERE curr_date = ? AND tenant_id = ?", [$selectedDate, $tenantId]);
$totalActive = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE status = 'Active' AND tenant_id = ? AND DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH) >= ?", [$tenantId, $selectedDate]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<?php if (!$isToday): ?>
    <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); color: var(--primary); padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-size: 0.9rem; flex-wrap: wrap; gap: 10px;">
        <div>
            <i class="fas fa-info-circle"></i> Viewing attendance archive for <strong><?php echo format_date($selectedDate); ?></strong>.
        </div>
        <a href="attendance.php" class="btn btn-primary btn-sm">
            <i class="fas fa-bolt"></i> Switch to Live Today (<?php echo format_date($todayDate); ?>)
        </a>
    </div>
<?php endif; ?>

<!-- Attendance Summary Card -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Present <?php echo $isToday ? 'Today' : '(' . date('M d', strtotime($selectedDate)) . ')'; ?></h3>
            <div class="stat-value"><?php echo $presentCount; ?></div>
            <div class="stat-meta"><?php echo format_date($selectedDate); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Absent <?php echo $isToday ? 'Today' : '(' . date('M d', strtotime($selectedDate)) . ')'; ?></h3>
            <div class="stat-value"><?php echo max(0, $totalActive - $presentCount); ?></div>
            <div class="stat-meta">Active gym members</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-user-times"></i>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Turnout Rate</h3>
            <?php $turnout = $totalActive > 0 ? round(($presentCount / $totalActive) * 100) : 0; ?>
            <div class="stat-value"><?php echo $turnout; ?>%</div>
            <div class="stat-meta">Attendance percentage</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-percentage"></i>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-calendar-check"></i>
            <span>Daily Attendance Register (<?php echo format_date($selectedDate); ?>)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <a href="attendance.php?date=<?php echo urlencode($prevDate); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-secondary btn-sm" title="Previous Day">
                <i class="fas fa-chevron-left"></i>
            </a>
            <form method="GET" action="" style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                <input type="date" name="date" value="<?php echo e($selectedDate); ?>" class="form-control" style="width: 155px; padding: 4px 8px;" onchange="this.form.submit()" />
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo e($search); ?>" />
                <?php endif; ?>
            </form>
            <a href="attendance.php?date=<?php echo urlencode($nextDate); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-secondary btn-sm" title="Next Day">
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="attendance.php" class="btn <?php echo $isToday ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <form method="GET" action="" style="display: inline-flex; gap: 6px; margin: 0;">
                <input type="hidden" name="date" value="<?php echo e($selectedDate); ?>" />
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search member..." class="form-control" style="width: 160px; padding: 4px 8px;" />
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Service Plan</th>
                        <th>Contact</th>
                        <th>Status on <?php echo date('M d', strtotime($selectedDate)); ?></th>
                        <th>Check-In Time</th>
                        <th>Check-Out Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No active members found for this date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $idx => $m): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <strong style="color: var(--text-main);"><?php echo e($m['fullname']); ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">Total Attendances: <?php echo $m['attendance_count']; ?></div>
                                </td>
                                <td><?php echo e($m['services']); ?></td>
                                <td><?php echo e($m['contact']); ?></td>
                                <td>
                                    <?php if ($m['present']): ?>
                                        <span class="status-badge badge-success"><i class="fas fa-check"></i> Present</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-secondary">Not Checked In</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($m['check_in_time'] ?: '—'); ?></td>
                                <td><?php echo e($m['check_out_time'] ?: '—'); ?></td>
                                <td>
                                    <?php if (!$m['present']): ?>
                                        <form method="POST" action="attendance.php?date=<?php echo urlencode($selectedDate); ?>" style="display:inline;"><?php echo Auth::csrfField(); ?><input type="hidden" name="member_id" value="<?php echo (int)$m['user_id']; ?>" /><button type="submit" name="action" value="checkin" class="btn btn-success btn-sm">
                                            <i class="fas fa-sign-in-alt"></i> Check In
                                        </button></form>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 6px;">
                                            <?php if (empty($m['check_out_time'])): ?>
                                                <form method="POST" action="attendance.php?date=<?php echo urlencode($selectedDate); ?>" style="display:inline;"><?php echo Auth::csrfField(); ?><input type="hidden" name="member_id" value="<?php echo (int)$m['user_id']; ?>" /><button type="submit" name="action" value="checkout" class="btn btn-secondary btn-sm" title="Log Check-Out Time">
                                                    <i class="fas fa-sign-out-alt"></i> Out
                                                </button></form>
                                            <?php endif; ?>
                                            <form method="POST" action="attendance.php?date=<?php echo urlencode($selectedDate); ?>" style="display:inline;"><?php echo Auth::csrfField(); ?><input type="hidden" name="member_id" value="<?php echo (int)$m['user_id']; ?>" /><button type="submit" name="action" value="remove" class="btn btn-secondary btn-sm" title="Remove Today's Attendance" onclick="return confirm(<?php echo e(json_encode('Remove attendance record for ' . $m['fullname'] . '?')); ?>)">
                                                <i class="fas fa-times" style="color: var(--danger);"></i>
                                            </button></form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>