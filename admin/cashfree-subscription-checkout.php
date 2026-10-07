<?php
/**
 * Cashfree Checkout — SaaS Subscription Renewal / Plan Upgrade
 */
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth('gym_admin');

$tenant = Tenant::getCurrent();
$tenantId = Tenant::getTenantId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cashfree_sub_pay'])) {
    redirect('subscription.php', 'error', 'Invalid subscription payment request.');
}

Auth::verifyCsrf();

$planId = (int)($_POST['plan_id'] ?? 0);
$cycle = $_POST['billing_cycle'] ?? 'monthly';
$couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));

// Server-side quote: rejects plans without a price for this cycle, validates coupon
// (active / not expired / usage limit) and applies the platform tax rate.
$quote = SubscriptionEngine::quoteSaasRenewal($planId, $cycle, $couponCode);
if (!$quote['success']) {
    redirect('subscription.php', 'error', $quote['error']);
}
$selectedPlan = $quote['plan'];
$basePrice    = $quote['base_price'];
$discount     = $quote['discount'];
$tax          = $quote['tax'];
$totalPayable = $quote['total'];
$couponCode   = $quote['coupon_code'];

if ($totalPayable < 1) {
    redirect('subscription.php', 'error', 'The payable amount is below the online payment minimum. Please contact support to apply this renewal.');
}

// Generate unique order ID
$orderId = CashfreeGateway::generateOrderId('SAAS');

// Store in session for display only — the DB row below is the source of truth for callback / webhook
$_SESSION['cashfree_saas_pending'] = [
    'order_id'       => $orderId,
    'tenant_id'      => $tenantId,
    'plan_id'        => $planId,
    'billing_cycle'  => $cycle,
    'base_price'     => $basePrice,
    'tax'            => $tax,
    'discount'       => $discount,
    'total_payable'  => $totalPayable,
    'coupon_code'    => $couponCode
];

$today = date('Y-m-d');
$saasPaymentId = DB::insert('saas_payments', [
    'tenant_id'       => $tenantId,
    'plan_id'         => $planId,
    'billing_cycle'   => $cycle,
    'amount'          => $basePrice,
    'tax_amount'      => $tax,
    'discount_amount' => $discount,
    'total_payable'   => $totalPayable,
    'coupon_code'     => $couponCode,
    'payment_method'  => 'cashfree',
    'transaction_ref' => $orderId,
    'status'          => 'pending',
    'start_date'      => $today,
    'end_date'        => $today,
    'notes'           => 'Cashfree online renewal — awaiting gateway confirmation'
]);
if (!$saasPaymentId) {
    redirect('subscription.php', 'error', 'Could not create subscription order. Please try again.');
}

// Return URL
$returnUrl = base_url('/admin/cashfree-subscription-callback.php');

$result = CashfreeGateway::createOrder(
    $orderId,
    $totalPayable,
    'INR',
    $tenant['owner_name'] ?: 'Gym Admin',
    $tenant['phone'] ?: '9999999999',
    $tenant['email'] ?: 'admin@fitisify.com',
    $returnUrl
);

if (!$result['success']) {
    DB::query("UPDATE saas_payments SET status = 'failed', failure_reason = ? WHERE id = ? AND status = 'pending'", [substr((string)($result['error'] ?? 'order creation failed'), 0, 250), $saasPaymentId]);
    redirect('subscription.php', 'error', 'Failed to initialize online payment: ' . ($result['error'] ?? 'Unknown error'));
}

