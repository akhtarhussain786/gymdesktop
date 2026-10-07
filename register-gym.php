<?php
ob_start();
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/tenant.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/currencies.php';
require_once __DIR__ . '/core/cashfree.php';
require_once __DIR__ . '/core/account_provisioner.php';
require_once __DIR__ . '/core/subscription_engine.php';
require_once __DIR__ . '/core/seo.php';

// Fetch active plans directly from DB
$plans = DB::fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
$currencies = get_supported_currencies();

$error = "";
$success = "";
$formData = [
    'gym_name' => '',
    'owner_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'country' => 'India',
    'state' => '',
    'city' => '',
    'currency' => '₹',
    'timezone' => 'Asia/Kolkata',
    'plan_id' => !empty($_GET['plan']) ? (int)$_GET['plan'] : 2
];

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_gym'])) {
    Auth::verifyCsrf();

    $formData['gym_name']   = trim($_POST['gym_name'] ?? '');
    $formData['owner_name'] = trim($_POST['owner_name'] ?? '');
    $formData['email']      = trim($_POST['email'] ?? '');
    $formData['phone']      = trim($_POST['phone'] ?? '');
    $formData['address']    = trim($_POST['address'] ?? '');
    $formData['country']    = trim($_POST['country'] ?? 'India');
    $formData['state']      = trim($_POST['state'] ?? '');
    $formData['city']       = trim($_POST['city'] ?? '');
    $formData['currency']   = trim($_POST['currency'] ?? '₹');
    $formData['timezone']   = trim($_POST['timezone'] ?? 'Asia/Kolkata');
    $formData['plan_id']    = (int)($_POST['plan_id'] ?? 2);

    $cleanPhone = preg_replace('/[^0-9]/', '', $formData['phone']);

    // Whitelist values that drive money formatting, payment availability (INR only) and date maths
    $validSymbols = array_column(get_supported_currencies(), 'symbol');
    if (!in_array($formData['currency'], $validSymbols, true)) {
        // trim() above strips trailing spaces in symbols like 'Rs. '; match on trimmed symbol
        $match = array_values(array_filter($validSymbols, function ($sym) use ($formData) { return trim($sym) === $formData['currency']; }));
        $formData['currency'] = $match[0] ?? '₹';
    }
    if (!in_array($formData['timezone'], timezone_identifiers_list(), true)) {
        $formData['timezone'] = 'Asia/Kolkata';
    }

    // 1. Validation
    if (empty($formData['gym_name']) || empty($formData['owner_name']) || empty($formData['email']) || empty($formData['phone'])) {
        $error = "Please fill in all required fields (Gym Name, Owner Name, Email, and Phone).";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($cleanPhone) < 10) {
        $error = "Please provide a valid 10-digit mobile phone number.";
    } else {
        // 2. Prevent duplicate gym registration with duplicate email or phone in active tenants
        $existingTenant = DB::fetchOne("SELECT id FROM tenants WHERE (LOWER(email) = LOWER(?) OR phone = ?) AND status != 'cancelled'", [$formData['email'], $cleanPhone]);
        $existingUser = DB::fetchOne("SELECT id FROM users WHERE email = ? OR phone = ?", [$formData['email'], $cleanPhone]);

        if ($existingTenant || $existingUser) {
            $error = "An account with this email address or mobile number already exists. Please login or contact support.";
        } else {
            // 3. Fetch & Verify Selected Plan from Database (Never trust frontend price)
            $selectedPlan = DB::fetchOne("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1", [$formData['plan_id']]);
            if (!$selectedPlan) {
                $error = "The selected subscription plan does not exist or is inactive.";
            } else {
                // Charge the price that matches the billing cycle that will be provisioned
                $planCycle = $selectedPlan['billing_cycle'] ?: 'monthly';
                if (!in_array($planCycle, ['trial', 'monthly', 'quarterly', 'yearly'], true)) {
                    $planCycle = 'monthly'; // 'custom' plans are billed monthly at signup
                }
                $isFreeTrial = ($planCycle === 'trial')
                    || ((float)$selectedPlan['price_monthly'] == 0.00 && (float)$selectedPlan['price_yearly'] == 0.00 && (float)($selectedPlan['price'] ?? 0) == 0.00);
                $planPrice = $isFreeTrial ? 0.00 : SubscriptionEngine::saasCyclePrice($selectedPlan, $planCycle);

                $planError = '';
                if (!$isFreeTrial && ($planPrice === null || $planPrice <= 0)) {
                    $planError = "The selected plan is not available for purchase right now. Please choose another plan.";
                } elseif ($isFreeTrial) {
                    // Trial abuse: one free trial per email / phone, ever (including cancelled / expired gyms)
                    $priorTrial = DB::fetchOne("SELECT id FROM tenants WHERE LOWER(email) = LOWER(?) OR phone = ? LIMIT 1", [$formData['email'], $cleanPhone]);
                    if ($priorTrial) {
                        $planError = "A free trial has already been used with this email address or mobile number. Please choose a paid plan or contact support.";
                    }
                }

                $regRef = 'REG_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $formData['gym_name'])));

                // -------------------------------------------------------------
                // CASE A: FREE TRIAL PLAN (₹0) -> No Cashfree, Instant Activation
                // -------------------------------------------------------------
                if ($planError !== '') {
                    $error = $planError;
                } elseif ($isFreeTrial) {
                    $provisionResult = AccountProvisioner::provisionAccount([
                        'reg_ref' => $regRef,
                        'gym_name' => $formData['gym_name'],
                        'owner_name' => $formData['owner_name'],
                        'email' => $formData['email'],
                        'phone' => $cleanPhone,
                        'address' => $formData['address'],
                        'country' => $formData['country'],
                        'state' => $formData['state'],
                        'city' => $formData['city'],
                        'currency' => $formData['currency'],
                        'timezone' => $formData['timezone'],
                        'plan_id' => $selectedPlan['id'],
                        'billing_cycle' => 'trial',
                        'amount' => 0.00
                    ]);

                    if ($provisionResult['success']) {
                        redirect("payment-status.php?ref=" . urlencode($regRef) . "&type=trial");
                    } else {
                        $error = $provisionResult['error'] ?? "Failed to provision free trial account.";
                    }
                } 
                // -------------------------------------------------------------
                // CASE B: PAID PLAN (> ₹0) -> Save Pending Onboarding & Open Cashfree
                // -------------------------------------------------------------
                else {
                    $orderId = CashfreeGateway::generateOrderId('SAAS_ONBOARD');

                    // Save pending onboarding record
                    DB::insert('pending_onboardings', [
                        'reg_ref' => $regRef,
                        'gym_name' => $formData['gym_name'],
                        'slug' => $slug,
                        'owner_name' => $formData['owner_name'],
                        'email' => $formData['email'],
                        'phone' => $cleanPhone,
                        'address' => $formData['address'],
                        'country' => $formData['country'],
                        'state' => $formData['state'],
                        'city' => $formData['city'],
                        'currency' => $formData['currency'],
                        'timezone' => $formData['timezone'],
                        'plan_id' => $selectedPlan['id'],
                        'billing_cycle' => $planCycle,
                        'amount' => $planPrice,
                        'order_id' => $orderId,
                        'status' => 'pending'
                    ]);

                    // Generate Return URL (Customer returns here after checkout)
                    $returnUrl = base_url('/payment-status.php?reg_ref=' . urlencode($regRef));

                    // Create Order in Cashfree
                    $cfOrder = CashfreeGateway::createOrder(
                        $orderId,
                        $planPrice,
                        'INR',
                        $formData['owner_name'],
                        $cleanPhone,
                        $formData['email'],
                        $returnUrl
                    );

                    if ($cfOrder['success'] && !empty($cfOrder['payment_session_id'])) {
                        // Store pending payment in saas_payments for audit
                        DB::insert('saas_payments', [
                            'tenant_id' => 0, // Assigned after verified payment
                            'reg_ref' => $regRef,
                            'plan_id' => $selectedPlan['id'],
                            'billing_cycle' => $planCycle,
                            'amount' => $planPrice,
                            'tax_amount' => 0.00,
                            'discount_amount' => 0.00,
                            'total_payable' => $planPrice,
                            'payment_method' => 'cashfree',
                            'transaction_ref' => $orderId,
                            'cf_order_id' => $cfOrder['cf_order_id'] ?? null,
                            'status' => 'pending',
                            'start_date' => date('Y-m-d'),
                            'end_date' => date('Y-m-d'),
                            'notes' => "Pending onboarding payment for {$formData['gym_name']}"
                        ]);

                        $_SESSION['onboarding_payment'] = [
                            'reg_ref' => $regRef,
                            'order_id' => $orderId,
                            'payment_session_id' => $cfOrder['payment_session_id'],
                            'plan_id' => $selectedPlan['id'],
                            'plan_name' => $selectedPlan['name'],
                            'amount' => $planPrice,
                            'gym_name' => $formData['gym_name'],
                            'owner_name' => $formData['owner_name'],
                            'email' => $formData['email'],
                            'phone' => $cleanPhone
                        ];

                        redirect("saas-checkout.php?reg_ref=" . urlencode($regRef));
                    } else {
                        $error = "Failed to initialize Cashfree checkout: " . ($cfOrder['error'] ?? 'Please check gateway configuration.');
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php SEO::renderHead('/register-gym'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css'); ?>" />
</head>
<body>

    <div class="bg-atmosphere"></div>

    <div class="onboarding-page-wrap">
        <div class="container container-narrow">
            
            <div class="step-header" style="text-align:center; margin-bottom:36px;">
                <a href="<?php echo base_url('/'); ?>" class="btn btn-ghost-dark btn-sm" style="margin-bottom:18px;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Homepage
                </a>
                <div class="badge-chip" style="margin-bottom:12px;">
                    <span class="pulse-dot"></span> INSTANT ONBOARDING ENGINE
                </div>
                <h1 style="font-size:clamp(2rem, 3.5vw, 2.8rem); margin-bottom:10px;">
                    PROVISION YOUR <span class="text-lime">FITNESS PORTAL</span>
                </h1>
                <p style="color:var(--text-body); max-width:540px; margin:0 auto;">
                    Set up your isolated multi-tenant database in seconds. Login credentials will be encrypted and emailed directly to your inbox.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background:rgba(255,90,54,0.12); border:1px solid rgba(255,90,54,0.4); border-radius:var(--radius-md); padding:16px 20px; color:#ff8a73; margin-bottom:24px; display:flex; align-items:center; gap:12px; font-weight:600;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:1.2rem;"></i>
                    <span><?php echo e($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="onboarding-card-box">
                <form method="POST" action="" id="onboarding-form">
                    <?php echo Auth::csrfField(); ?>

                    <!-- Step 1: Gym & Owner Details -->
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid var(--border-subtle);">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:30px; height:30px; border-radius:50%; background:var(--lime); color:#05080d; display:inline-flex; align-items:center; justify-content:center; font-weight:900; font-size:0.9rem;">1</span>
                            <h3 style="font-size:1.2rem; color:#fff;">Gym & Facility Profile</h3>
                        </div>
                        <span class="badge-chip" style="font-size:0.68rem;">STEP 1 OF 2</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gym / Fitness Center Name *</label>
                            <input type="text" name="gym_name" class="form-control" placeholder="e.g. Iron Forge Athletics" value="<?php echo e($formData['gym_name']); ?>" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Owner / Managing Director Name *</label>
                            <input type="text" name="owner_name" class="form-control" placeholder="e.g. Vikram Singhania" value="<?php echo e($formData['owner_name']); ?>" required />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Owner Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="Credentials sent here" value="<?php echo e($formData['email']); ?>" required />
                            <small style="color:var(--text-muted); font-size:0.75rem; margin-top:6px; display:block;">
                                <i class="fa-solid fa-shield-check text-lime"></i> Temporary master admin password will be delivered to this address.
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mobile Number *</label>
                            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number" value="<?php echo e($formData['phone']); ?>" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Physical Address</label>
                        <input type="text" name="address" class="form-control" placeholder="Street, Building, Floor" value="<?php echo e($formData['address']); ?>" />
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="<?php echo e($formData['country']); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">State / Province</label>
                            <input type="text" name="state" class="form-control" placeholder="e.g. Maharashtra" value="<?php echo e($formData['state']); ?>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai" value="<?php echo e($formData['city']); ?>" />
                        </div>
                    </div>

                    <div class="form-row" style="margin-top:4px;">
                        <div class="form-group">
                            <label class="form-label">Default Billing Currency</label>
                            <select name="currency" id="currency-select" class="form-select">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?php echo e($c['symbol']); ?>" <?php echo $c['symbol'] === $formData['currency'] ? 'selected' : ''; ?>>
                                        <?php echo e($c['name']); ?> (<?php echo e($c['symbol']); ?> - <?php echo e($c['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <input type="text" name="timezone" id="timezone-input" class="form-control" value="<?php echo e($formData['timezone']); ?>" />
                        </div>
                    </div>

                    <!-- Step 2: Select Subscription Plan -->
                    <div style="display:flex; align-items:center; justify-content:space-between; margin:32px 0 20px; padding-top:24px; border-top:1px solid var(--border-subtle);">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:30px; height:30px; border-radius:50%; background:var(--lime); color:#05080d; display:inline-flex; align-items:center; justify-content:center; font-weight:900; font-size:0.9rem;">2</span>
                            <h3 style="font-size:1.2rem; color:#fff;">Select Subscription Plan</h3>
                        </div>
                        <span class="badge-chip badge-chip-cyan" style="font-size:0.68rem;">STEP 2 OF 2</span>
                    </div>

                    <div class="plan-select-grid">
                        <?php foreach ($plans as $p): ?>
                            <?php 
                            $pCycle = in_array($p['billing_cycle'], ['monthly', 'quarterly', 'yearly'], true) ? $p['billing_cycle'] : 'monthly';
                            $isTrial = ($p['billing_cycle'] === 'trial') || ((float)$p['price_monthly'] == 0 && (float)$p['price_yearly'] == 0 && (float)($p['price'] ?? 0) == 0);
                            $price = $isTrial ? 0 : (float)(SubscriptionEngine::saasCyclePrice($p, $pCycle) ?? 0);
                            $cycleSuffix = ['monthly' => '/mo', 'quarterly' => '/quarter', 'yearly' => '/yr'][$pCycle];
                            $isSelected = ($p['id'] == $formData['plan_id']);
                            ?>
                            <label class="plan-select-card <?php echo $isSelected ? 'selected' : ''; ?>" id="card-plan-<?php echo $p['id']; ?>">
                                <input type="radio" 
                                       name="plan_id" 
                                       value="<?php echo $p['id']; ?>" 
                                       data-price="<?php echo $price; ?>" 
                                       data-name="<?php echo htmlspecialchars($p['name']); ?>" 
                                       data-istrial="<?php echo $isTrial ? '1' : '0'; ?>"
                                       <?php echo $isSelected ? 'checked' : ''; ?> 
                                       onchange="updatePlanSelection(this)" />
                                
                                <div style="font-weight:800; font-size:1.1rem; color:#fff; margin-bottom:4px;"><?php echo e($p['name']); ?></div>
                                <div style="font-size:1.6rem; font-weight:900; color:var(--lime); font-family:'Outfit'; margin:6px 0;">
                                    <?php if ($isTrial): ?>
                                        Free 14-Day Trial
                                    <?php else: ?>
                                        ₹<?php echo number_format($price, 0); ?><span style="font-size:0.8rem; font-weight:600; color:var(--text-muted);"><?php echo $cycleSuffix; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.82rem; color:var(--text-body); line-height:1.6; margin-top:10px;">
                                    • Up to <strong><?php echo number_format($p['max_members']); ?></strong> Members<br>
                                    • <strong><?php echo number_format($p['max_staff']); ?></strong> Staff/Trainer Logins<br>
                                    • <strong><?php echo number_format($p['max_branches']); ?></strong> Branch Location(s)<br>
                                    • Cashfree Gateway Verified
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Dynamic Submit Button -->
                    <div style="margin-top:36px;">
                        <button type="submit" name="register_gym" id="submit-pay-btn" class="btn btn-lime btn-lg" style="width:100%;">
                            <i class="fa-solid fa-lock"></i>
                            <span id="btn-text">Pay with Cashfree</span>
                        </button>
                        <div style="text-align:center; margin-top:14px; font-size:0.82rem; color:var(--text-muted);">
                            <i class="fa-solid fa-shield-check" style="color:#10b981;"></i> 256-Bit SSL Encrypted & Cashfree Certified Onboarding Gateway
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="assets/js/geo-currency.js"></script>
    <script>
    function updatePlanSelection(radio) {
        document.querySelectorAll('.plan-select-card').forEach(card => card.classList.remove('selected'));
        const parent = radio.closest('.plan-select-card');
        if (parent) parent.classList.add('selected');

        const isTrial = radio.getAttribute('data-istrial') === '1';
        const price = parseFloat(radio.getAttribute('data-price')) || 0;
        const name = radio.getAttribute('data-name') || '';

        const btnText = document.getElementById('btn-text');
        const submitBtn = document.getElementById('submit-pay-btn');

        if (isTrial || price === 0) {
            btnText.innerText = "Start 14-Day Free Trial";
            submitBtn.className = "btn btn-lime btn-lg";
        } else {
            btnText.innerText = "Pay ₹" + Math.round(price).toLocaleString() + " with Cashfree (" + name + ")";
            submitBtn.className = "btn btn-lime btn-lg";
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const checkedRadio = document.querySelector('input[name="plan_id"]:checked') || document.querySelector('input[name="plan_id"]');
        if (checkedRadio) {
            checkedRadio.checked = true;
            updatePlanSelection(checkedRadio);
        }
    });
    </script>
</body>
</html>
