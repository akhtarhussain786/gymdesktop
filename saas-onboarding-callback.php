<?php
/**
 * Cashfree Onboarding Callback — Verify Payment & Activate Gym Tenant
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';

$orderId = $_GET['order_id'] ?? ($_SESSION['onboarding_payment']['order_id'] ?? '');

if (empty($orderId)) {
    redirect('register-gym.php', 'error', 'No payment order reference found.');
}

require_once __DIR__ . '/core/account_provisioner.php';

$pending = DB::fetchOne("SELECT p.*, sp.name as plan_name FROM pending_onboardings p JOIN subscription_plans sp ON p.plan_id = sp.id WHERE p.order_id = ? OR p.reg_ref = ?", [$orderId, $orderId]);

$paymentRecord = DB::fetchOne("SELECT p.*, t.id as tenant_id, t.gym_name, t.owner_name, t.email, t.phone, sp.name as plan_name 
                               FROM saas_payments p 
                               LEFT JOIN tenants t ON p.tenant_id = t.id 
                               LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                               WHERE p.transaction_ref = ? OR p.reg_ref = ?", [$orderId, $orderId]);

if (!$pending && !$paymentRecord) {
    redirect('register-gym.php', 'error', 'Payment transaction record not found.');
}

// Already provisioned (e.g. by the webhook) — go straight to the status page
if ($pending && $pending['status'] === 'completed') {
    redirect("payment-status.php?order_id=" . urlencode($orderId) . "&reg_ref=" . urlencode($pending['reg_ref']));
}

// Verify order with Cashfree REST API (status + amount + currency) and provision idempotently
$provision = $pending ? AccountProvisioner::provisionVerifiedOrder($pending, $orderId) : ['success' => false, 'error' => 'Registration not found.'];

if ($provision['success'] || ($provision['status'] ?? '') === 'MISMATCH') {
    // payment-status.php polls api/check-payment-status.php and shows the definitive result
    $targetRef = $pending['reg_ref'] ?? $orderId;
    redirect("payment-status.php?order_id=" . urlencode($orderId) . "&reg_ref=" . urlencode($targetRef));
}

// Display values for the failure screen (onboarding rows have no tenant yet)
$paymentRecord = $paymentRecord ?: [];
$paymentRecord['gym_name'] = $paymentRecord['gym_name'] ?? ($pending['gym_name'] ?? '');
$paymentRecord['plan_name'] = $paymentRecord['plan_name'] ?? ($pending['plan_name'] ?? '');
$paymentRecord['total_payable'] = $paymentRecord['total_payable'] ?? ($pending['amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Fitisify SaaS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .status-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            max-width: 520px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
        }

        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="status-card">
    <div class="status-icon"><i class="fas fa-times"></i></div>
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">
        Payment Incomplete or Cancelled
    </h2>
    <p style="color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; margin-bottom: 24px;">
        We could not verify your payment with Cashfree. The transaction was either cancelled or did not complete.
    </p>

    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 28px; text-align: left; font-size: 0.88rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span style="color: var(--text-muted);">Gym Name:</span>
            <strong style="color: var(--text-main);"><?php echo e($paymentRecord['gym_name']); ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <span style="color: var(--text-muted);">Plan:</span>
            <strong style="color: var(--text-main);"><?php echo e($paymentRecord['plan_name']); ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted);">Payable Amount:</span>
            <strong style="color: var(--primary);">₹<?php echo number_format($paymentRecord['total_payable'], 2); ?></strong>
        </div>
    </div>

    <div style="display: flex; gap: 12px; justify-content: center;">
        <a href="saas-onboarding-checkout.php?order_id=<?php echo urlencode($orderId); ?>" class="btn btn-primary btn-lg" style="flex: 1;">
            <i class="fas fa-redo"></i> Retry Payment
        </a>
        <a href="index.php" class="btn btn-secondary btn-lg">
            <i class="fas fa-home"></i> Home
        </a>
    </div>
</div>

</body>
</html>
