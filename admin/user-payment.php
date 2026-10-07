<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'payment';
$pageTitle = 'Record Member Payment / Renewal';
$pageSubtitle = 'Process fee collection, update membership plan, and generate receipt';
$tenantId = Tenant::getTenantId();
$memberId = (int)($_GET['id'] ?? 0);

// Member must belong to the current tenant — never look across tenants or switch tenant context
$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    redirect('payment.php', 'error', 'Member not found.');
}

// Fresh tenant record directly from DB to guarantee live UPI ID
$tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
if (!$tenant) {
    $tenant = Tenant::getCurrent();
}

$rates = Tenant::getRates($tenantId);

// Ensure member's current assigned package is present in $rates
$hasMemberService = false;
$memberService = trim($member['services'] ?? '');
if (!empty($memberService)) {
    foreach ($rates as $r) {
        if (strcasecmp($r['name'], $memberService) === 0) {
            $hasMemberService = true;
            break;
        }
    }
    if (!$hasMemberService) {
        $memberMonthly = !empty($member['amount']) && !empty($member['plan'])
            ? round((float)$member['amount'] / max(1, (int)$member['plan']), 2)
            : 500.00;
        array_unshift($rates, [
            'id' => 0,
            'tenant_id' => $tenantId,
            'name' => $memberService,
            'charge' => $memberMonthly
        ]);
    }
}

// Compute initial monthly charge
$initialMonthlyRate = 500.00;
if (!empty($rates)) {
    $selectedRate = null;
    if (!empty($memberService)) {
        foreach ($rates as $r) {
            if (strcasecmp($r['name'], $memberService) === 0) {
                $selectedRate = (float)$r['charge'];
                break;
            }
        }
    }
    $initialMonthlyRate = $selectedRate !== null ? $selectedRate : (float)($rates[0]['charge'] ?? 500.00);
}

$upiId = trim($tenant['upi_id'] ?? '');
$gymName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Gym Owner';

