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

// CLI always allowed. Web calls need ?key= matching CRON_SECRET from the environment; there is no
// built-in default, so web invocation is refused until CRON_SECRET is configured.
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    $cronSecret = (string)env('CRON_SECRET', '');
    $reqKey = isset($_GET['key']) && is_string($_GET['key']) ? $_GET['key'] : '';
    if ($cronSecret === '' || !hash_equals($cronSecret, $reqKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'Unauthorized cron invocation']));
    }
}

// 1. Mark unpaid Cashfree checkouts older than 2 days as abandoned. Nothing is deleted: manual renewal
// requests stay pending until a super admin reviews them, and a late-paid gateway order can still be applied.
$cutoffTime = date('Y-m-d H:i:s', strtotime('-2 days'));
$purgedCount = (int)DB::update('saas_payments', [
    'status' => 'failed',
    'failure_reason' => 'abandoned'
], "status = 'pending' AND payment_method = 'cashfree' AND created_at < ?", [$cutoffTime]);

// 2. Execute automated reminders
$result = SubscriptionEngine::checkAndSendSaasExpiryReminders(null);

$response = [
    'success'         => true,
    'timestamp'       => date('Y-m-d H:i:s'),
    'purged_orders'   => $purgedCount,
    'total_checked'   => $result['total_checked'] ?? 0,
    'reminders_sent'  => $result['reminders_sent'] ?? 0,
    'skipped'         => $result['skipped'] ?? 0,
    'errors'          => $result['errors'] ?? [],
    'message'         => "Processed {$result['total_checked']} gyms: {$result['reminders_sent']} email reminders sent, {$result['skipped']} skipped. Marked {$purgedCount} abandoned checkouts."
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
    // Web callers get counts only: error details contain gym owner emails / SMTP responses
    foreach ($response['errors'] as $err) {
        error_log('SaaS reminder cron: ' . $err);
    }
    $response['error_count'] = count($response['errors']);
    $response['errors'] = [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
