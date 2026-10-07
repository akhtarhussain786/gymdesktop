<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/subscription_engine.php';
Auth::requireAuth('gym_admin');

$page = 'settings';
$pageTitle = 'Gym Subscription & Billing';
$pageSubtitle = 'Manage your SaaS rental plan, renewals, payments, and quotas';
$tenant = Tenant::getCurrent();
$tenantId = Tenant::getTenantId();
$subStatus = Tenant::getSubscriptionStatus();

$error = "";
$success = "";

// Handle Renewal / Plan Upgrade submission (manual / offline payment)
// Offline payments are recorded as PENDING and only extend the subscription once a super admin
// approves them (superadmin/payments.php → SubscriptionEngine::applySaasPayment()).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_plan'])) {
    Auth::verifyCsrf();

    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $cycle = $_POST['billing_cycle'] ?? 'monthly'; // monthly, quarterly, yearly
    $payment_method = $_POST['payment_method'] ?? 'upi';
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));

    if (!in_array($payment_method, ['upi', 'card', 'bank_transfer', 'cash'], true)) {
        redirect('subscription.php', 'error', 'Invalid payment method.');
    }
    if ($transaction_ref !== '' && !preg_match('/^[A-Za-z0-9\-_\/]{4,64}$/', $transaction_ref)) {
        redirect('subscription.php', 'error', 'Please enter a valid transaction reference.');
    }

    $quote = SubscriptionEngine::quoteSaasRenewal($plan_id, $cycle, $coupon_code);
    if (!$quote['success']) {
        redirect('subscription.php', 'error', $quote['error']);
    }
    $selectedPlan = $quote['plan'];

    // The same reference cannot be submitted twice
    if ($transaction_ref !== '' && DB::fetchValue("SELECT id FROM saas_payments WHERE transaction_ref = ?", [$transaction_ref])) {
        redirect('subscription.php', 'error', 'This transaction reference has already been submitted.');
    }

    // One open manual request at a time per gym
    $openRequest = DB::fetchValue("SELECT id FROM saas_payments WHERE tenant_id = ? AND status = 'pending' AND payment_method <> 'cashfree'", [$tenantId]);
    if ($openRequest) {
        redirect('subscription.php', 'error', 'You already have a renewal request awaiting verification.');
    }

    $today = date('Y-m-d');

    // Insert SaaS payment transaction as PENDING verification — expiry is NOT extended here
    $paymentId = DB::insert('saas_payments', [
        'tenant_id' => $tenantId,
        'plan_id' => $plan_id,
        'billing_cycle' => $cycle,
        'amount' => $quote['base_price'],
        'tax_amount' => $quote['tax'],
        'discount_amount' => $quote['discount'],
        'total_payable' => $quote['total'],
        'coupon_code' => $quote['coupon_code'],
        'payment_method' => $payment_method,
        'transaction_ref' => $transaction_ref ?: CashfreeGateway::generateOrderId('MANUAL'),
        'status' => 'pending',
        'start_date' => $today,
        'end_date' => $today,
        'notes' => 'Subscription renewal via ' . strtoupper($payment_method) . ' — awaiting verification'
    ]);

    if (!$paymentId) {
        redirect('subscription.php', 'error', 'Could not record your renewal request. Please try again.');
    }

    Auth::auditLog('RENEW_SUBSCRIPTION_REQUEST', "Gym requested renewal to {$selectedPlan['name']} ({$cycle}) via " . strtoupper($payment_method) . " — pending verification");
    redirect('subscription.php', 'success', "Renewal request submitted. Your subscription will be extended once the payment is verified.");
}