$paymentSessionId = $result['payment_session_id'];
$cashfreeMode = CashfreeGateway::getMode();
$jsSdkUrl = CashfreeGateway::getJsSdkUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Subscription Payment — Cashfree | <?php echo e($tenant['gym_name']); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { 
            background: var(--bg-app, #f1f5f9); 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            margin: 0; padding: 0;
            display: flex; align-items: center; justify-content: center; 
            min-height: 100vh;
        }
        .checkout-container {
            max-width: 520px; width: 100%; margin: 20px;
        }
        .checkout-card {
            background: var(--bg-surface, #fff); border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08); overflow: hidden;
        }
        .checkout-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 28px; color: white; text-align: center;
        }
        .checkout-header h2 { margin: 0; font-size: 1.3rem; font-weight: 700; }
        .checkout-header .amount {
            font-size: 2.4rem; font-weight: 800; margin-top: 10px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .checkout-header .meta { font-size: 0.85rem; opacity: 0.85; margin-top: 6px; }
        .checkout-body { padding: 28px; }
        .checkout-detail {
            display: flex; justify-content: space-between; padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06); font-size: 0.9rem;
        }
        .checkout-detail:last-child { border-bottom: none; }
        .checkout-detail .label { color: #64748b; }
        .checkout-detail .value { font-weight: 600; color: #0f172a; }
        #cashfree-drop-in { margin-top: 20px; min-height: 300px; }
        .checkout-footer { 
            padding: 20px 28px; background: #f8fafc; 
            text-align: center; font-size: 0.78rem; color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .loading-state {
            text-align: center; padding: 40px; color: #64748b;
        }
        .loading-state i { font-size: 2rem; color: #4f46e5; margin-bottom: 12px; }
        .error-banner {
            background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
            padding: 14px 20px; border-radius: 10px; margin: 20px 28px 0;
            font-size: 0.88rem; display: none;
        }
        .back-link {
            display: inline-flex; align-items: center; gap: 6px; color: #64748b;
            text-decoration: none; font-size: 0.85rem; margin-bottom: 16px;
        }
        .back-link:hover { color: #4f46e5; }
        .sandbox-badge {
            background: #fef3c7; color: #92400e; font-size: 0.72rem;
            padding: 4px 10px; border-radius: 20px; font-weight: 600;
            display: inline-block; margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <a href="subscription.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Subscriptions
    </a>

    <div class="checkout-card">
        <div class="checkout-header">
            <h2><i class="fas fa-rocket"></i> SaaS Subscription Renewal</h2>
            <div class="amount">₹<?php echo number_format($totalPayable, 2); ?></div>
            <div class="meta">
                Plan: <?php echo e($selectedPlan['name']); ?> (<?php echo e(ucfirst($cycle)); ?>)
            </div>
            <?php if ($cashfreeMode === 'sandbox'): ?>
                <div class="sandbox-badge"><i class="fas fa-flask"></i> TEST MODE</div>
            <?php endif; ?>
        </div>

        <div id="error-banner" class="error-banner">
            <i class="fas fa-exclamation-circle"></i> <span id="error-text"></span>
        </div>

        <div class="checkout-body">
            <div class="checkout-detail">
                <span class="label">Gym Name</span>
                <span class="value"><?php echo e($tenant['gym_name']); ?></span>
            </div>
            <div class="checkout-detail">
                <span class="label">Selected Tier</span>
                <span class="value"><?php echo e($selectedPlan['name']); ?></span>
            </div>
            <div class="checkout-detail">
                <span class="label">Billing Cycle</span>
                <span class="value"><?php echo e(ucfirst($cycle)); ?></span>
            </div>
            <?php if ($tax > 0): ?>
            <div class="checkout-detail">
                <span class="label">Tax</span>
                <span class="value">₹<?php echo number_format($tax, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($discount > 0): ?>
            <div class="checkout-detail">
                <span class="label">Coupon Discount</span>
                <span class="value" style="color: #10b981;">-₹<?php echo number_format($discount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="checkout-detail">
                <span class="label"><strong>Total Payable</strong></span>
                <span class="value" style="color: #4f46e5; font-size: 1.1rem;">
                    <strong>₹<?php echo number_format($totalPayable, 2); ?></strong>
                </span>
            </div>

            <div id="cashfree-drop-in">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div>Loading Cashfree Checkout...</div>
                </div>
            </div>
        </div>

        <div class="checkout-footer">
            <i class="fas fa-lock"></i> Secured by <strong>Cashfree Payments</strong> • 256-bit SSL Encryption
            <br>Order ID: <code><?php echo e($orderId); ?></code>
        </div>
    </div>
</div>

<script src="<?php echo $jsSdkUrl; ?>"></script>
<script>
    const cashfree = Cashfree({ mode: "<?php echo $cashfreeMode; ?>" });

    let checkoutOptions = {
        paymentSessionId: "<?php echo $paymentSessionId; ?>",
        redirectTarget: "_self"
    };

    cashfree.checkout(checkoutOptions).then(function(result) {
        if (result.error) {
            document.getElementById('error-banner').style.display = 'block';
            document.getElementById('error-text').textContent = result.error.message || 'Payment initialization failed.';
        }
    }).catch(function(err) {
        document.getElementById('error-banner').style.display = 'block';
        document.getElementById('error-text').textContent = 'Could not load payment form. Please try again.';
        console.error('Cashfree error:', err);
    });
</script>

</body>
</html>
