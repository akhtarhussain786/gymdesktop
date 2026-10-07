<?php
/**
 * Cashfree Callback for SaaS Subscription Renewal
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/subscription_engine.php';

$orderId = trim($_GET['order_id'] ?? '');
if (empty($orderId)) {
    die('Invalid callback — Order ID missing.');
}

$payment = DB::fetchOne("SELECT p.*, t.gym_name, t.currency, sp.name as plan_name 
                         FROM saas_payments p 
                         JOIN tenants t ON p.tenant_id = t.id 
                         JOIN subscription_plans sp ON p.plan_id = sp.id 
                         WHERE p.transaction_ref = ?", [$orderId]);

if (!$payment) {
    die('Payment record not found.');
}

$tenantId = (int)$payment['tenant_id'];

// Finalize with Cashfree
$result = SubscriptionEngine::finalizeSaasGatewayOrder($orderId, $tenantId);

$isSuccess = !empty($result['success']);
$newExpiry = $result['end_date'] ?? null;
$currency = !empty($payment['currency']) ? $payment['currency'] : '₹';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Payment Status | <?php echo htmlspecialchars($payment['gym_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-app: #0f1015;
            --bg-surface: #171821;
            --bg-card: #202230;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --success: #10b981;
            --danger: #ef4444;
            --lime: #ccff00;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: radial-gradient(circle at 50% 0%, rgba(16, 185, 129, 0.15) 0%, transparent 60%), var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
        }

        .status-card {
            max-width: 480px;
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }

        .icon-circle.success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 2px solid var(--success);
        }

        .icon-circle.failed {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 2px solid var(--danger);
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        p.subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .info-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            text-align: left;
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.84rem;
        }

        .info-row .label { color: var(--text-muted); }
        .info-row .val { font-weight: 700; color: #fff; }

        .btn-done {
            display: block;
            width: 100%;
            padding: 14px;
            background: var(--lime);
            color: #000;
            font-weight: 800;
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: opacity 0.2s;
        }

        .btn-done:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="status-card">
    <?php if ($isSuccess): ?>
        <div class="icon-circle success">
            <i class="fas fa-check"></i>
        </div>
        <h1>Subscription Renewed!</h1>
        <p class="subtitle">Your SaaS subscription has been verified with Cashfree and successfully extended.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="label">Gym</span>
                <span class="val"><?php echo htmlspecialchars($payment['gym_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Plan</span>
                <span class="val"><?php echo htmlspecialchars($payment['plan_name']); ?> (<?php echo ucfirst(htmlspecialchars($payment['billing_cycle'])); ?>)</span>
            </div>
            <div class="info-row">
                <span class="label">Amount Paid</span>
                <span class="val" style="color: var(--lime);"><?php echo $currency . number_format((float)$payment['total_payable'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="label">New Expiry Date</span>
                <span class="val" style="color: #60a5fa;"><?php echo $newExpiry ? date('d M Y (D)', strtotime($newExpiry)) : 'Extended'; ?></span>
            </div>
        </div>

        <a href="javascript:window.close();" class="btn-done">Return to App</a>
    <?php else: ?>
        <div class="icon-circle failed">
            <i class="fas fa-times"></i>
        </div>
        <h1>Payment Incomplete</h1>
        <p class="subtitle"><?php echo htmlspecialchars($result['error'] ?? 'Transaction was not completed on Cashfree.'); ?></p>
        <a href="<?php echo base_url('/saas-renew-checkout.php?order_id=' . urlencode($orderId)); ?>" class="btn-done" style="background: #6366f1; color: white;">Try Again</a>
    <?php endif; ?>
</div>

</body>
</html>
