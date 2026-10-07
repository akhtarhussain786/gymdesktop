<?php
/**
 * Cashfree Checkout — Member Payment
 * Creates a Cashfree order and renders the embedded payment UI.
 */
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/cashfree.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth(['gym_admin', 'staff']);

$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

// Validate incoming POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cashfree_pay'])) {
    redirect('payment.php', 'error', 'Invalid payment request.');
}

Auth::verifyCsrf();

$memberId = (int)($_POST['member_id'] ?? 0);

// Get member details (current tenant only)
$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    redirect('payment.php', 'error', 'Member not found.');
}

if (!Tenant::canWrite()) {
    redirect("user-payment.php?id=$memberId", 'error', 'Your gym subscription has expired. Renew to collect member payments.');
}

// Cashfree orders here are INR-only — never charge a non-INR price as rupees
if (!SubscriptionEngine::tenantUsesInr($tenant)) {
    redirect("user-payment.php?id=$memberId", 'error', 'Online Cashfree payments are only available for gyms billing in INR.');
}

// Price, plan and duration are computed server-side from the gym's rate card (form values are not trusted)
$rateRow = DB::fetchOne("SELECT * FROM rates WHERE tenant_id = ? AND name = ? LIMIT 1", [$tenantId, trim($_POST['services'] ?? '')]);
$planMonths = (int)($_POST['plan'] ?? 1);
if (!$rateRow || (float)$rateRow['charge'] <= 0) {
    redirect("user-payment.php?id=$memberId", 'error', 'Please select a valid service package with a configured rate.');
}
if (!in_array($planMonths, [1, 3, 6, 12], true)) {
    redirect("user-payment.php?id=$memberId", 'error', 'Invalid renewal duration.');
}
$services    = $rateRow['name'];
$rateAmount  = (float)$rateRow['charge'];
$totalAmount = round($rateAmount * $planMonths, 2);

// Generate unique order ID
$orderId = CashfreeGateway::generateOrderId('MEM');

// Persist the order (amount + months) server-side so callback / webhook never depend on the session
$paymentRecordId = DB::insert('membership_payments', [
    'tenant_id' => $tenantId,
    'member_id' => $memberId,
    'plan_id' => (int)$rateRow['id'],
    'plan_months' => $planMonths,
    'cashfree_order_id' => $orderId,
    'amount' => $totalAmount,
    'currency' => 'INR',
    'payment_method' => 'Cashfree',
    'payment_status' => 'PENDING',
    'gateway_response' => json_encode(['months' => $planMonths, 'source' => 'admin_checkout', 'created_by' => $_SESSION['user_id'] ?? null]),
    'created_at' => date('Y-m-d H:i:s')
]);
if (!$paymentRecordId) {
    redirect("user-payment.php?id=$memberId", 'error', 'Could not create payment record.');
}

// Store payment details in session for display only
$_SESSION['cashfree_pending'] = [
    'order_id'    => $orderId,
    'member_id'   => $memberId,
    'services'    => $services,
    'amount'      => $totalAmount,
    'rate_amount' => $rateAmount,
    'plan_months' => $planMonths,
    'tenant_id'   => $tenantId
];

$currencyCode = 'INR';

$returnUrl = base_url('/admin/cashfree-callback.php');

// Create Cashfree order
$result = CashfreeGateway::createOrder(
    $orderId,
    $totalAmount,
    $currencyCode,
    $member['fullname'],
    $member['contact'] ?? '9999999999',
    $member['email'] ?? '',
    $returnUrl
);

if (!$result['success']) {
    SubscriptionEngine::markPaymentNotPaid($orderId, 'FAILED');
    redirect("user-payment.php?id=$memberId", 'error', 'Failed to create payment order: ' . ($result['error'] ?? 'Unknown error'));
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
    <title>Complete Payment — Cashfree | <?php echo e($tenant['gym_name']); ?></title>
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
            background: linear-gradient(135deg, var(--primary, #3b82f6), var(--secondary, #10b981));
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
        .loading-state i { font-size: 2rem; color: var(--primary, #3b82f6); margin-bottom: 12px; }
        .error-banner {
            background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
            padding: 14px 20px; border-radius: 10px; margin: 20px 28px 0;
            font-size: 0.88rem; display: none;
        }
        .back-link {
            display: inline-flex; align-items: center; gap: 6px; color: #64748b;
            text-decoration: none; font-size: 0.85rem; margin-bottom: 16px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary, #3b82f6); }
        .sandbox-badge {
            background: #fef3c7; color: #92400e; font-size: 0.72rem;
            padding: 4px 10px; border-radius: 20px; font-weight: 600;
            display: inline-block; margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <a href="user-payment.php?id=<?php echo $memberId; ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Payment Form
    </a>

    <div class="checkout-card">
        <div class="checkout-header">
            <h2><i class="fas fa-shield-halved"></i> Secure Payment</h2>
            <div class="amount"><?php echo format_currency($totalAmount); ?></div>
            <div class="meta">
                <?php echo e($member['fullname']); ?> • <?php echo e($services); ?> (<?php echo $planMonths; ?> Mo)
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
                <span class="label">Member</span>
                <span class="value"><?php echo e($member['fullname']); ?></span>
            </div>
            <div class="checkout-detail">
                <span class="label">Service</span>
                <span class="value"><?php echo e($services); ?></span>
            </div>
            <div class="checkout-detail">
                <span class="label">Duration</span>
                <span class="value"><?php echo $planMonths; ?> Month(s)</span>
            </div>
            <div class="checkout-detail">
                <span class="label">Rate / Month</span>
                <span class="value"><?php echo format_currency($rateAmount); ?></span>
            </div>
            <div class="checkout-detail">
                <span class="label"><strong>Total</strong></span>
                <span class="value" style="color: var(--primary, #3b82f6); font-size: 1.1rem;">
                    <strong><?php echo format_currency($totalAmount); ?></strong>
                </span>
            </div>

            <div id="cashfree-drop-in">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <div>Loading payment options...</div>
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

    // Render the Cashfree payment form
    cashfree.checkout(checkoutOptions).then(function(result) {
        if (result.error) {
            document.getElementById('error-banner').style.display = 'block';
            document.getElementById('error-text').textContent = result.error.message || 'Payment initialization failed.';
        }
        if (result.redirect) {
            // Payment redirect will happen automatically
        }
    }).catch(function(err) {
        document.getElementById('error-banner').style.display = 'block';
        document.getElementById('error-text').textContent = 'Could not load payment form. Please try again.';
        console.error('Cashfree error:', err);
    });
</script>

</body>
</html>