$allPlans = DB::fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
$paymentHistory = DB::fetchAll("SELECT p.*, sp.name as plan_name 
                               FROM saas_payments p 
                               LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                               WHERE p.tenant_id = ? 
                               ORDER BY p.id DESC", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Expiry Notice Banner if applicable -->
<?php echo Tenant::renderExpiryBanner(); ?>

<!-- Current Subscription Overview Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-certificate"></i>
            <span>Current Subscription Status</span>
        </div>
        <div>
            <?php echo status_badge($subStatus['state']); ?>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="background: var(--bg-app); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Active Tier</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-top: 4px;">
                    <?php echo e($tenant['plan_name'] ?? 'Pro Tier'); ?>
                </div>
            </div>

            <div style="background: var(--bg-app); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Expires On</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-top: 4px;">
                    <?php echo format_date($tenant['subscription_expiry']); ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                    <?php echo $subStatus['days_left'] > 0 ? "({$subStatus['days_left']} days remaining)" : "(Expired)"; ?>
                </div>
            </div>

            <div style="background: var(--bg-app); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Member Quota</div>
                <?php $mQuota = Tenant::checkLimit('members'); ?>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--secondary); margin-top: 4px;">
                    <?php echo $mQuota['current']; ?> / <?php echo $mQuota['max']; ?>
                </div>
            </div>

            <div style="background: var(--bg-app); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Staff & Branch Quota</div>
                <?php $sQuota = Tenant::checkLimit('staff'); $bQuota = Tenant::checkLimit('branches'); ?>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-top: 6px;">
                    Staff: <strong><?php echo $sQuota['current']; ?>/<?php echo $sQuota['max']; ?></strong> • Branches: <strong><?php echo $bQuota['current']; ?>/<?php echo $bQuota['max']; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Renew / Upgrade Subscription Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-rocket"></i>
            <span>Renew or Upgrade Subscription Plan</span>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px;">
                <?php foreach ($allPlans as $p): ?>
                    <label class="plan-option <?php echo $p['id'] == $tenant['subscription_plan_id'] ? 'selected' : ''; ?>" style="border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; cursor: pointer; display: block; position: relative;">
                        <input type="radio" name="plan_id" value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $tenant['subscription_plan_id'] ? 'checked' : ''; ?> style="position: absolute; top: 16px; right: 16px;" />
                        <div style="font-weight: 700; font-size: 1.15rem; color: var(--text-main);"><?php echo e($p['name']); ?></div>
                        <div style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 8px 0;">
                            ₹<?php echo number_format($p['price_monthly'], 0); ?><span style="font-size: 0.85rem; color: var(--text-muted);">/mo</span>
                        </div>
                        <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.7;">
                            • <strong><?php echo $p['max_members']; ?></strong> Member Capacity<br>
                            • <strong><?php echo $p['max_staff']; ?></strong> Staff Logins<br>
                            • <strong><?php echo $p['max_branches']; ?></strong> Branch Location(s)<br>
                            • <strong><?php echo $p['grace_period_days']; ?></strong> Days Grace Period
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Select Renewal Duration</label>
                    <select name="billing_cycle" class="form-select">
                        <option value="monthly">1 Month (Standard)</option>
                        <option value="quarterly">3 Months (Quarterly - 10% OFF)</option>
                        <option value="yearly">12 Months (Yearly plan price)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="sub-payment-method" class="form-select" onchange="toggleSubPaymentMode()">
                        <option value="cashfree">💳 Pay Online via Cashfree (UPI / Cards / NetBanking)</option>
                        <option value="upi">Manual UPI / Instant QR Code</option>
                        <option value="card">Credit / Debit Card (Offline Swipe)</option>
                        <option value="bank_transfer">Direct Bank Wire / NEFT</option>
                        <option value="cash">Manual / Cash</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Discount Coupon Code (Optional)</label>
                    <input type="text" name="coupon_code" class="form-control" placeholder="e.g. WELCOME20" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Transaction Reference / Receipt ID (Optional)</label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="UPI Reference or Bank Txn ID" />
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" name="renew_plan" value="1" id="sub-btn-manual" class="btn btn-primary btn-lg" style="display: none;">
                    <i class="fas fa-check-circle"></i> Confirm & Process Renewal (Manual)
                </button>
                <button type="submit" name="cashfree_sub_pay" value="1" id="sub-btn-online" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #4f46e5, #7c3aed);">
                    <i class="fas fa-credit-card"></i> Pay Online via Cashfree
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SaaS Subscription Invoices & Payment History Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>Subscription Billing History</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Plan</th>
                        <th>Duration</th>
                        <th>Amount Paid</th>
                        <th>Payment Mode</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentHistory)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No subscription payment history found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paymentHistory as $p): ?>
                            <tr>
                                <td><strong>#SAAS-<?php echo str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo e($p['plan_name']); ?></td>
                                <td><span class="status-badge badge-info"><?php echo e(ucfirst($p['billing_cycle'])); ?></span></td>
                                <td><strong>₹<?php echo number_format($p['total_payable'], 2); ?></strong></td>
                                <td><?php echo e(strtoupper($p['payment_method'])); ?></td>
                                <td><code style="font-size: 0.8rem;"><?php echo e($p['transaction_ref'] ?? 'N/A'); ?></code></td>
                                <td><?php echo format_date($p['created_at']); ?></td>
                                <td><?php echo status_badge($p['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleSubPaymentMode() {
    var method = document.getElementById('sub-payment-method').value;
    var form = document.querySelector('form[method="POST"]');
    var btnManual = document.getElementById('sub-btn-manual');
    var btnOnline = document.getElementById('sub-btn-online');

    if (method === 'cashfree') {
        form.action = 'cashfree-subscription-checkout.php';
        btnManual.style.display = 'none';
        btnOnline.style.display = 'inline-flex';
    } else {
        form.action = '';
        btnManual.style.display = 'inline-flex';
        btnOnline.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleSubPaymentMode);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
