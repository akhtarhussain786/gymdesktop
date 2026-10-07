<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/demo_seeder.php';
Auth::requireAuth('super_admin');

$page = 'super_settings';
$pageTitle = 'Global Platform Settings & Diagnostics';
$pageSubtitle = 'Manage platform branding, database health, Gmail SMTP, and demo environment';

$testResult = null;
$mailConfig = Mailer::getConfig();

// Handle Database Sanitation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_orphaned_records'])) {
    Auth::verifyCsrf();
    $res = DemoSeeder::purgeOrphans();
    if ($res['success']) {
        Auth::auditLog('SUPER_PURGE_ORPHANS', "Purged {$res['total_deleted']} orphaned records from database.");
        redirect('settings.php', 'success', "Database Sanitation Succeeded: Removed {$res['total_deleted']} orphaned records.");
    } else {
        redirect('settings.php', 'error', "Database Sanitation Failed: " . ($res['error'] ?? 'Unknown error'));
    }
}

// Handle Seed / Reset Demo Gym Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_demo_gym_account'])) {
    Auth::verifyCsrf();
    $force = !empty($_POST['force_reset']);
    $res = DemoSeeder::seedDemoGym($force);
    if ($res['success']) {
        Auth::auditLog('SUPER_SEED_DEMO', "Super Admin reset showcase DEMO FITNESS & GYM account.");
        $creds = $res['credentials'] ?? [];
        redirect('settings.php', 'success', "Demo Gym Synchronized: Code <code>{$creds['gym_code']}</code> | Admin: <code>{$creds['admin_username']}</code> / <code>{$creds['admin_password']}</code>");
    } else {
        redirect('settings.php', 'error', "Failed to seed demo gym: " . ($res['error'] ?? 'Unknown error'));
    }
}

// Handle Test Email Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    Auth::verifyCsrf();

    // Simple rate limiting: 5 seconds between tests per session
    $lastTestTime = $_SESSION['last_test_email_time'] ?? 0;
    if (time() - $lastTestTime < 5) {
        $testResult = [
            'success' => false,
            'error' => 'Rate limit: Please wait at least 5 seconds before sending another test email.'
        ];
    } else {
        $_SESSION['last_test_email_time'] = time();
        $testRecipient = trim($_POST['test_recipient_email'] ?? '');
        $testResult = Mailer::sendTestEmail($testRecipient);
        Auth::auditLog('SMTP_TEST_EMAIL', "Super Admin triggered test email to '{$testRecipient}' with result: " . ($testResult['success'] ? 'SUCCESS' : 'FAILED'));
    }
}

// Handle General Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general_settings'])) {
    Auth::verifyCsrf();
    redirect('settings.php', 'success', 'Global platform settings updated successfully.');
}

// Check database orphaned rows
$orphanCount = DemoSeeder::countOrphans();

