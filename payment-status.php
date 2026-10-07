<?php
/**
 * Public Payment Result & Account Verification Page
 */
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/account_provisioner.php';

$regRef  = $_GET['reg_ref'] ?? ($_GET['ref'] ?? '');
$orderId = $_GET['order_id'] ?? '';
$isTrial = isset($_GET['type']) && $_GET['type'] === 'trial';

// Fetch details
$pending = null;
if (!empty($regRef)) {
    $pending = DB::fetchOne("SELECT p.*, sp.name as plan_name FROM pending_onboardings p JOIN subscription_plans sp ON p.plan_id = sp.id WHERE p.reg_ref = ?", [$regRef]);
}
if (!$pending && !empty($orderId)) {
    $pending = DB::fetchOne("SELECT p.*, sp.name as plan_name FROM pending_onboardings p JOIN subscription_plans sp ON p.plan_id = sp.id WHERE p.order_id = ?", [$orderId]);
}

// The "type=trial" flag is only honoured when a free-trial activation really exists for this reference
$trialRecord = null;
if ($isTrial) {
    $trialRecord = !empty($regRef) ? DB::fetchOne(
        "SELECT p.*, t.gym_name, t.email, sp.name AS plan_name FROM saas_payments p
         JOIN tenants t ON t.id = p.tenant_id LEFT JOIN subscription_plans sp ON sp.id = p.plan_id
         WHERE p.reg_ref = ? AND p.status = 'approved' AND p.payment_method = 'free_trial'",
        [$regRef]
    ) : null;
    $isTrial = (bool)$trialRecord;
}

// Prefer the registration's current order id (it may have been regenerated on retry)
$effectiveOrderId = ($pending['order_id'] ?? '') ?: ($orderId ?: ($trialRecord['transaction_ref'] ?? $regRef));
$gymName = $pending['gym_name'] ?? ($trialRecord['gym_name'] ?? 'Your Gym');
$planName = $pending['plan_name'] ?? ($trialRecord['plan_name'] ?? 'SaaS Subscription');
$contactEmail = $pending['email'] ?? ($trialRecord['email'] ?? '');
$maskedEmail = !empty($contactEmail) ? AccountProvisioner::maskEmail($contactEmail) : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Account Status | Fitisify SaaS</title>
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

        .status-container {
            max-width: 560px;
            width: 100%;
        }

        .status-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            padding: 40px 32px;
            text-align: center;
        }

        .status-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            margin-bottom: 24px;
        }

        .icon-processing {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
        }

        .icon-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
        }

        .icon-failed {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .status-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 10px;
        }

        .status-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .info-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 28px;
            text-align: left;
            font-size: 0.9rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-muted);
        }

        .info-val {
            font-weight: 700;
            color: var(--text-main);
        }

        .email-notice {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--radius-md);
            padding: 16px;
            font-size: 0.88rem;
            color: #c7d2fe;
            margin-bottom: 28px;
            text-align: left;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<div class="status-container">
    <div class="status-card">

        <!-- STATE 1: PROCESSING / VERIFYING (Default view while polling) -->
        <div id="state-processing" style="<?php echo $isTrial ? 'display:none;' : 'display:block;'; ?>">
            <div class="status-icon-wrapper icon-processing">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            <h2 class="status-title">Verifying Payment...</h2>
            <p class="status-desc">
                Your payment is being securely verified with Cashfree. Please do not close or refresh this page.
            </p>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Order Reference: <code><?php echo e($effectiveOrderId); ?></code>
            </div>
        </div>

        <!-- STATE 2: PAYMENT SUCCESSFUL & ACCOUNT CREATED -->
        <div id="state-success" style="<?php echo $isTrial ? 'display:block;' : 'display:none;'; ?>">
            <div class="status-icon-wrapper icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="status-title">
                <?php echo $isTrial ? 'Free Trial Activated!' : 'Payment Successful!'; ?>
            </h2>
            <p class="status-desc" style="color: #10b981; font-weight: 600;">
                Your Gym Management SaaS account is ready.
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Gym Name</span>
                    <span class="info-val" id="res-gym-name"><?php echo e($gymName); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Subscription Tier</span>
                    <span class="info-val" id="res-plan-name"><?php echo e($planName); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Reference</span>
                    <span class="info-val" id="res-order-id"><code><?php echo e($effectiveOrderId); ?></code></span>
                </div>
            </div>

            <div class="email-notice">
                <i class="fas fa-envelope-open-text" style="color: var(--primary); margin-right: 6px;"></i>
                Login credentials (User ID & Temporary Password) have been sent to your registered email: 
                <strong id="res-masked-email" style="color: #fff;"><?php echo e($maskedEmail ?: 'your email'); ?></strong>.
                <div style="font-size: 0.8rem; margin-top: 8px; opacity: 0.85;">
                    * You will be required to update your temporary password upon your first login.
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="index.php" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </a>
            </div>
        </div>

        <!-- STATE 3: PAYMENT FAILED -->
        <div id="state-failed" style="display: none;">
            <div class="status-icon-wrapper icon-failed">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2 class="status-title">Payment Failed</h2>
            <p class="status-desc">
                No account has been activated. <span id="fail-reason">The transaction was cancelled or declined by the gateway.</span>
            </p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Gym Registration</span>
                    <span class="info-val"><?php echo e($gymName); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Selected Plan</span>
                    <span class="info-val"><?php echo e($planName); ?></span>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <a href="saas-checkout.php?reg_ref=<?php echo urlencode($regRef); ?>" class="btn btn-primary btn-lg" style="flex: 1;">
                    <i class="fas fa-redo"></i> Retry Payment
                </a>
                <a href="index.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-home"></i> Home
                </a>
            </div>
        </div>

        <!-- STATE 4: PAYMENT PENDING -->
        <div id="state-pending" style="display: none;">
            <div class="status-icon-wrapper" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <h2 class="status-title">Payment Pending</h2>
            <p class="status-desc">
                We are awaiting final confirmation from Cashfree. Your account will be activated once the gateway confirms payment.
            </p>
            <a href="index.php" class="btn btn-secondary">Return to Home</a>
        </div>

    </div>
