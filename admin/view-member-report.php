<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$page = 'members';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();
$memberId = (int)($_GET['id'] ?? 0);

$member = DB::fetchOne("SELECT m.*, s.fullname as trainer_name 
                        FROM members m 
                        LEFT JOIN staffs s ON m.trainer_id = s.user_id 
                        WHERE m.user_id = ? AND m.tenant_id = ?", [$memberId, $tenantId]);
// Trainers may only open members assigned to them (or unassigned), matching trainer/index.php
if ($member && ($_SESSION['role'] ?? '') === 'trainer'
    && !empty($member['trainer_id']) && (int)$member['trainer_id'] !== (int)$_SESSION['user_id']) {
    $member = null;
}
if (!$member) {
    redirect((($_SESSION['role'] ?? '') === 'trainer') ? base_url('/trainer/index') : base_url('/admin/members'), 'error', 'Member record not found.');
}

$pageTitle = 'Member Profile: ' . $member['fullname'];
$pageSubtitle = 'Membership ID Card, Attendance Log, and Progress Tracking';

// Recent Attendance Logs
$attendanceLogs = DB::fetchAll("SELECT * FROM attendance WHERE user_id = ? AND tenant_id = ? ORDER BY curr_date DESC LIMIT 10", [$memberId, $tenantId]);

// Invoices / Payment History
$invoices = DB::fetchAll("SELECT * FROM invoices WHERE member_id = ? AND tenant_id = ? ORDER BY payment_date DESC", [$memberId, $tenantId]);

// Weight Difference calculation
$currW = (float)($member['curr_weight'] ?? $member['current_weight'] ?? 70);
$iniW = (float)($member['ini_weight'] ?? $member['initial_weight'] ?? 70);
$weightDiff = round($currW - $iniW, 2);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px;" class="no-print">
    <button onclick="window.print()" class="btn btn-secondary btn-sm">
        <i class="fas fa-print"></i> Print Member Card / Report
    </button>
    <a href="edit-memberform.php?id=<?php echo $memberId; ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-edit"></i> Edit Profile
    </a>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Printable ID Card -->
    <div>
        <div class="card" style="background: linear-gradient(135deg, var(--bg-surface) 0%, var(--bg-app) 100%); border: 2px solid var(--border-color); text-align: center; padding: 24px; position: relative;">
            <div style="position: absolute; top: 16px; right: 16px;">
                <?php
                    $planEnd = !empty($member['paid_date']) ? date('Y-m-d', strtotime('+' . max(1, (int)$member['plan']) . ' months', strtotime($member['paid_date']))) : null;
                    $effStatus = ($member['status'] === 'Active' && ($planEnd === null || $planEnd < date('Y-m-d'))) ? 'Expired' : $member['status'];
                    echo status_badge($effStatus);
                ?>
            </div>
            
            <div style="width: 76px; height: 76px; border-radius: var(--radius-full); background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 10px auto 16px;">
                <?php echo strtoupper(substr($member['fullname'], 0, 1)); ?>
            </div>

            <h3 style="font-family: var(--font-display); font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
                <?php echo e($member['fullname']); ?>
            </h3>
            <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 16px;">
                Member ID: <strong>#MEM-<?php echo str_pad($member['user_id'], 4, '0', STR_PAD_LEFT); ?></strong>
            </div>

            <div style="text-align: left; background: var(--bg-surface); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-size: 0.88rem; line-height: 1.8;">
                <div><span style="color: var(--text-muted);">Service:</span> <strong><?php echo e($member['services']); ?> (<?php echo $member['plan']; ?> Mo)</strong></div>
                <div><span style="color: var(--text-muted);">Joined Date:</span> <strong><?php echo format_date($member['dor']); ?></strong></div>
                <div><span style="color: var(--text-muted);">Phone:</span> <strong><?php echo e($member['contact']); ?></strong></div>
                <div><span style="color: var(--text-muted);">Email:</span> <strong><?php echo e($member['email'] ?: 'Not Provided'); ?></strong></div>
                <div><span style="color: var(--text-muted);">Trainer:</span> <strong><?php echo e($member['trainer_name'] ?: 'General'); ?></strong></div>
            </div>

            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 8px;">
                <?php if (!empty($tenant['logo'])): ?>
                    <img src="<?php echo e(str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo'])); ?>" style="width: 18px; height: 18px; object-fit: contain;" />
                <?php endif; ?>
                <span><?php echo e($tenant['gym_name']); ?> • Member Identity Card</span>
            </div>
        </div>

        <!-- Body Transformation Stats -->
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="font-size: 0.95rem;">
                    <i class="fas fa-weight"></i>
                    <span>Body Metrics Progress</span>
                </div>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; text-align: center;">
                    <div style="background: var(--bg-app); padding: 12px; border-radius: var(--radius-md);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Initial Weight</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-top: 4px;"><?php echo number_format($iniW, 2); ?> kg</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($member['ini_bodytype'] ?? $member['ini_body_type'] ?? 'N/A'); ?></div>
                    </div>
                    <div style="background: var(--bg-app); padding: 12px; border-radius: var(--radius-md);">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Current Weight</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary); margin-top: 4px;"><?php echo number_format($currW, 2); ?> kg</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($member['curr_bodytype'] ?? $member['curr_body_type'] ?? $member['body_type'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <div style="margin-top: 14px; text-align: center; font-size: 0.9rem; font-weight: 600;">
                    Net Change: 
                    <span style="color: <?php echo $weightDiff >= 0 ? 'var(--secondary)' : 'var(--danger)'; ?>;">
                        <?php echo ($weightDiff > 0 ? '+' : '') . $weightDiff; ?> kg
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance & Invoices Tabbed/Sectioned Layout -->
    <div>
        <!-- Recent Attendance Log -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-calendar-check"></i>
                    <span>Recent Attendance Check-Ins (Total: <?php echo $member['attendance_count']; ?> days)</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check-In Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                        No attendance records logged yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $a): ?>
                                    <tr>
                                        <td><strong><?php echo format_date($a['curr_date']); ?></strong></td>
                                        <td><?php echo e($a['curr_time']); ?></td>
                                        <td><span class="status-badge badge-success"><i class="fas fa-check"></i> Present</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment & Invoice History -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-receipt"></i>
                    <span>Invoices & Payment Receipts</span>
                </div>
                <a href="user-payment.php?id=<?php echo $memberId; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-plus"></i> Record New Payment
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Service Plan</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                        No payment invoices generated yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td><strong><?php echo e($inv['invoice_number']); ?></strong></td>
                                        <td><?php echo e($inv['service_name']); ?> (<?php echo $inv['plan_months']; ?> Mo)</td>
                                        <td><strong><?php echo format_currency($inv['total_amount'] ?? $inv['amount_paid'] ?? $inv['amount'] ?? 0); ?></strong></td>
                                        <td><?php echo format_date($inv['payment_date']); ?></td>
                                        <td><?php echo status_badge($inv['status']); ?></td>
                                        <td>
                                            <a href="userpay.php?id=<?php echo $inv['id']; ?>" class="btn btn-secondary btn-sm" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
