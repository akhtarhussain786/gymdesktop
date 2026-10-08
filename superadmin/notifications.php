<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/notifications.php';

Auth::requireAuth('super_admin');

$page = 'super_notifications';
$pageTitle = 'Broadcast Notifications Hub';
$pageSubtitle = 'Send real-time push notifications & announcements to Gym Owners & Admins';

NotificationEngine::ensureSchema();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $action = $_POST['action'] ?? 'send_notification';

    if ($action === 'send_notification') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'system_update';
        $targetAudience = $_POST['target_audience'] ?? 'all';
        $selectedGyms = $_POST['gym_ids'] ?? [];

        if (empty($title) || empty($message)) {
            set_flash('error', 'Notification title and message are required.');
        } else {
            $targetTenantIds = ($targetAudience === 'specific' && !empty($selectedGyms)) ? $selectedGyms : [];
            $res = NotificationEngine::sendToGymOwners($title, $message, $targetTenantIds, $type);

            if ($res['success']) {
                $count = $res['delivered_count'];
                $tokens = $res['token_count'];
                Auth::auditLog('SUPER_BROADCAST_NOTIFICATION', "Dispatched notification: '$title' to $count gym owners ($tokens devices)");
                set_flash('success', "Notification dispatched successfully! Delivered to {$count} gym owners ({$tokens} active push devices).");
            } else {
                set_flash('error', 'Failed to dispatch notification: ' . ($res['error'] ?? 'Unknown error'));
            }
        }
        redirect('/superadmin/notifications');
    }

    // Update FCM Server Key Settings
    if ($action === 'save_fcm_key') {
        $fcmKey = trim($_POST['fcm_server_key'] ?? '');
        try {
            DB::query("CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) AUTO_INCREMENT PRIMARY KEY, `setting_key` varchar(100) UNIQUE, `setting_value` text)");
            $exists = DB::fetchValue("SELECT COUNT(*) FROM settings WHERE setting_key = 'fcm_server_key'");
            if ($exists) {
                DB::update('settings', ['setting_value' => $fcmKey], "setting_key = 'fcm_server_key'");
            } else {
                DB::insert('settings', ['setting_key' => 'fcm_server_key', 'setting_value' => $fcmKey]);
            }
            set_flash('success', 'Firebase Cloud Messaging (FCM) Server Key saved successfully!');
        } catch (Throwable $e) {
            set_flash('error', 'Failed to save FCM key: ' . $e->getMessage());
        }
        redirect('/superadmin/notifications');
    }
}

// Fetch Gym Tenants
$tenants = DB::fetchAll("SELECT id, gym_name, gym_code, status FROM tenants ORDER BY gym_name ASC");

// Fetch Device Token Stats
$totalGymOwners = (int)DB::fetchValue("SELECT COUNT(*) FROM users WHERE role IN ('gym_admin', 'staff') AND status = 'active'");
$totalActiveDevices = (int)DB::fetchValue("SELECT COUNT(DISTINCT device_token) FROM device_tokens WHERE status = 'active' AND user_id IN (SELECT id FROM users WHERE role IN ('gym_admin', 'staff'))");

// Fetch Sent Notification History
$history = DB::fetchAll(
    "SELECT n.*, u.fullname as sender_name 
     FROM notifications n 
     LEFT JOIN users u ON n.sender_user_id = u.id 
     WHERE n.sender_role = 'super_admin' 
     GROUP BY n.title, n.created_at 
     ORDER BY n.id DESC 
     LIMIT 50"
);

