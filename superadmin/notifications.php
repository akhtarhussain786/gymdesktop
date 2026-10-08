<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/notifications.php';

Auth::requireAuth('super_admin');

$page = 'super_notifications';
$pageTitle = 'Broadcast Notifications Hub';
$pageSubtitle = 'Send real-time push notifications & announcements to Gym Owners & Admins';

NotificationEngine::ensureSchema();

$testResult = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $action = $_POST['action'] ?? 'send_notification';

    // 1. Broadcast Notification Action
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
            $res = NotificationEngine::sendToGymOwners($title, $message, $targetTenantIds, $type, $targetAudience);

            if ($res['success']) {
                $accepted = $res['accepted_count'] ?? 0;
                $rejected = $res['rejected_count'] ?? 0;
                $totalTokens = $res['token_count'] ?? 0;

                if ($res['status'] === 'no_devices_registered' || $totalTokens === 0) {
                    set_flash('warning', "ℹ️ Notification recorded in In-App feed, but <strong>0 active mobile push devices</strong> were registered for the selected audience.");
                } elseif ($accepted > 0 && $rejected === 0) {
                    Auth::auditLog('SUPER_BROADCAST_NOTIFICATION', "Dispatched notification: '$title' to $accepted devices");
                    set_flash('success', "🚀 Notification dispatched successfully via <strong>Firebase FCM HTTP v1</strong>! Accepted by Firebase for <strong>{$accepted}</strong> active device(s).");
                } elseif ($accepted > 0 && $rejected > 0) {
                    set_flash('warning', "⚠️ Partial delivery: Accepted by Firebase for <strong>{$accepted}</strong> device(s), but rejected for <strong>{$rejected}</strong> device(s). Stale tokens have been auto-pruned.");
                } else {
                    set_flash('error', "❌ Firebase FCM v1 rejected push delivery for all {$rejected} targeted device(s). Please check device token validity.");
                }
            } else {
                set_flash('error', 'Failed to dispatch notification: ' . ($res['error'] ?? ($res['message'] ?? 'Unknown error')));
            }
        }
        redirect('/superadmin/notifications');
    }

    // 2. Instant Single-Device Test Push Action
    if ($action === 'test_push') {
        $testToken = trim($_POST['test_device_token'] ?? '');
        $testTitle = trim($_POST['test_title'] ?? '🔔 Live Test Push Alert');
        $testBody = trim($_POST['test_body'] ?? 'Verifying Firebase FCM HTTP v1 delivery with high-importance popup and sound.');

        if (empty($testToken)) {
            set_flash('error', 'Please enter or select a valid FCM Device Token.');
        } else {
            $testResult = NotificationEngine::sendTestPush($testToken, $testTitle, $testBody);
            if ($testResult['success']) {
                set_flash('success', "✅ Test push notification <strong>accepted by Firebase FCM HTTP v1</strong> in {$testResult['duration_ms']}ms! Check the target phone screen.");
            } else {
                $errDetails = $testResult['fcm_response']['details'][0]['error_message'] ?? ($testResult['error'] ?? 'Delivery failed');
                set_flash('error', "❌ Test push delivery failed: " . htmlspecialchars($errDetails));
            }
        }
    }
}

// Fetch Gym Tenants
$tenants = DB::fetchAll("SELECT id, gym_name, gym_code, status FROM tenants ORDER BY gym_name ASC");

// Fetch FCM & Device Diagnostics
$fcmHealth = NotificationEngine::getFcmHealth();
$totalGymOwners = (int)DB::fetchValue("SELECT COUNT(*) FROM users WHERE role IN ('gym_admin', 'staff') AND status = 'active'");

