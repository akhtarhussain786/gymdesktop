<?php
/**
 * Cashfree Hosted Drop-in Checkout Page for Gym SaaS Subscription Renewal / Upgrade
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/subscription_engine.php';

$orderId = trim($_GET['order_id'] ?? '');
if (empty($orderId)) {
    die('Invalid checkout request — Order ID is missing.');
}

$payment = DB::fetchOne("SELECT p.*, t.gym_name, t.owner_name, t.phone, t.email, t.currency, sp.name as plan_name, sp.max_members, sp.max_staff 
                         FROM saas_payments p 
                         JOIN tenants t ON p.tenant_id = t.id 
                         JOIN subscription_plans sp ON p.plan_id = sp.id 
                         WHERE p.transaction_ref = ?", [$orderId]);

if (!$payment) {
    die('Subscription payment record not found or expired.');
}

if ($payment['status'] === 'approved') {
    redirect(base_url('/saas-renew-callback.php?order_id=' . urlencode($orderId)));
}

$paymentSessionId = $_GET['payment_session_id'] ?? '';
$error = null;

if (empty($paymentSessionId)) {
    $returnUrl = base_url('/saas-renew-callback.php?order_id=' . urlencode($orderId));
    $cfResult = CashfreeGateway::createOrder(
        $orderId,
        $payment['total_payable'],
        'INR',
        $payment['owner_name'] ?: 'Gym Admin',
        $payment['phone'] ?: '9999999999',
        $payment['email'] ?: 'admin@fitisify.com',
        $returnUrl
    );

    if ($cfResult['success']) {
        $paymentSessionId = $cfResult['payment_session_id'];
    } else {
        $error = $cfResult['error'] ?? 'Could not initialize Cashfree gateway.';
    }
}

$cashfreeMode = CashfreeGateway::getMode();
$jsSdkUrl = CashfreeGateway::getJsSdkUrl();
$currency = !empty($payment['currency']) ? $payment['currency'] : '₹';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew SaaS Subscription | <?php echo htmlspecialchars($payment['gym_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-app: #0f1015;
            --bg-surface: #171821;
            --bg-card: #202230;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --lime: #ccff00;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --success: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 60%), var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
        }

        .checkout-wrapper {
            max-width: 520px;
            width: 100%;
        }

        .checkout-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .checkout-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 26px 20px;
            color: white;
            text-align: center;
        }

        .checkout-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .checkout-header p {
            font-size: 0.85rem;
            opacity: 0.85;
        }

        .checkout-body {
            padding: 24px 20px;
        }

        .price-hero {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
        }

        .price-hero .plan-tag {
            display: inline-block;
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .price-hero .amount {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            color: #ffffff;
        }

        .price-hero .cycle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .details-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .detail-row .label { color: var(--text-muted); }
        .detail-row .value { font-weight: 600; color: #fff; }

        #cashfree-drop-in {
            min-height: 280px;
            border-radius: 12px;
            overflow: hidden;
        }

        .checkout-footer {
            text-align: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.2);
            font-size: 0.75rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="checkout-wrapper">
    <div class="checkout-card">
        <div class="checkout-header">
            <h1><i class="fas fa-bolt"></i> SaaS Subscription Renewal</h1>
            <p><?php echo htmlspecialchars($payment['gym_name']); ?></p>
        </div>

        <div class="checkout-body">
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="price-hero">
                <div class="plan-tag"><?php echo htmlspecialchars($payment['plan_name']); ?> • <?php echo ucfirst(htmlspecialchars($payment['billing_cycle'])); ?></div>
                <div class="amount"><?php echo $currency . number_format((float)$payment['total_payable'], 2); ?></div>
                <div class="cycle">Total Payable including taxes</div>
            </div>

            <div class="details-list">
                <div class="detail-row">
                    <span class="label">Gym Name</span>
                    <span class="value"><?php echo htmlspecialchars($payment['gym_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Order Reference</span>
                    <span class="value">#<?php echo htmlspecialchars($payment['transaction_ref']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Quotas</span>
                    <span class="value"><?php echo number_format($payment['max_members']); ?> Members • <?php echo number_format($payment['max_staff']); ?> Staff</span>
                </div>
            </div>

            <!-- Cashfree Drop-in Component -->
            <div id="cashfree-drop-in">
                <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #6366f1; margin-bottom: 12px;"></i>
                    <div>Connecting to Cashfree Secure Gateway...</div>
                </div>
            </div>
        </div>

        <div class="checkout-footer">
            <i class="fas fa-lock" style="color: #10b981;"></i> Secured by <strong>Cashfree Payments</strong> • 256-Bit SSL<br>
            <span>Supports UPI (GPay, PhonePe, Paytm), Cards & NetBanking</span>
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
            if (result && result.error) {
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
