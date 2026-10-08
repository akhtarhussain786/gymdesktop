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

// Handle Mobile App & APK Release Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mobile_app_settings'])) {
    Auth::verifyCsrf();
    
    $playstoreUrl = trim($_POST['app_playstore_url'] ?? '');
    $externalApkUrl = trim($_POST['app_apk_external_url'] ?? '');
    $appVersion = trim($_POST['app_version'] ?? 'v1.0.4');
    $minAndroid = trim($_POST['app_min_android'] ?? 'Android 8.0+');
    $releaseNotes = trim($_POST['app_release_notes'] ?? '');
    
    set_platform_setting('app_playstore_url', $playstoreUrl);
    set_platform_setting('app_apk_external_url', $externalApkUrl);
    set_platform_setting('app_version', $appVersion);
    set_platform_setting('app_min_android', $minAndroid);
    set_platform_setting('app_release_notes', $releaseNotes);
    
    $apkUploadMsg = '';
    if (isset($_FILES['apk_file']) && $_FILES['apk_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['apk_file']['tmp_name'];
        $fileName = $_FILES['apk_file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExt === 'apk') {
            $destDir = __DIR__ . '/../uploads/apk';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $destPath = $destDir . '/fitisify_member_app.apk';
            if (move_uploaded_file($fileTmp, $destPath)) {
                set_platform_setting('app_apk_local_file', 'uploads/apk/fitisify_member_app.apk');
                set_platform_setting('app_apk_last_updated', date('Y-m-d H:i:s'));
                $apkUploadMsg = ' New APK file uploaded successfully.';
            } else {
                $apkUploadMsg = ' Failed to save uploaded APK file to server directory.';
            }
        } else {
            $apkUploadMsg = ' Invalid file format. Only .apk files are permitted.';
        }
    } elseif (isset($_FILES['apk_file']) && $_FILES['apk_file']['error'] === UPLOAD_ERR_INI_SIZE) {
        $apkUploadMsg = ' Note: Uploaded APK exceeded PHP server limit. You can specify an external download URL or increase php.ini upload_max_filesize.';
    }
    
    Auth::auditLog('SUPER_APP_SETTINGS_UPDATE', "Super Admin updated mobile app release settings & APK.$apkUploadMsg");
    redirect('settings.php', 'success', 'Mobile app release settings updated successfully.' . $apkUploadMsg);
}

// Check database orphaned rows
$orphanCount = DemoSeeder::countOrphans();

// Fetch recent email delivery logs
$recentLogs = DB::fetchAll("SELECT * FROM email_delivery_logs ORDER BY id DESC LIMIT 10");

// Load Mobile App Release Info
$appPlayStoreUrl = get_platform_setting('app_playstore_url', 'https://play.google.com/store/apps/details?id=com.fitisify.gym_member_app');
$appApkExternalUrl = get_platform_setting('app_apk_external_url', '');
$appVersion = get_platform_setting('app_version', 'v1.0.4');
$appMinAndroid = get_platform_setting('app_min_android', 'Android 8.0+');
$appReleaseNotes = get_platform_setting('app_release_notes', 'Official Fitisify Athlete & Member Companion Mobile App with real-time QR gate check-in, dynamic workout logging, automated diet plans, fee payments, and push notifications.');

$apkLocalPath = __DIR__ . '/../uploads/apk/fitisify_member_app.apk';
$apkExists = file_exists($apkLocalPath);
$apkSizeFormatted = $apkExists ? round(filesize($apkLocalPath) / (1024 * 1024), 2) . ' MB' : 'Not Uploaded';
$apkModifiedFormatted = $apkExists ? date('M d, Y H:i A', filemtime($apkLocalPath)) : '-';
$apkDownloadUrl = !empty($appApkExternalUrl) ? $appApkExternalUrl : base_url('/download-apk.php');

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

<!-- 5. Mobile Application & APK Release Control -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fab fa-android" style="color: #10b981;"></i>
            <span>Mobile Application & APK Release Management</span>
        </div>
        <span class="status-badge <?php echo $apkExists ? 'badge-success' : 'badge-warning'; ?>">
            <i class="fas <?php echo $apkExists ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $apkExists ? 'APK Active (' . $apkSizeFormatted . ')' : 'No APK File Uploaded'; ?>
        </span>
    </div>
    <div class="card-body">
        <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin-top: 0;">
            Manage the official <strong>Fitisify Athlete & Member Mobile App</strong> distribution. Configure the Google Play Store landing link, upload or update the direct Android <code>.apk</code> package, and set version metadata displayed on the main website homepage.
        </p>

        <!-- Current Release Diagnostics Box -->
        <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; font-size: 0.86rem;">
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Current Release Version</span>
                    <strong style="color: var(--lime); font-size: 1.05rem;"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($appVersion); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Local Package Size</span>
                    <strong style="color: var(--text-main); font-size: 1.05rem;"><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($apkSizeFormatted); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Last Modified</span>
                    <strong style="color: var(--text-main); font-size: 0.92rem;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($apkModifiedFormatted); ?></strong>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <?php if ($apkExists): ?>
                        <a href="<?php echo htmlspecialchars($apkDownloadUrl); ?>" download class="btn btn-sm btn-ghost-dark" style="width: 100%; border-color: rgba(199,255,46,0.3); color: var(--lime);">
                            <i class="fas fa-download"></i> Test Direct Download
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label"><i class="fab fa-google-play" style="color: #38bdf8;"></i> Google Play Store URL</label>
                    <input type="url" name="app_playstore_url" class="form-control" placeholder="https://play.google.com/store/apps/details?id=..." value="<?php echo htmlspecialchars($appPlayStoreUrl); ?>" />
                    <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                        The Play Store button on the main homepage will redirect users here.
                    </small>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label"><i class="fas fa-link" style="color: #f59e0b;"></i> External Direct APK URL (Optional CDN/Drive/S3)</label>
                    <input type="url" name="app_apk_external_url" class="form-control" placeholder="https://cdn.yourdomain.com/fitisify_member_app.apk" value="<?php echo htmlspecialchars($appApkExternalUrl); ?>" />
                    <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                        Leave blank to serve directly from the server's <code>uploads/apk/</code> directory.
                    </small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label"><i class="fas fa-tag"></i> App Version Name / Build</label>
                    <input type="text" name="app_version" class="form-control" placeholder="e.g. v1.0.4" value="<?php echo htmlspecialchars($appVersion); ?>" required />
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label"><i class="fab fa-android"></i> Minimum OS Requirement</label>
                    <input type="text" name="app_min_android" class="form-control" placeholder="e.g. Android 8.0+" value="<?php echo htmlspecialchars($appMinAndroid); ?>" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fas fa-file-upload" style="color: #10b981;"></i> Upload New Android APK File (.apk)</label>
                <input type="file" name="apk_file" class="form-control" accept=".apk" style="padding: 9px 14px;" />
                <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                    Selecting an APK file will replace the current active package in <code>uploads/apk/fitisify_member_app.apk</code>.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fas fa-align-left"></i> App Release Highlights / Description</label>
                <textarea name="app_release_notes" class="form-control" rows="3" placeholder="Summary of member app features..."><?php echo htmlspecialchars($appReleaseNotes); ?></textarea>
            </div>

            <div style="margin-top: 24px;">
                <button type="submit" name="save_mobile_app_settings" value="1" class="btn btn-primary" style="font-weight: 700;">
                    <i class="fas fa-save"></i> Save & Publish Mobile App Release
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 6. General Platform Settings -->
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
