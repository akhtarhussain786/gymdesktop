<?php
/**
 * Cashfree Callback — SaaS Subscription Verification
 * The saas_payments row created at checkout is the source of truth; the order is re-verified
 * with Cashfree (status + amount + currency) and applied idempotently.
 */
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth('gym_admin');

$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

$orderId = trim($_GET['order_id'] ?? '');

if (empty($orderId)) {
    redirect('subscription.php', 'error', 'Invalid subscription callback — no order ID received.');
}

// Verify with Cashfree and apply (idempotent; also handled by the webhook if the browser never returns)
$result = SubscriptionEngine::finalizeSaasGatewayOrder($orderId, $tenantId);

unset($_SESSION['cashfree_saas_pending']);

if ($result['success']) {
    $newExpiry = $result['end_date'] ?? null;
    if (empty($result['already_processed'])) {
        $planName = DB::fetchValue("SELECT sp.name FROM saas_payments p LEFT JOIN subscription_plans sp ON sp.id = p.plan_id WHERE p.transaction_ref = ?", [$orderId]);
        Auth::auditLog('RENEW_SUBSCRIPTION_ONLINE', "Gym renewed SaaS subscription to " . ($planName ?: 'plan') . " until $newExpiry via Cashfree (" . ($result['transaction_id'] ?? $orderId) . ")");
    }
    redirect('subscription.php', 'success', "Subscription renewed successfully via Cashfree! New expiry date: " . format_date($newExpiry));
}

$status = $result['status'] ?? 'UNKNOWN';
$errorMsg = $result['error'] ?? 'Payment was not completed.';

redirect('subscription.php', 'error', "Subscription payment not completed. Status: $status. $errorMsg");