</div>

<script>
<?php if (!$isTrial): ?>
let pollAttempts = 0;
const maxAttempts = 15;
const orderId = "<?php echo urlencode($effectiveOrderId); ?>";
const regRef = "<?php echo urlencode($regRef); ?>";

function checkStatus() {
    pollAttempts++;
    fetch(`api/check-payment-status.php?order_id=${orderId}&reg_ref=${regRef}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'SUCCESS') {
                showSuccess(data);
            } else if (data.status === 'FAILED') {
                showFailed(data.reason);
            } else if (data.status === 'PENDING') {
                if (pollAttempts >= maxAttempts) {
                    showPending();
                } else {
                    setTimeout(checkStatus, 2500);
                }
            } else {
                if (pollAttempts < maxAttempts) {
                    setTimeout(checkStatus, 2500);
                } else {
                    showFailed(data.message || 'Verification timed out.');
                }
            }
        })
        .catch(err => {
            console.error('Status check error:', err);
            if (pollAttempts < maxAttempts) {
                setTimeout(checkStatus, 3000);
            } else {
                showFailed('Could not reach verification server.');
            }
        });
}

function showSuccess(data) {
    document.getElementById('state-processing').style.display = 'none';
    document.getElementById('state-failed').style.display = 'none';
    document.getElementById('state-pending').style.display = 'none';
    document.getElementById('state-success').style.display = 'block';

    if (data.gym_name) document.getElementById('res-gym-name').innerText = data.gym_name;
    if (data.plan_name) document.getElementById('res-plan-name').innerText = data.plan_name;
    if (data.order_id) document.getElementById('res-order-id').innerHTML = `<code>${data.order_id}</code>`;
    if (data.masked_email) document.getElementById('res-masked-email').innerText = data.masked_email;
}

function showFailed(reason) {
    document.getElementById('state-processing').style.display = 'none';
    document.getElementById('state-success').style.display = 'none';
    document.getElementById('state-pending').style.display = 'none';
    document.getElementById('state-failed').style.display = 'block';
    if (reason) document.getElementById('fail-reason').innerText = reason;
}

function showPending() {
    document.getElementById('state-processing').style.display = 'none';
    document.getElementById('state-success').style.display = 'none';
    document.getElementById('state-failed').style.display = 'none';
    document.getElementById('state-pending').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', () => {
    checkStatus();
});
<?php endif; ?>
</script>

</body>
</html>