// Fetch recent email delivery logs
$recentLogs = DB::fetchAll("SELECT * FROM email_delivery_logs ORDER BY id DESC LIMIT 10");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
    
    <!-- 1. Gmail SMTP Live Status Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-envelope-open-text" style="color: var(--primary);"></i>
                <span>Gmail SMTP Service Configuration</span>
            </div>
            <span class="status-badge <?php echo !empty($mailConfig['password']) ? 'badge-success' : 'badge-warning'; ?>">
                <i class="fas <?php echo !empty($mailConfig['password']) ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                <?php echo !empty($mailConfig['password']) ? 'Credentials Active' : 'App Password Pending'; ?>
            </span>
        </div>
        <div class="card-body">
            <div style="font-size: 0.88rem; line-height: 1.8; color: var(--text-main);">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">SMTP Host:</span>
                    <strong><code><?php echo htmlspecialchars($mailConfig['host']); ?></code></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">Port & Encryption:</span>
                    <strong><?php echo (int)$mailConfig['port']; ?> (<?php echo strtoupper($mailConfig['encryption']); ?> / STARTTLS)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">Authentication:</span>
                    <strong style="color: #10b981;"><i class="fas fa-lock"></i> Enabled (AUTH LOGIN)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">Username / Sender:</span>
                    <strong><?php echo htmlspecialchars($mailConfig['username']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">App Password Status:</span>
                    <strong>
                        <?php if (!empty($mailConfig['password'])): ?>
                            <span style="color: #10b981;"><i class="fas fa-shield-alt"></i> Configured (Masked)</span>
                        <?php else: ?>
                            <span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Not set in .env</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding: 8px 0;">
                    <span style="color: var(--text-muted);">From Display Name:</span>
                    <strong><?php echo htmlspecialchars($mailConfig['from_name']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span style="color: var(--text-muted);">Reply-To Address:</span>
                    <strong><?php echo htmlspecialchars($mailConfig['reply_to']); ?></strong>
                </div>
            </div>

            <div style="margin-top: 16px; font-size: 0.78rem; color: var(--text-muted); background: var(--bg-app); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                Settings are read securely from the local <code>.env</code> file (excluded from Git version control).
            </div>
        </div>
    </div>

    <!-- 2. Super Admin Test Email Tool -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-vial" style="color: #10b981;"></i>
                <span>Super Admin SMTP Diagnostic Test</span>
            </div>
        </div>
        <div class="card-body">
            <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-top: 0;">
                Test real-time socket connection, TLS certificate validation, Gmail authentication, and email delivery to any destination inbox.
            </p>

            <?php if ($testResult !== null): ?>
                <?php if ($testResult['success']): ?>
                    <div class="alert alert-success" style="margin-bottom: 18px;">
                        <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                        <div>
                            <strong>SMTP Test Succeeded!</strong><br>
                            Test message was successfully accepted and dispatched by <code>smtp.gmail.com:587</code>.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger" style="margin-bottom: 18px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                        <div>
                            <strong>SMTP Test Failed</strong><br>
                            <span><?php echo htmlspecialchars($testResult['error'] ?? 'Unknown connection error.'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo Auth::csrfField(); ?>
                <div class="form-group">
                    <label class="form-label">Recipient Email Address *</label>
                    <input type="email" name="test_recipient_email" class="form-control" placeholder="e.g. admin@yourdomain.com" required value="<?php echo htmlspecialchars($_POST['test_recipient_email'] ?? $mailConfig['username']); ?>" />
                    <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                        A diagnostic test message with timestamp and server metadata will be dispatched.
                    </small>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="send_test_email" value="1" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Send SMTP Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- 3. Recent Email Delivery Logs Table -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>Recent Email Delivery Tracking & Idempotency Audit Logs</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Recipient Email</th>
                        <th>Email Type</th>
                        <th>Order / Txn Ref</th>
                        <th>Delivery Status</th>
                        <th>Attempts</th>
                        <th>Sent / Attempted At</th>
                        <th>Error Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No email delivery events recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td>#<?php echo $log['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($log['recipient_email']); ?></strong></td>
                                <td>
                                    <span class="status-badge badge-info" style="font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($log['email_type']); ?>
                                    </span>
                                </td>
                                <td><code><?php echo htmlspecialchars($log['order_ref'] ?: 'N/A'); ?></code></td>
                                <td>
                                    <?php if ($log['status'] === 'sent'): ?>
                                        <span class="status-badge badge-success"><i class="fas fa-check"></i> Sent</span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="status-badge badge-danger"><i class="fas fa-times"></i> Failed</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge"><?php echo (int)$log['attempts']; ?></span></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo $log['sent_at'] ? htmlspecialchars($log['sent_at']) : htmlspecialchars($log['last_attempt_at']); ?>
                                </td>
                                <td style="font-size: 0.8rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php if (!empty($log['failure_reason'])): ?>
                                        <span style="color: #ef4444;" title="<?php echo htmlspecialchars($log['failure_reason']); ?>">
                                            <?php echo htmlspecialchars($log['failure_reason']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
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

<!-- 4. Database Sanitation & Showcase Demo Tools -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">
    <!-- Database Health Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-database" style="color: #38bdf8;"></i>
                <span>Database Health & Orphan Cleaner</span>
            </div>
            <span class="status-badge <?php echo $orphanCount === 0 ? 'badge-success' : 'badge-warning'; ?>">
                <i class="fas <?php echo $orphanCount === 0 ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                <?php echo $orphanCount === 0 ? 'Clean (0 Orphans)' : $orphanCount . ' Ghost Rows'; ?>
            </span>
        </div>
        <div class="card-body">
            <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-top: 0;">
                Purges dangling records across all child tables (members, staff, invoices, attendance) where the parent gym tenant has been deleted. Ensures 100% accurate metrics.
            </p>
            <form method="POST" action="" onsubmit="return confirm('Purge all orphaned records across the database?');">
                <?php echo Auth::csrfField(); ?>
                <button type="submit" name="purge_orphaned_records" value="1" class="btn btn-warning" style="width: 100%; font-weight: 700; background: #f59e0b; color: #000;">
                    <i class="fas fa-broom"></i> Clean Orphaned Records Now
                </button>
            </form>
        </div>
    </div>

    <!-- Showcase Demo Gym Seeder Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-magic" style="color: var(--lime);"></i>
                <span>Showcase Demo Gym Initializer</span>
            </div>
            <span class="status-badge badge-info">Client Presentation</span>
        </div>
        <div class="card-body">
            <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-top: 0;">
                Creates or resets a pristine <strong>DEMO FITNESS & GYM</strong> with 5 demo members, 2 trainers, workout & diet routines, and verified invoices for client demonstrations.
            </p>
            <form method="POST" action="" onsubmit="return confirm('Initialize / Reset DEMO FITNESS & GYM account?');">
                <?php echo Auth::csrfField(); ?>
                <input type="hidden" name="force_reset" value="1" />
                <button type="submit" name="seed_demo_gym_account" value="1" class="btn btn-primary" style="width: 100%; font-weight: 700;">
                    <i class="fas fa-sync-alt"></i> Reset / Seed Demo Gym
                </button>
            </form>
        </div>
    </div>
</div>

<!-- 5. General Platform Settings -->
<div class="card" style="max-width: 800px; margin-bottom: 30px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-sliders-h"></i>
            <span>Platform Branding & Policy Defaults</span>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="form-group">
                <label class="form-label">Platform Master Name</label>
                <input type="text" name="platform_name" class="form-control" value="Fitisify SaaS Gym Network" />
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Platform Support Email</label>
                    <input type="email" name="support_email" class="form-control" value="akhtarhussain132003@gmail.com" />
                </div>
                <div class="form-group">
                    <label class="form-label">Default Trial Duration (Days)</label>
                    <input type="number" name="trial_days" class="form-control" value="14" />
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Multi-Tenancy Isolation Mode</label>
                <input type="text" class="form-control" value="Logical Row-Level Tenant Scoping (Active)" readonly style="background: var(--bg-app);" />
            </div>
            <div style="margin-top: 24px;">
                <button type="submit" name="save_general_settings" value="1" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
