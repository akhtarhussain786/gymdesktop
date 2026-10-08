<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/notifications.php';

Auth::requireAuth(['gym_admin', 'staff']);

$page = 'notifications';
$pageTitle = 'Broadcast Notifications & Push Alerts';
$pageSubtitle = 'Send push notifications & alerts directly to your gym members\' phones';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

NotificationEngine::ensureSchema();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $action = $_POST['action'] ?? 'send_notification';

    if ($action === 'send_notification') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'announcement';
        $target = $_POST['target'] ?? 'all_members';
        $specificMemberId = !empty($_POST['member_id']) ? [(int)$_POST['member_id']] : [];

        if (empty($title) || empty($message)) {
            set_flash('error', 'Notification title and message are required.');
        } else {
            $res = NotificationEngine::sendToMembers($tenantId, $title, $message, $target, $specificMemberId, $type);
            if ($res['success']) {
                $count = $res['delivered_count'];
                $tokens = $res['token_count'];
                Auth::auditLog('ADMIN_SEND_NOTIFICATION', "Sent notification '$title' to $count members ($tokens devices)");
                set_flash('success', "Notification sent successfully to {$count} members! ({$tokens} mobile devices received push popup).");
            } else {
                set_flash('error', 'Failed to send notification: ' . ($res['error'] ?? 'Unknown error'));
            }
        }
        redirect('/admin/notifications');
    }

    // Quick 1-Click Bulk Due Reminder Action
    if ($action === 'quick_due_reminder') {
        $dueTitle = "Membership Fee Due Reminder - " . ($tenant['gym_name'] ?? 'Gym');
        $dueMsg = "Dear athlete, your membership fee is due. Kindly renew your plan at the front desk or via the member app to continue uninterrupted gym access.";
        
        $res = NotificationEngine::sendToMembers($tenantId, $dueTitle, $dueMsg, 'due_members', [], 'fee_reminder');
        if ($res['success']) {
            $count = $res['delivered_count'];
            $tokens = $res['token_count'];
            Auth::auditLog('ADMIN_BULK_DUE_REMINDER', "Dispatched 1-click due reminder to $count pending members");
            set_flash('success', "Instant Fee Reminder sent to {$count} pending/expiring members ({$tokens} devices).");
        } else {
            set_flash('error', 'Failed to send fee reminders: ' . ($res['error'] ?? 'Unknown error'));
        }
        redirect('/admin/notifications');
    }
}

// Fetch Members for Dropdown
$members = DB::fetchAll("SELECT user_id, fullname, contact, status FROM members WHERE tenant_id = ? ORDER BY fullname ASC", [$tenantId]);

// Fetch Counts
$totalMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'Active'", [$tenantId]);
$totalDueMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND (due_amount > 0 OR DATEDIFF(DATE_ADD(COALESCE(paid_date, dor), INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH), CURDATE()) <= 3)", [$tenantId]);
$totalActiveDevices = (int)DB::fetchValue("SELECT COUNT(DISTINCT device_token) FROM device_tokens WHERE tenant_id = ? AND status = 'active'", [$tenantId]);