// Get current FCM key
$currentFcmKey = '';
try {
    $currentFcmKey = DB::fetchValue("SELECT setting_value FROM settings WHERE setting_key = 'fcm_server_key'") ?: '';
} catch (Throwable $e) {}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Metrics Banner -->
<div class="row" style="margin-bottom: 24px;">
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid var(--lime);">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Target Gym Owners</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($totalGymOwners); ?></div>
            <div style="font-size: 0.8rem; color: #10b981; margin-top: 4px;"><i class="fas fa-user-shield"></i> Active Gym Admins</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid #38bdf8;">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">FCM Active Devices</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($totalActiveDevices); ?></div>
            <div style="font-size: 0.8rem; color: #38bdf8; margin-top: 4px;"><i class="fas fa-mobile-alt"></i> Mobile Push Enabled</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid #a855f7;">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Notification Engine</div>
            <div style="font-size: 1.2rem; font-weight: 800; color: #fff; margin-top: 10px;">
                <?php if (!empty($currentFcmKey)): ?>
                    <span class="status-badge badge-success"><i class="fas fa-check-circle"></i> FCM Ready</span>
                <?php else: ?>
                    <span class="status-badge badge-warning"><i class="fas fa-bell"></i> In-App Active</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 6px;">2-Tier Push Delivery</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left: Compose Form -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-paper-plane" style="color: var(--lime);"></i>
                    <span>Broadcast Notification to Gym Owners</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo Auth::csrfField(); ?>
                    <input type="hidden" name="action" value="send_notification">

                    <div class="form-group">
                        <label class="form-label">Notification Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Important: System Upgrade Scheduled for Sunday" required style="font-weight: 700;" />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notification Message *</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Type your announcement, renewal notice, or update message here..." required></textarea>
                        <small style="color: var(--text-muted);">Will be delivered immediately to gym owners' notification centers and mobile push tray.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Notice Type</label>
                            <select name="type" class="form-select">
                                <option value="system_update">🚀 System Update & New Features</option>
                                <option value="subscription_alert">💳 SaaS Subscription & Renewal Alert</option>
                                <option value="announcement">📢 Platform Announcement</option>
                                <option value="offer">🎁 Special SaaS Discount / Offer</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Target Audience</label>
                            <select name="target_audience" id="target-audience" class="form-select" onchange="toggleGymSelector(this.value)">
                                <option value="all">👑 All Gym Owners (Broadcast)</option>
                                <option value="specific">🏢 Specific Selected Gyms</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="gym-selector-box" style="display: none;">
                        <label class="form-label">Select Target Gyms</label>
                        <select name="gym_ids[]" class="form-select" multiple style="height: 120px;">
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo e($t['gym_name']); ?> (<?php echo e($t['gym_code'] ?: 'ID: ' . $t['id']); ?>) - <?php echo ucfirst($t['status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--text-muted);">Hold Ctrl / Cmd to select multiple gyms.</small>
                    </div>

                    <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary btn-lg" style="font-weight: 800; padding: 12px 28px;">
                            <i class="fas fa-paper-plane"></i> Send Push Notification Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: FCM Server Key & Settings -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-cog" style="color: #38bdf8;"></i>
                    <span>Firebase FCM Configuration</span>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
                    Enter your <strong>Firebase Cloud Messaging (FCM) Server Key</strong> to enable instant background push popups on Android & iOS devices even when the app is closed.
                </p>

                <form method="POST" action="">
                    <?php echo Auth::csrfField(); ?>
                    <input type="hidden" name="action" value="save_fcm_key">

                    <div class="form-group">
                        <label class="form-label">FCM Server Key / Legacy Authorization Key</label>
                        <input type="password" name="fcm_server_key" class="form-control" value="<?php echo htmlspecialchars($currentFcmKey); ?>" placeholder="AAAA...:APA91b..." />
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-save"></i> Save FCM Key
                        </button>
                    </div>
                </form>

                <div style="background: rgba(204, 255, 0, 0.05); border: 1px solid rgba(204, 255, 0, 0.2); border-radius: 8px; padding: 12px; margin-top: 18px; font-size: 0.8rem; color: var(--text-main);">
                    <strong><i class="fas fa-lightbulb" style="color: var(--lime);"></i> How Push Delivery Works:</strong>
                    <ul style="margin: 6px 0 0 16px; padding: 0;">
                        <li>If FCM Key is provided, notifications trigger background device popups.</li>
                        <li>In-App Notification Feed (Bell icon) is always active in real-time.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sent History Table -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>Recent Dispatched Broadcasts</span>
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
                        <th>Sender</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                                No notifications dispatched yet. Use the form above to send your first broadcast.
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
                                        'system_update' => '<span class="status-badge badge-info"><i class="fas fa-cog"></i> System</span>',
                                        'subscription_alert' => '<span class="status-badge badge-danger"><i class="fas fa-credit-card"></i> Renewal</span>',
                                        'offer' => '<span class="status-badge badge-success"><i class="fas fa-tag"></i> Offer</span>',
                                        default => '<span class="status-badge badge-secondary"><i class="fas fa-bullhorn"></i> Notice</span>'
                                    };
                                    echo $typeBadge;
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge badge-secondary">
                                        <?php echo $h['target_type'] === 'all_gym_owners' ? '👑 All Gyms' : '🏢 Selected'; ?>
                                    </span>
                                </td>
                                <td><?php echo e($h['sender_name'] ?: 'Super Admin'); ?></td>
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
function toggleGymSelector(val) {
    const box = document.getElementById('gym-selector-box');
    if (box) {
        box.style.display = (val === 'specific') ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