// Fetch Recent Registered Devices for Test Selector
$recentDevices = DB::fetchAll("SELECT d.*, t.gym_name, u.fullname as user_name FROM device_tokens d LEFT JOIN tenants t ON d.tenant_id = t.id LEFT JOIN users u ON d.user_id = u.id WHERE d.status = 'active' ORDER BY d.updated_at DESC LIMIT 15");

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
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Active Push Devices</div>
            <div style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo number_format($fcmHealth['active_devices_total']); ?></div>
            <div style="font-size: 0.8rem; color: #38bdf8; margin-top: 4px;">
                <i class="fas fa-mobile-alt"></i> <?php echo $fcmHealth['admin_devices']; ?> Admins &bull; <?php echo $fcmHealth['member_devices']; ?> Members
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="padding: 20px; border-left: 4px solid <?php echo $fcmHealth['oauth_authenticated'] ? '#10b981' : '#ef4444'; ?>;">
            <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Firebase FCM Engine</div>
            <div style="font-size: 1.2rem; font-weight: 800; color: #fff; margin-top: 10px;">
                <?php if ($fcmHealth['oauth_authenticated']): ?>
                    <span class="status-badge badge-success"><i class="fas fa-check-circle"></i> FCM HTTP v1 Active</span>
                <?php else: ?>
                    <span class="status-badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Key Missing</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 6px;">
                Project: <code><?php echo e($fcmHealth['project_id']); ?></code>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left: Compose Broadcast Form -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-paper-plane" style="color: var(--lime);"></i>
                    <span>Broadcast Notification to Gym Owners & Members</span>
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
                        <small style="color: var(--text-muted);">Dispatches real-time high priority popup banner with sound & vibration to mobile devices.</small>
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
                                <option value="everyone" selected>🌍 Everyone (All Active Mobile Devices & Gyms)</option>
                                <option value="all_gym_owners">👑 All Gym Owners & Staff (Admin App)</option>
                                <option value="all_members">🏋️ All Gym Members Across All Gyms</option>
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

    <!-- Right: FCM HTTP v1 Configuration & Instant Test Push Tool -->
    <div class="col-md-5">
        <!-- FCM HTTP v1 Status Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-server" style="color: #38bdf8;"></i>
                    <span>Firebase FCM HTTP v1 Status</span>
                </div>
            </div>
            <div class="card-body">
                <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #fff; font-size: 0.95rem;">
                        <i class="fas fa-shield-alt" style="color: #38bdf8;"></i>
                        <span>Google OAuth2 RS256 Engine</span>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.8rem; color: var(--text-muted); line-height: 1.6;">
                        <div><strong>Project ID:</strong> <code><?php echo e($fcmHealth['project_id']); ?></code></div>
                        <div><strong>Service Account:</strong> <span style="word-break: break-all;"><?php echo e($fcmHealth['client_email']); ?></span></div>
                        <div><strong>Protocol:</strong> <code>HTTP/v1 (OAuth 2.0 Bearer)</code></div>
                        <div><strong>Heads-Up Channel:</strong> <code>gym_high_importance_channel</code></div>
                    </div>
                </div>

                <!-- Instant Test Push Tool -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: var(--lime);">
                        <i class="fas fa-bolt"></i> Instant Test Push to Device
                    </h4>
                    <form method="POST" action="">
                        <?php echo Auth::csrfField(); ?>
                        <input type="hidden" name="action" value="test_push">

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Select Active Device / Token</label>
                            <select class="form-select form-select-sm" onchange="fillTestToken(this.value)">
                                <option value="">-- Choose from recently active devices --</option>
                                <?php foreach ($recentDevices as $rd): ?>
                                    <option value="<?php echo htmlspecialchars($rd['device_token']); ?>">
                                        <?php echo e($rd['user_name'] ?: ($rd['gym_name'] ?: 'Guest Device')); ?> 
                                        (<?php echo ucfirst($rd['platform']); ?> - <?php echo e(substr($rd['device_token'], 0, 10)); ?>...)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">FCM Device Token *</label>
                            <input type="text" id="test-device-token" name="test_device_token" class="form-control form-control-sm" placeholder="Paste FCM Token here..." required />
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Test Title</label>
                            <input type="text" name="test_title" class="form-control form-control-sm" value="🔔 WhatsApp-Style Test Alert" />
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Test Message</label>
                            <input type="text" name="test_body" class="form-control form-control-sm" value="Firebase FCM v1 push test with high-importance sound & banner." />
                        </div>

                        <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; font-weight: 700;">
                            <i class="fas fa-paper-plane"></i> Send Instant Test Push
                        </button>
                    </form>
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
                                        <?php 
                                        echo match($h['target_type']) {
                                            'all_gym_owners' => '👑 All Gyms',
                                            'all_members' => '🏋️ All Members',
                                            'everyone' => '🌍 Everyone',
                                            default => '🏢 Selected'
                                        };
                                        ?>
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

function fillTestToken(token) {
    if (token) {
        document.getElementById('test-device-token').value = token;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