// Fetch Notification History
$history = DB::fetchAll(
    "SELECT n.*, u.fullname as sender_name 
     FROM notifications n 
     LEFT JOIN users u ON n.sender_user_id = u.id 
     WHERE n.tenant_id = ? AND n.sender_role IN ('gym_admin', 'staff')
     GROUP BY n.title, n.created_at 
     ORDER BY n.id DESC 
     LIMIT 50",
    [$tenantId]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Metrics Summary -->
<div class="row" style="margin-bottom: 24px;">
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid var(--lime);">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Active Gym Members</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($totalMembers); ?></div>
            <div style="font-size: 0.8rem; color: #10b981; margin-top: 4px;"><i class="fas fa-users"></i> Available to Receive Alerts</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid #ef4444;">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Due / Expiring Members</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($totalDueMembers); ?></div>
            <div style="font-size: 0.8rem; color: #ef4444; margin-top: 4px;"><i class="fas fa-exclamation-circle"></i> Pending Fee Collection</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid #38bdf8;">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Mobile App Devices</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($totalActiveDevices); ?></div>
            <div style="font-size: 0.8rem; color: #38bdf8; margin-top: 4px;"><i class="fas fa-mobile-alt"></i> Push Notifications Enabled</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Compose Notification Card -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-paper-plane" style="color: var(--lime);"></i>
                    <span>Send Notification to Gym Members</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo Auth::csrfField(); ?>
                    <input type="hidden" name="action" value="send_notification">

                    <div class="form-group">
                        <label class="form-label">Notification Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Gym Timings Change for Sunday" required style="font-weight: 700;" />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message Content *</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Type your holiday alert, fee reminder, diet tip, or announcement here..." required></textarea>
                        <small style="color: var(--text-muted);">Will appear instantly on your members' mobile app notification tray.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Notification Type</label>
                            <select name="type" class="form-select">
                                <option value="announcement">📢 General Announcement</option>
                                <option value="holiday_timing">⏰ Holiday / Timing Update</option>
                                <option value="fee_reminder">💳 Membership Fee Due Reminder</option>
                                <option value="offer">🎁 Special Gym Discount / Personal Training Offer</option>
                                <option value="general">🏋️ Fitness Challenge / Workout Tip</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Target Audience</label>
                            <select name="target" id="target-select" class="form-select" onchange="toggleMemberSelector(this.value)">
                                <option value="all_members">👥 All Active Members (Broadcast)</option>
                                <option value="due_members">⚠️ Only Fee Due / Expiring Members (<?php echo $totalDueMembers; ?>)</option>
                                <option value="specific_member">👤 Single Specific Member</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="member-select-box" style="display: none;">
                        <label class="form-label">Select Member</label>
                        <select name="member_id" class="form-select">
                            <option value="">-- Choose Member --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['user_id']; ?>">
                                    <?php echo e($m['fullname']); ?> (<?php echo e($m['contact'] ?: 'ID: #' . $m['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary btn-lg" style="font-weight: 800; padding: 12px 28px;">
                            <i class="fas fa-paper-plane"></i> Send Push Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick 1-Click Due Reminder Box -->
    <div class="col-md-4">
        <div class="card" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(204, 255, 0, 0.03)); border: 1.5px solid rgba(239, 68, 68, 0.3);">
            <div class="card-header">
                <div class="card-title" style="color: #f87171;">
                    <i class="fas fa-bolt"></i>
                    <span>1-Click Fee Due Recovery</span>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size: 0.85rem; color: var(--text-main); line-height: 1.6;">
                    Have members with unpaid dues or memberships expiring within 3 days? Send a polite automated renewal push notification to all <strong><?php echo $totalDueMembers; ?> due members</strong> with one click.
                </p>

                <form method="POST" action="" onsubmit="return confirm('Send automated fee due push reminder to all <?php echo $totalDueMembers; ?> pending members?');">
                    <?php echo Auth::csrfField(); ?>
                    <input type="hidden" name="action" value="quick_due_reminder">

                    <button type="submit" class="btn btn-primary" style="width: 100%; background: #ef4444; border-color: #ef4444; font-weight: 800; padding: 12px;">
                        <i class="fas fa-bell"></i> Send 1-Click Due Alert (<?php echo $totalDueMembers; ?>)
                    </button>
                </form>

                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 14px; text-align: center;">
                    <i class="fas fa-shield-alt"></i> Members receive popup notification directly on their phones.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- History Table -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>Sent Notification History</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title & Message</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Sent By</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                                No notifications sent yet. Use the form above to send your first member notification.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($h['title']); ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                                        <?php echo e(mb_strimwidth($h['message'], 0, 100, '...')); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $typeBadge = match($h['type']) {
                                        'fee_reminder' => '<span class="status-badge badge-danger"><i class="fas fa-credit-card"></i> Fee Due</span>',
                                        'holiday_timing' => '<span class="status-badge badge-warning"><i class="fas fa-clock"></i> Timing</span>',
                                        'offer' => '<span class="status-badge badge-success"><i class="fas fa-tag"></i> Offer</span>',
                                        default => '<span class="status-badge badge-info"><i class="fas fa-bullhorn"></i> Notice</span>'
                                    };
                                    echo $typeBadge;
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge badge-secondary">
                                        <?php 
                                        echo match($h['target_type']) {
                                            'due_members' => '⚠️ Fee Due Only',
                                            'specific_member' => '👤 Specific Member',
                                            default => '👥 All Members'
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo e($h['sender_name'] ?: 'Gym Admin'); ?></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo date('M d, Y • h:i A', strtotime($h['created_at'])); ?>
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
function toggleMemberSelector(val) {
    const box = document.getElementById('member-select-box');
    if (box) {
        box.style.display = (val === 'specific_member') ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