require_once __DIR__ . '/../core/subscription_engine.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    Auth::verifyCsrf();

    $services = trim($_POST['services'] ?? 'Fitness');
    $rateAmount = (float)($_POST['amount'] ?? 0);
    $planMonths = max(1, (int)($_POST['plan'] ?? 1));
    $statusInput = $_POST['status'] ?? 'Active';
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $paidDate = !empty($_POST['paid_date']) ? $_POST['paid_date'] : date('Y-m-d');

    // Validate payment date (real calendar date, not in the future)
    $pd = DateTime::createFromFormat('Y-m-d', (string)$paidDate);
    if (!$pd || $pd->format('Y-m-d') !== $paidDate || $paidDate > date('Y-m-d')) {
        redirect("user-payment.php?id=$memberId", 'error', 'Invalid payment date.');
    }
    if ($rateAmount <= 0) {
        redirect("user-payment.php?id=$memberId", 'error', 'Monthly rate must be greater than zero.');
    }
    if (!Tenant::canWrite()) {
        redirect("user-payment.php?id=$memberId", 'error', 'Your gym subscription has expired. Renew to record member payments.');
    }

    $totalPayable = round($rateAmount * $planMonths, 2);

    DB::beginTransaction();

    try {
        // Serialise concurrent renewals for this member before computing the queue
        DB::fetchAll("SELECT id FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? FOR UPDATE", [$tenantId, $memberId]);

        // Calculate queued start date and expiry date
        $startDate = SubscriptionEngine::calculateNextStartDate($tenantId, $memberId);
        $expiryDate = SubscriptionEngine::calculateExpiryDate($startDate, $planMonths);
        $today = date('Y-m-d');

        $hasActive = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'active' AND expiry_date >= ?",
            [$tenantId, $memberId, $today]
        );

        $subStatus = ($hasActive > 0 || strtotime($startDate) > strtotime($today)) ? 'upcoming' : 'active';
        $upcomingCount = (int)DB::fetchValue(
            "SELECT COUNT(*) FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'upcoming'",
            [$tenantId, $memberId]
        );

        // Collision-free invoice number is assigned from the inserted id below
        $invoiceNumber = SubscriptionEngine::tempInvoiceNumber();

        $transactionRef = 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8));

        // 1. Insert Invoice
        $invoiceId = DB::insert('invoices', [
            'tenant_id' => $tenantId,
            'branch_id' => (int)($member['branch_id'] ?? 1),
            'member_id' => $memberId,
            'invoice_number' => $invoiceNumber,
            'service_name' => $services . ($subStatus === 'upcoming' ? ' (Upcoming)' : ''),
            'plan_months' => $planMonths,
            'amount' => $totalPayable,
            'paid_amount' => $totalPayable,
            'discount' => 0.00,
            'payment_method' => $paymentMethod,
            'payment_date' => $paidDate,
            'status' => 'Paid',
            'transaction_ref' => $transactionRef,
            'notes' => "Payment recorded by Gym Admin for {$services} ({$planMonths} Mo)",
            'created_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        if (!$invoiceId) {
            throw new RuntimeException('Could not create invoice.');
        }
        $invoiceNumber = SubscriptionEngine::assignInvoiceNumber($invoiceId, $tenant, $paidDate);

        // 2. Insert Member Subscription Record
        $subId = DB::insert('member_subscriptions', [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'plan_name_snapshot' => $services,
            'plan_price_snapshot' => $totalPayable,
            'plan_duration_snapshot' => $planMonths,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'status' => $subStatus,
            'payment_id' => $invoiceId,
            'queue_position' => ($subStatus === 'upcoming' ? $upcomingCount + 1 : 1),
            'activated_at' => ($subStatus === 'active' ? date('Y-m-d H:i:s') : null),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 3. Update Member master record
        if ($subStatus === 'active') {
            DB::update('members', [
                'services' => $services,
                'amount' => $totalPayable,
                'plan' => $planMonths,
                'status' => 'Active',
                // paid_date = start of the current period (expiry = paid_date + plan months)
                'paid_date' => $startDate,
                'reminder' => 0
            ], 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
        }

        DB::commit();

        Auth::auditLog('RECORD_PAYMENT', "Processed {$subStatus} payment of " . format_currency($totalPayable) . " for member {$member['fullname']} (Invoice #{$invoiceNumber})");

        redirect("userpay.php?id=$invoiceId", 'success', "Payment recorded! Invoice #{$invoiceNumber} generated successfully.");
    } catch (Throwable $e) {
        DB::rollback();
        error_log("Payment Process Error: " . $e->getMessage());
        redirect("user-payment.php?id=$memberId", 'error', "Failed to process payment. Please try again.");
    }
}

$upcomingSubs = DB::fetchAll(
    "SELECT * FROM member_subscriptions WHERE tenant_id = ? AND member_id = ? AND status = 'upcoming' ORDER BY start_date ASC",
    [$tenantId, $memberId]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-cash-register"></i>
            <span>Record Payment for <?php echo e($member['fullname']); ?></span>
        </div>
        <a href="payment.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
            <div style="font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Member Summary</div>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-top: 4px;">
                <?php echo e($member['fullname']); ?> (#MEM-<?php echo str_pad($member['user_id'], 4, '0', STR_PAD_LEFT); ?>)
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                Current Plan: <strong><?php echo e($member['services']); ?></strong> (<?php echo $member['plan']; ?> Mo) • Status: <?php echo status_badge($member['status']); ?>
            </div>
        </div>

        <?php if (!empty($upcomingSubs)): ?>
            <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.3); padding: 14px; border-radius: var(--radius-md); margin-bottom: 20px;">
                <div style="font-size: 0.85rem; font-weight: 800; color: var(--primary); text-transform: uppercase;">
                    <i class="fas fa-clock"></i> Upcoming Membership Queue (<?php echo count($upcomingSubs); ?> Scheduled)
                </div>
                <?php foreach ($upcomingSubs as $idx => $us): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed rgba(99,102,241,0.2);">
                        <div>
                            <strong>#<?php echo $idx + 1; ?> <?php echo e($us['plan_name_snapshot']); ?></strong> (<?php echo $us['plan_duration_snapshot']; ?> Mo)
                            <div style="font-size: 0.78rem; color: var(--text-muted);">
                                Scheduled: <?php echo format_date($us['start_date']); ?> to <?php echo format_date($us['expiry_date']); ?>
                            </div>
                        </div>
                        <span class="status-badge badge-info">Upcoming</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>

            <div class="form-row">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" style="margin-bottom: 0;">Service Package *</label>
                        <a href="rates.php" target="_blank" style="font-size: 0.78rem; color: var(--primary); text-decoration: none; font-weight: 600;">
                            <i class="fas fa-cog"></i> Manage Packages
                        </a>
                    </div>
                    <select name="services" id="service-select" class="form-select" onchange="updatePayTotal()">
                        <?php foreach ($rates as $r): ?>
                            <option value="<?php echo e($r['name']); ?>" data-rate="<?php echo e($r['charge']); ?>" <?php echo strcasecmp($member['services'], $r['name']) === 0 ? 'selected' : ''; ?>>
                                <?php echo e($r['name']); ?> (<?php echo format_currency($r['charge']); ?>/mo)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Renewal Duration *</label>
                    <select name="plan" id="plan-select" class="form-select" onchange="updatePayTotal()">
                        <option value="1">1 Month</option>
                        <option value="3">3 Months</option>
                        <option value="6">6 Months</option>
                        <option value="12">12 Months (1 Year)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Monthly Rate (<?php echo $tenant['currency']; ?>) *</label>
                    <input type="number" step="0.01" name="amount" id="rate-input" class="form-control" value="<?php echo $initialMonthlyRate; ?>" oninput="updatePayTotal()" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" id="payment-method-select" class="form-select" onchange="togglePaymentMode()">
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI / QR Code</option>
                        <option value="Card">Credit / Debit Card</option>
                        <option value="Bank Transfer">Bank Wire</option>
                        <option value="Cashfree">💳 Pay Online (Cashfree)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Payment Date *</label>
                    <input type="date" name="paid_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Membership Status</label>
                    <select name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
            </div>

            <!-- Dynamic UPI QR Code Box for Member Payment -->
            <div id="upi-qr-box" style="background: rgba(59, 130, 246, 0.05); border: 2px dashed rgba(59, 130, 246, 0.3); border-radius: var(--radius-md); padding: 16px; margin-top: 15px; margin-bottom: 20px; text-align: center;">
                <div style="font-weight: 700; font-size: 0.95rem; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-qrcode" style="font-size: 1.2rem;"></i>
                    <span>Scan QR Code to Pay Directly to Gym Owner (UPI)</span>
                </div>
                
                <?php if (!empty($upiId)): ?>
                <div style="display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 20px; margin-top: 10px;">
                    <div style="background: #ffffff; padding: 10px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: inline-block;">
                        <img id="upi-qr-img" src="" alt="Payment QR Code" style="width: 180px; height: 180px; display: block;" />
                    </div>
                    
                    <div style="text-align: left; max-width: 320px;">
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Payee Gym Name:</div>
                        <div style="font-weight: 800; font-size: 1rem; color: var(--text-main); margin-bottom: 6px;"><?php echo e($gymName); ?></div>
                        
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Gym Owner UPI ID / VPA:</div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--primary); margin-bottom: 10px; word-break: break-all;">
                            <span id="upi-id-display"><?php echo e($upiId); ?></span>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo e($upiId); ?>'); alert('UPI ID Copied to clipboard!');" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 0.75rem; margin-left: 6px;">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Payable Amount:</div>
                        <div style="font-weight: 900; font-size: 1.3rem; color: var(--primary);" id="upi-qr-amount">
                            <?php echo format_currency($initialMonthlyRate); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px;">
                            Works with Google Pay, PhonePe, Paytm, BHIM & any UPI Scanner.
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="padding: 14px 16px; margin-top: 8px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-md); text-align: center;">
                    <div style="font-weight: 700; color: #f59e0b; margin-bottom: 4px;">
                        <i class="fas fa-exclamation-circle"></i> UPI ID is not configured
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Please add your gym's UPI ID in <a href="settings.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Settings &rarr; Branding & System Settings</a> to enable QR code payments.
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div style="background: var(--bg-surface); border: 2px solid var(--primary); padding: 18px; border-radius: var(--radius-md); margin-top: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-main);">Total Amount Collected:</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="total-pay-display">
                    <?php echo format_currency($initialMonthlyRate); ?>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <a href="payment.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="process_payment" value="1" id="btn-manual-pay" class="btn btn-primary btn-lg">
                    <i class="fas fa-receipt"></i> Process Payment & Print Receipt
                </button>
                <button type="submit" name="cashfree_pay" value="1" id="btn-online-pay" class="btn btn-primary btn-lg" style="display:none; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    <i class="fas fa-credit-card"></i> Pay Online via Cashfree
                </button>
            </div>
            <input type="hidden" name="member_id" value="<?php echo $memberId; ?>" />
        </form>
    </div>
</div>

<script>
function updatePayTotal() {
    const rate = parseFloat(document.getElementById('rate-input').value) || 0;
    const plan = parseInt(document.getElementById('plan-select').value) || 1;
    const total = (rate * plan).toFixed(2);
    const currency = "<?php echo $tenant['currency']; ?>";
    document.getElementById('total-pay-display').innerText = currency + total;

    // Live Dynamic UPI QR Code Generation
    const upiId = "<?php echo e($upiId); ?>";
    const gymName = "<?php echo e($gymName); ?>";
    const memberName = "<?php echo e($member['fullname']); ?>";
    const serviceSelect = document.getElementById('service-select');
    const serviceName = serviceSelect ? serviceSelect.value : 'Fitness';

    if (upiId) {
        const note = encodeURIComponent("Renewal-" + serviceName + "-" + memberName);
        const payeeName = encodeURIComponent(gymName.substring(0, 50));
        const upiString = "upi://pay?pa=" + encodeURIComponent(upiId) + "&pn=" + payeeName + "&am=" + total + "&cu=INR&tn=" + note;
        const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" + encodeURIComponent(upiString) + "&margin=10";

        const qrImg = document.getElementById('upi-qr-img');
        if (qrImg) {
            qrImg.src = qrUrl;
        }
    }
    const upiAmt = document.getElementById('upi-qr-amount');
    if (upiAmt) {
        upiAmt.innerText = currency + total;
    }
}

var serviceSelectEl = document.getElementById('service-select');
if (serviceSelectEl) {
    serviceSelectEl.addEventListener('change', function() {
        if (this.selectedIndex >= 0 && this.options[this.selectedIndex]) {
            const opt = this.options[this.selectedIndex];
            const baseRate = opt.getAttribute('data-rate');
            if (baseRate !== null && baseRate !== '') {
                document.getElementById('rate-input').value = baseRate;
                updatePayTotal();
            }
        }
    });
}
updatePayTotal();

function togglePaymentMode() {
    var method = document.getElementById('payment-method-select').value;
    var form = document.querySelector('form[method="POST"]');
    var btnManual = document.getElementById('btn-manual-pay');
    var btnOnline = document.getElementById('btn-online-pay');

    if (method === 'Cashfree') {
        form.action = 'cashfree-checkout.php';
        btnManual.style.display = 'none';
        btnOnline.style.display = 'inline-flex';
    } else {
        form.action = '';
        btnManual.style.display = 'inline-flex';
        btnOnline.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>