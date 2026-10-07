<?php
/**
 * Cashfree Onboarding Checkout — SaaS Subscription Purchase for New Gym Registrations
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/helpers.php';

$orderId = $_GET['order_id'] ?? ($_SESSION['onboarding_payment']['order_id'] ?? '');

if (empty($orderId)) {
    redirect('register-gym.php', 'error', 'No active subscription payment session found.');
}

// Onboarding orders have no tenant yet (tenant_id = 0): take customer details from pending_onboardings
$paymentRecord = DB::fetchOne("SELECT p.*, o.gym_name, o.owner_name, o.email, o.phone, o.status AS onboarding_status, sp.name as plan_name, sp.max_members, sp.max_staff, sp.max_branches 
                               FROM saas_payments p 
                               JOIN pending_onboardings o ON o.reg_ref = p.reg_ref
                               JOIN subscription_plans sp ON p.plan_id = sp.id 
                               WHERE p.transaction_ref = ?", [$orderId]);

if (!$paymentRecord) {
    redirect('register-gym.php', 'error', 'Subscription order record not found.');
}
if ($paymentRecord['status'] === 'approved' || $paymentRecord['onboarding_status'] === 'completed') {
    redirect('payment-status.php?order_id=' . urlencode($orderId) . '&reg_ref=' . urlencode($paymentRecord['reg_ref']));
}

$sessPay = $_SESSION['onboarding_payment'] ?? [];
$paymentSessionId = (($sessPay['order_id'] ?? '') === $orderId) ? ($sessPay['payment_session_id'] ?? null) : null;

// If payment_session_id is not in session, generate / retrieve from Cashfree
if (empty($paymentSessionId)) {
    $returnUrl = base_url('/saas-onboarding-callback.php');

    $cfResult = CashfreeGateway::createOrder(
        $orderId,
        $paymentRecord['total_payable'],
        'INR',
        $paymentRecord['owner_name'] ?: 'Gym Owner',
        $paymentRecord['phone'] ?: '9876543210',
        $paymentRecord['email'] ?: 'owner@gym.com',
        $returnUrl
    );

    // If order creation failed on old order_id (e.g. invalid cached Cashfree order), generate fresh Order ID and retry
    if (!$cfResult['success']) {
        $newOrderId = CashfreeGateway::generateOrderId('SAAS_ONBOARD');
        // Keep pending_onboardings.order_id and saas_payments.transaction_ref in sync
        DB::query("UPDATE pending_onboardings SET order_id = ? WHERE order_id = ? AND status <> 'completed'", [$newOrderId, $orderId]);
        DB::query("UPDATE saas_payments SET transaction_ref = ?, cf_order_id = NULL, status = 'pending' WHERE id = ? AND status IN ('pending','failed')", [$newOrderId, $paymentRecord['id']]);
        $orderId = $newOrderId;

        $cfResult = CashfreeGateway::createOrder(
            $newOrderId,
            $paymentRecord['total_payable'],
            'INR',
            $paymentRecord['owner_name'] ?: 'Gym Owner',
            $paymentRecord['phone'] ?: '9876543210',
            $paymentRecord['email'] ?: 'owner@gym.com',
            $returnUrl
        );
    }

    if ($cfResult['success']) {
        $paymentSessionId = $cfResult['payment_session_id'];
        $_SESSION['onboarding_payment']['reg_ref'] = $paymentRecord['reg_ref'];
        $_SESSION['onboarding_payment']['order_id'] = $orderId;
        $_SESSION['onboarding_payment']['payment_session_id'] = $paymentSessionId;
    } else {
        $error = "Failed to initialize Cashfree gateway: " . ($cfResult['error'] ?? 'API error');
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
    <title>Complete Subscription Payment | Fitisify SaaS</title>
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
            max-width: 580px;
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
            padding: 32px 28px;
            color: white;
            text-align: center;
            position: relative;
        }

        .checkout-header .brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        .checkout-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            color: #fff;
        }

        .checkout-header .plan-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .checkout-amount-box {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin: 24px 28px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .checkout-body {
            padding: 24px 28px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: var(--text-muted);
        }

        .summary-value {
            color: var(--text-main);
            font-weight: 600;
        }

        #cashfree-drop-in {
            margin-top: 20px;
            min-height: 280px;
        }

        .checkout-footer {
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 18px 28px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .error-alert {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid #ef4444;
            color: #f87171;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin: 20px 28px 0;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>

<div class="checkout-wrapper">
    <div class="checkout-card">
        <div class="checkout-header">
            <div class="brand-icon"><i class="fas fa-dumbbell"></i></div>
            <h2>Complete Gym Subscription</h2>
            <div class="plan-pill">
                <i class="fas fa-gem"></i> <?php echo e($paymentRecord['plan_name']); ?> Tier
            </div>
            <?php if ($cashfreeMode === 'sandbox'): ?>
                <div style="margin-top: 8px;">
                    <span style="background:#fef3c7;color:#92400e;font-size:0.75rem;padding:3px 10px;border-radius:20px;font-weight:700;">
                        <i class="fas fa-flask"></i> Cashfree Test Sandbox
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <div class="checkout-amount-box">
            <div>
                <div style="font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                    Total Payable Amount
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); font-family: 'Outfit', sans-serif;">
                    ₹<?php echo number_format($paymentRecord['total_payable'], 2); ?>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="status-badge badge-info"><?php echo ucfirst($paymentRecord['billing_cycle']); ?> Plan</span>
            </div>
        </div>

        <div class="checkout-body">
            <div class="summary-row">
                <span class="summary-label">Gym Name</span>
                <span class="summary-value"><?php echo e($paymentRecord['gym_name']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Owner Name</span>
                <span class="summary-value"><?php echo e($paymentRecord['owner_name']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Member Capacity</span>
                <span class="summary-value"><?php echo number_format($paymentRecord['max_members']); ?> Members</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Staff Logins</span>
                <span class="summary-value"><?php echo number_format($paymentRecord['max_staff']); ?> Accounts</span>
            </div>

            <!-- Cashfree Drop-in Container -->
            <div id="cashfree-drop-in">
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 12px;"></i>
                    <div>Connecting to Cashfree Secure Gateway...</div>
                </div>
            </div>
        </div>

        <div class="checkout-footer">
            <i class="fas fa-lock" style="color: #10b981;"></i> Secured by <strong>Cashfree Payments</strong> • 256-Bit SSL Encryption<br>
            <span style="font-size: 0.75rem; opacity: 0.8;">Supports UPI (GPay, PhonePe, Paytm), Credit/Debit Cards & Net Banking</span>
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
                alert("Payment initiation failed: " + (result.error.message || "Please try again."));
            }
        }).catch(function(err) {
            console.error("Cashfree Init Catch:", err);
        });
    <?php endif; ?>
});
</script>

</body>
</html>
