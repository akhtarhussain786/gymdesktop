<?php
/**
 * Cashfree Onboarding Hosted & Drop-in Checkout Page
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/helpers.php';

$regRef = $_GET['reg_ref'] ?? ($_SESSION['onboarding_payment']['reg_ref'] ?? '');

if (empty($regRef)) {
    redirect('register-gym.php', 'error', 'No active onboarding registration found.');
}

$pending = DB::fetchOne("SELECT p.*, sp.name as plan_name, sp.max_members, sp.max_staff, sp.max_branches 
                         FROM pending_onboardings p 
                         JOIN subscription_plans sp ON p.plan_id = sp.id 
                         WHERE p.reg_ref = ?", [$regRef]);

if (!$pending) {
    redirect('register-gym.php', 'error', 'Registration record not found or already completed.');
}
if ($pending['status'] === 'completed') {
    redirect('payment-status.php?reg_ref=' . urlencode($regRef));
}

$orderId = $pending['order_id'];
// Only reuse a cached payment session that belongs to THIS registration and order
$sessPay = $_SESSION['onboarding_payment'] ?? [];
$paymentSessionId = (($sessPay['reg_ref'] ?? '') === $regRef && ($sessPay['order_id'] ?? '') === $orderId)
    ? ($sessPay['payment_session_id'] ?? null) : null;

// If paymentSessionId is missing from session, initialize it with Cashfree
if (empty($paymentSessionId) && !empty($orderId)) {
    $returnUrl = base_url('/payment-status.php?reg_ref=' . urlencode($regRef));

    $cfResult = CashfreeGateway::createOrder(
        $orderId,
        $pending['amount'],
        'INR',
        $pending['owner_name'],
        $pending['phone'],
        $pending['email'],
        $returnUrl
    );

    // If order creation failed on old order_id (e.g. invalid cached Cashfree order), generate fresh Order ID and retry
    if (!$cfResult['success']) {
        $newOrderId = CashfreeGateway::generateOrderId('SAAS_ONBOARD');
        // Keep pending_onboardings.order_id and saas_payments.transaction_ref in sync
        DB::query("UPDATE pending_onboardings SET order_id = ? WHERE reg_ref = ? AND status <> 'completed'", [$newOrderId, $regRef]);
        DB::query("UPDATE saas_payments SET transaction_ref = ?, cf_order_id = NULL, status = 'pending' WHERE reg_ref = ? AND tenant_id = 0 AND status IN ('pending','failed')", [$newOrderId, $regRef]);
        $orderId = $newOrderId;
        $pending['order_id'] = $newOrderId;

        $cfResult = CashfreeGateway::createOrder(
            $newOrderId,
            $pending['amount'],
            'INR',
            $pending['owner_name'],
            $pending['phone'],
            $pending['email'],
            $returnUrl
        );
    }

    if ($cfResult['success']) {
        $paymentSessionId = $cfResult['payment_session_id'];
        $_SESSION['onboarding_payment']['reg_ref'] = $regRef;
        $_SESSION['onboarding_payment']['order_id'] = $orderId;
        $_SESSION['onboarding_payment']['payment_session_id'] = $paymentSessionId;
    } else {
        $error = $cfResult['error'];
    }
}

$cashfreeMode = CashfreeGateway::getMode();
$jsSdkUrl = CashfreeGateway::getJsSdkUrl();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Cashfree Payment | <?php echo e($pending['gym_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 60%), var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .checkout-wrapper {
            max-width: 540px;
            width: 100%;
        }

        .checkout-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }

        .checkout-header {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            padding: 30px 24px;
            color: white;
            text-align: center;
        }

        .checkout-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #fff;
        }

        .amount-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin: 20px 24px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .checkout-body {
            padding: 20px 24px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.88rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-main);
        }

        #cashfree-drop-in {
            margin-top: 16px;
            min-height: 280px;
        }

        .checkout-footer {
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="checkout-wrapper">
    <div class="checkout-card">
        <div class="checkout-header">
            <div style="font-size: 1.8rem; margin-bottom: 8px;"><i class="fas fa-dumbbell"></i></div>
            <h2>Complete Gym Subscription</h2>
            <div style="font-size: 0.85rem; opacity: 0.9;">
                Plan: <strong><?php echo e($pending['plan_name']); ?></strong> (<?php echo ucfirst($pending['billing_cycle']); ?>)
            </div>
            <?php if ($cashfreeMode === 'sandbox'): ?>
                <div style="margin-top: 6px;">
                    <span style="background: #fef3c7; color: #92400e; font-size: 0.72rem; padding: 2px 8px; border-radius: 99px; font-weight: 700;">
                        <i class="fas fa-flask"></i> Cashfree Test Sandbox
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="amount-box">
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total Payable</div>
                <div style="font-size: 1.8rem; font-weight: 900; color: var(--primary); font-family: 'Outfit', sans-serif;">
                    ₹<?php echo number_format($pending['amount'], 2); ?>
                </div>
            </div>
            <div>
                <span class="status-badge badge-info"><?php echo e($pending['gym_name']); ?></span>
            </div>
        </div>

        <div class="checkout-body">
            <div class="detail-row">
                <span class="detail-label">Owner Name</span>
                <span class="detail-value"><?php echo e($pending['owner_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email Address</span>
                <span class="detail-value"><?php echo e($pending['email']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo e($pending['phone']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Quotas Included</span>
                <span class="detail-value"><?php echo number_format($pending['max_members']); ?> Members • <?php echo number_format($pending['max_staff']); ?> Staff</span>
            </div>

            <!-- Cashfree Drop-in Container -->
            <div id="cashfree-drop-in">
                <div style="text-align: center; padding: 36px 0; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                    <div>Connecting to Cashfree Secure Payments...</div>
                </div>
            </div>
        </div>

        <div class="checkout-footer">
            <i class="fas fa-lock" style="color: #10b981;"></i> Secured by <strong>Cashfree Payments</strong> • 256-Bit SSL Encryption<br>
            <span>Supports UPI (GPay, PhonePe, Paytm, BHIM), Credit/Debit Cards & Net Banking</span>
        </div>
    </div>
</div>

<script src="<?php echo $jsSdkUrl; ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($paymentSessionId)): ?>
        const cashfree = Cashfree({ mode: "<?php echo $cashfreeMode; ?>" });

        let checkoutOptions = {
            paymentSessionId: "<?php echo $paymentSessionId; ?>",
            redirectTarget: "_self"
        };

        cashfree.checkout(checkoutOptions).then(function(result) {
            if (result.error) {
                console.error("Cashfree Checkout Error:", result.error);
                alert("Payment initiation error: " + (result.error.message || "Please refresh and try again."));
            }
        }).catch(function(err) {
            console.error("Cashfree Init Error:", err);
        });
    <?php endif; ?>
});
</script>

</body>
</html>
