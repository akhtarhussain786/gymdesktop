<?php
/**
 * Automated Cron Job: SaaS Subscription Expiry Email Reminders
 * 
 * Scans all gyms with SaaS subscriptions expiring in <= 5 days (5, 4, 3, 2, 1, 0 days)
 * and dispatches professional email reminders to the gym owner with one-click UPI renewal links.
 * 
 * Usage:
 * - CLI: php cron/saas_subscription_reminder.php
 * - HTTP / cPanel Cron: https://gymsaas.in/cron/saas_subscription_reminder.php?key=YOUR_CRON_SECRET
 */

set_time_limit(300);
ignore_user_abort(true);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/env.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../core/subscription_engine.php';

// CLI or Auth Key Protection for Web
$isCli = (php_sapi_name() === 'cli');
$cronSecret = env('CRON_SECRET', 'fitisify_cron_secret_2026');
$reqKey = $_GET['key'] ?? '';

if (!$isCli && !empty($cronSecret) && $reqKey !== $cronSecret) {
    // Also allow superadmin session if logged in via web
    session_start();
    $isSuperAdmin = !empty($_SESSION['super_admin_id']) || !empty($_SESSION['is_super_admin']);
    if (!$isSuperAdmin) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Unauthorized cron invocation']));
    }
}

$tenantId = !empty($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

// Execute automated reminders
$result = SubscriptionEngine::checkAndSendSaasExpiryReminders($tenantId);

$response = [
    'success'         => true,
    'timestamp'       => date('Y-m-d H:i:s'),
    'total_checked'   => $result['total_checked'] ?? 0,
    'reminders_sent'  => $result['reminders_sent'] ?? 0,
    'skipped'         => $result['skipped'] ?? 0,
    'errors'          => $result['errors'] ?? [],
    'message'         => "Processed {$result['total_checked']} gyms: {$result['reminders_sent']} email reminders sent, {$result['skipped']} skipped (already sent today)."
];

if ($isCli) {
    echo "=== Fitisify SaaS Expiry Reminder Cron ===\n";
    echo "Time: " . $response['timestamp'] . "\n";
    echo "Total Checked: " . $response['total_checked'] . "\n";
    echo "Reminders Sent: " . $response['reminders_sent'] . "\n";
    echo "Skipped (Already Sent Today): " . $response['skipped'] . "\n";
    if (!empty($response['errors'])) {
        echo "Errors:\n";
        foreach ($response['errors'] as $err) {
            echo " - " . $err . "\n";
        }
    }
    echo "Done.\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
